# تست

## دستورها

بعد از `composer install` در ریشه پکیج:

```bash
vendor/bin/phpunit
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --testsuite Scenarios
vendor/bin/phpunit --testsuite Integration
```

`phpunit.xml` سه suite دارد: `Feature`، `Scenarios`، `Integration`. هشدار و risky شکست است. اتصال پیش‌فرض تست SQLite در حافظه است و `foreign_key_constraints` روشن است.

suite جداگانه‌ای به نام Unit وجود ندارد. پوشش هسته داخل Feature است.

## Integration و skip

`DatabaseCompatibilityTest` روی SQLite هم schema، کوئری نوع‌دار، نسخه‌بندی و غیرخالی بودن `EXPLAIN` را چک می‌کند.

`ConcurrencyTest` بدون `META_INTEGRATION=1` با پیام صریح skip می‌شود. SQLite برای اثبات row lock استفاده نمی‌شود.

اگر `META_INTEGRATION=1` باشد و `META_DB_DRIVER` برابر `mysql` یا `pgsql` نباشد، همان کلاس fail می‌شود، نه skip.

```bash
META_INTEGRATION=1 META_DB_DRIVER=mysql META_DB_DATABASE=meta_test \
  vendor/bin/phpunit --testsuite Integration
```

متغیرهای `META_DB_HOST`، `META_DB_PORT`، `META_DB_USERNAME` و `META_DB_PASSWORD` هم خوانده می‌شوند.

این تست‌ها تراکنش RefreshDatabase را برای اتصال تست خاموش می‌کنند تا پروسه worker ردیف commitشده را ببیند.

## CI

`.github/workflows/tests.yml`:

- PHP 8.3 و 8.4: `composer validate`، `composer install`، Pint، `php -l`، Feature، Scenarios، Integration روی SQLite.
- job جدا برای MySQL 8.4 و PostgreSQL 16 با `META_INTEGRATION=1`. نبود دیتابیس این job را سبز نمی‌کند.

## بنچمارک

جزو PHPUnit نیست.

```bash
php bench/benchmark.php
php bench/benchmark.php 100000 --confirm
php bench/benchmark.php 1000000 --confirm
php bench/benchmark.php 10000 --reuse
```

`--reuse` نوشتن را تکرار نمی‌کند و فقط خواندن را روی `bench/results/bench.sqlite` اندازه می‌گیرد. این پوشه gitignore شده است. اسکریپت migration اپلیکیشن میزبان را اجرا نمی‌کند.

## Pint

```bash
vendor/bin/pint --test
```
