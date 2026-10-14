<?php

declare(strict_types=1);

namespace Karnoweb\Meta\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Karnoweb\Meta\Tests\Fixtures\Product;
use RuntimeException;

class ConcurrencyTest extends IntegrationTestCase
{
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        if (getenv('META_INTEGRATION') !== '1') {
            $this->markTestSkipped('META_INTEGRATION=1 is required. SQLite is not used to prove row locks.');
        }

        $driver = getenv('META_DB_DRIVER') ?: '';

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->fail('META_INTEGRATION=1 requires META_DB_DRIVER=mysql or pgsql. The suite must not pass on a silent skip.');
        }

        parent::setUp();
    }

    public function test_rollback_keeps_the_previous_root_and_history(): void
    {
        $product = $this->product();
        $product->setMeta('color', 'red');
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');

        try {
            DB::transaction(function () use ($product): void {
                $product->setMeta('color', 'blue');
                $product->setMeta('price', '11.00', type: 'decimal');
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
            $this->assertSame('red', $product->getMeta('color'));
            $this->assertSame('10.0000000000', $product->getMeta('price'));
            $this->assertSame(1, DB::table('meta_versions')->count());
        }
    }

    public function test_two_processes_create_a_single_root(): void
    {
        $product = $this->product();
        $this->runWorkers($product->id, 'color', ['red', 'blue']);

        $this->assertSame(1, DB::table('metas')->where('key', 'color')->count());
        $this->assertContains($product->getMeta('color'), ['red', 'blue']);
    }

    public function test_two_processes_append_two_versions(): void
    {
        $product = $this->product();
        $product->setMeta('price', '10.00', versioned: true, type: 'decimal');
        $this->runWorkers($product->id, 'price', ['11.00', '12.00'], 'decimal');

        $this->assertSame(1, DB::table('metas')->where('key', 'price')->count());
        $this->assertGreaterThanOrEqual(3, DB::table('meta_versions')->count());
        $this->assertContains($product->getMeta('price'), ['11.0000000000', '12.0000000000']);
    }

    public function test_promotion_and_a_concurrent_write_keep_one_versioned_root(): void
    {
        $product = $this->product();
        $product->setMeta('status', 'draft');
        $this->runWorkers($product->id, 'status', ['active', 'review'], versioned: true);

        $root = DB::table('metas')->where('key', 'status')->first();
        $this->assertNotNull($root);
        $this->assertSame(1, DB::table('metas')->where('key', 'status')->count());
        $this->assertSame(1, (int) $root->versioned);
        $this->assertGreaterThanOrEqual(2, DB::table('meta_versions')->where('meta_id', $root->id)->count());
        $this->assertContains($product->getMeta('status'), ['active', 'review']);
    }

    /**
     * @param  list<string>  $values
     */
    private function runWorkers(int $ownerId, string $key, array $values, ?string $type = null, bool $versioned = false): void
    {
        $processes = [];

        foreach ($values as $value) {
            $command = [
                PHP_BINARY,
                dirname(__DIR__).DIRECTORY_SEPARATOR.'Integration'.DIRECTORY_SEPARATOR.'worker.php',
                (string) $ownerId,
                $key,
                $value,
                (string) $type,
                $versioned ? '1' : '0',
            ];
            $pipes = [];
            $process = proc_open($command, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, dirname(__DIR__, 2));

            if (! is_resource($process)) {
                $this->fail('Could not start a concurrency worker.');
            }

            $processes[] = ['process' => $process, 'pipes' => $pipes];
        }

        foreach ($processes as $item) {
            $stdout = stream_get_contents($item['pipes'][1]);
            $stderr = stream_get_contents($item['pipes'][2]);
            fclose($item['pipes'][1]);
            fclose($item['pipes'][2]);
            $exit = proc_close($item['process']);

            if ($exit !== 0) {
                $this->fail("Worker failed ({$exit}): {$stdout} {$stderr}");
            }
        }
    }
}
