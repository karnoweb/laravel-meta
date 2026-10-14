# karnoweb/laravel-meta

متادیتای نوع‌دار برای مدل Eloquent. هر کلید دو حالت دارد:

- **عادی:** یک مقدار جاری. تغییر بعدی همان ردیف را عوض می‌کند و تاریخچه نمی‌سازد.
- **نسخه‌دار:** فقط اگر همان بار اول `versioned: true` بدهید. مقدار جاری در جدول تاریخچه است، نه روی ردیف مادر.

نصب منتشرشده روی SQLite تست شده است. MySQL 8.4 و PostgreSQL 16 در workflow هستند و در این محیط اجرا نشده‌اند. تا سبز شدن آن jobها این بسته `READY FOR PRODUCTION` نیست.

## نصب

مدل باید از قبل ذخیره شده باشد. `setMeta` و `setMetas` همان لحظه در دیتابیس می‌نویسند و به `$product->save()` نیاز ندارند.

```bash
composer require karnoweb/laravel-meta
php artisan migrate
```

انتشار config یا migration لازم نیست. اگر دیتابیس را با نسخه قبلی همین بسته migrate کرده‌اید، `php artisan migrate` فایل `2020_01_01_090001_align_meta_exact_storage.php` را هم اجرا می‌کند. دقت decimal که SQLite قبلاً خراب کرده باشد برنمی‌گردد.

```php
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Traits\HasMeta;

class Product extends Model
{
    use HasMeta;
}
```

## ذخیره یک مقدار

```php
$product->setMeta('color', 'red');
$product->setMeta('count', 10);
$product->setMeta('visible', false);
$product->setMeta('note', null);
$product->setMeta('tags', ['vip', 'sale']);
$product->setMeta('price', '120.50', type: 'decimal');
```

`'10'` رشته می‌ماند و به عدد تبدیل نمی‌شود. `1.5` به‌صورت float پذیرفته نمی‌شود؛ decimal را رشته بدهید. `false` با `0` یکی نیست.

## ذخیره چند مقدار

`setMetas` یک تراکنش است. کلید جدید نسخه‌دار نمی‌شود و نوع از روی مقدار PHP حدس زده می‌شود. decimal و روشن کردن تاریخچه را با `setMeta` جدا بنویسید.

```php
$product->setMetas([
    'color' => 'blue',
    'visible' => true,
    'count' => 10,
]);
```

اگر یکی از مقدارها نامعتبر باشد، هیچ‌کدام از همان دسته ذخیره نمی‌شود.

## خواندن یک مقدار

```php
$product->getMeta('color');                 // blue
$product->getMeta('unknown', 'fallback');   // fallback
$product->hasMeta('note');                  // true، حتی اگر مقدار null باشد
$product->hasMeta('missing');               // false
```

نبود کلید، `null` صریح، رشته خالی، `false`، و حذف نسخه‌دار پنج وضعیت جدا هستند.

## خواندن چند مقدار از همان مدل

کلید غایب و tombstone در خروجی نیستند. `null` صریح می‌ماند. آرایه خالی هیچ کوئری نمی‌زند.

```php
$product->getMetas(['color', 'count', 'note', 'missing']);
// ['color' => 'blue', 'count' => 10, 'note' => null]

$product->getMetas();
// همه کلیدهای موجود جاری، به ترتیب نام کلید
```

## فهرست، صفحه‌بندی، بدون N+1

```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** @var LengthAwarePaginator $page */
$page = Product::query()
    ->where('status', 'active')
    ->withMeta(['color', 'price'])
    ->paginate(20);

foreach ($page->items() as $product) {
    $product->getMeta('color');
    $product->getMeta('price');
}
```

برای ۱۰۰ مدل ترکیبی، کل `withMeta` حداکثر ۳ کوئری است: مدل‌ها، ردیف‌های مادر، آخرین نسخه. `paginate` یک count جدا دارد. تاریخچه کامل بارگذاری نمی‌شود.

اگر مدل‌ها را از قبل دارید:

```php
use Karnoweb\Meta\Facades\Meta;

Meta::load($products, ['color', 'price']);
$product->refreshMeta();
```

`refreshMeta` بعد از عوض شدن ساعت یا بعد از نوشتن، snapshot را تازه می‌کند. بارگذاری بیش از ۵۰۰ شناسه در چند کوئری شکسته می‌شود، نه یک کوئری به‌ازای هر مدل.

## جستجو

`orderBy` روی خود متادیتا API ندارد.

| کار | نمونه |
| --- | --- |
| برابری | `Product::query()->whereMeta('color', '=', 'red')` |
| عدد | `Product::query()->whereMeta('count', '>=', 10)` |
| عضویت | `Product::query()->whereMetaIn('count', [10, 20])` |
| بازه | `Product::query()->whereMetaBetween('count', 10, 20)` |
| الگو | `Product::query()->whereMetaLike('color', '%red%')` |
| وجود | `Product::query()->whereHasMeta('color')` |
| نبود | `Product::query()->whereDoesntHaveMeta('color')` |
| null صریح | `Product::query()->whereMetaNull('note')` |
| غیر null | `Product::query()->whereMetaNotNull('note')` |
| JSON | `Product::query()->whereMetaJsonContains('tags', 'vip')` |
| در یک تاریخ | `Product::query()->whereMetaAt('price', '=', '100.00', '2026-06-01 00:00:00', type: 'decimal')` |

`!=` کلید غایب را داخل نتیجه نمی‌آورد. فقط `int` و `decimal` و `date` و `datetime` مقایسه ترتیبی دارند. `whereMetaIn([])` هیچ مدلی برنمی‌گرداند.

`orWhereMeta` را گروه‌بندی کنید:

```php
Product::query()
    ->where('status', 'active')
    ->where(function ($query): void {
        $query->whereMeta('color', '=', 'red')
            ->orWhereMeta('color', '=', 'blue');
    })
    ->whereMeta('count', '>=', 10);
```

عضو سطح اول آرایه JSON در SQLite و MySQL و PostgreSQL پیدا می‌شود. ساختار تودرتو در SQLite خطا می‌دهد تا نتیجه خالی جعلی نسازد. در MySQL و PostgreSQL با `whereJsonContains` جستجو می‌شود.

برابری رشته به مقدار دقیق وابسته است و برای ایندکس از hash همان رشته استفاده می‌کند، بعد خود مقدار هم چک می‌شود. `LIKE` به collation دیتابیس وابسته است: در SQLite برای حروف ASCII معمولاً به بزرگی و کوچکی حساس نیست. این را برای MySQL و PostgreSQL یکسان فرض نکنید.

## نسخه‌بندی

```php
$product->setMeta('price', '100.00', versioned: true, type: 'decimal');
$product->setMeta('price', '120.00', type: 'decimal');
$product->setMetaAt('price', '150.00', '2027-01-01 00:00:00', type: 'decimal');

$product->getMeta('price');
$product->getMetaAt('price', '2026-06-01 00:00:00');
$product->metaHistory('price');
$product->metaHistoryPage('price', 50);
```

نوشتن بعدی بدون فلگ، حالت نسخه‌دار را نگه می‌دارد. `versioned: false` روی کلید نسخه‌دار خطا می‌دهد و داده را عوض نمی‌کند. نسخه آینده تا رسیدن `effective_at` در `getMeta` و `whereMeta` دیده نمی‌شود. scheduler لازم نیست. دو نسخه با زمان اثر یکسان، `id` بزرگ‌تر را جاری می‌کنند.

`getMetaAt` بازسازی با داده‌های امروز است، نه گزارش حسابرسی غیرقابل تغییر. `effective_at` زمان اعتبار است و `created_at` زمان ثبت.

`metaHistory` همه نسخه‌ها را، از جمله نشان حذف، به ترتیب `effective_at` و `id` می‌دهد. `metaHistoryPage` همان ترتیب را صفحه‌بندی می‌کند و فقط branch همان مدل را می‌بیند.

## حذف

```php
$product->deleteMeta('color');
Meta::purge($product);
Meta::purgeMany($products);
```

`deleteMeta` روی کلید عادی ردیف را پاک می‌کند. روی کلید نسخه‌دار یک tombstone می‌سازد و تاریخچه را نگه می‌دارد.

`purge` ردیف‌های مادر و تاریخچه همان مدل را در branch جاری و connection همان مدل، داخل یک تراکنش، پاک می‌کند. اگر مدل‌ها connection مختلف داشته باشند، هر connection جدا commit می‌شود. حذف نرم مدل متادیتا را نگه می‌دارد. `forceDelete` فقط وقتی `meta.purge_on_force_delete` برابر `true` باشد پاکسازی می‌کند. پیش‌فرض خاموش است.

`Product::query()->where(...)->delete()` رویداد مدل را اجرا نمی‌کند و purge نمی‌شود.

این بسته اجازه کاربر را چک نمی‌کند. اینکه چه کسی حق دیدن محصول را دارد با اپلیکیشن میزبان است.

## Facade

```php
use Karnoweb\Meta\Facades\Meta;

Meta::set($product, 'color', 'blue');
Meta::setMany($product, ['color' => 'green', 'visible' => true]);
Meta::get($product, 'color');
Meta::getMany($product, ['color', 'visible']);
Meta::load($products, ['color', 'price']);
Meta::historyPage($product, 'price', 50);
```

Trait و Facade هر دو به یک سرویس می‌رسند.

## Branch

پیش‌فرض خاموش است و همه ردیف‌ها `branch_key` خالی دارند. برای روشن کردن، کلاس invokable بگذارید تا `config:cache` خراب نشود. closure در config نگذارید.

```php
namespace App\Meta;

class BranchResolver
{
    public function __invoke(): string
    {
        return (string) auth()->user()->branch_id;
    }
}
```

```php
'branch' => [
    'enabled' => true,
    'resolver' => App\Meta\BranchResolver::class,
],
```

کلید branch دقیق و حساس به حروف است. `Branch-A` و `branch-a` دو branch هستند. فاصله ابتدا و انتها، و شکلی که NFC نباشد، رد می‌شود و بی‌صدا عوض نمی‌شود. اگر resolver نباشد یا کلید خالی بدهد، عملیات قطع می‌شود و به branch عمومی برنمی‌گردد. عوض شدن branch در یک worker، snapshot قبلی را برنمی‌گرداند.

## نوع داده و محدودیت

| نوع | ورودی | خروجی |
| --- | --- | --- |
| string | رشته تا ۵۱۲ کاراکتر | همان رشته |
| int | int | int |
| decimal | رشته یا int | رشته با ۱۰ رقم اعشار |
| bool | bool | bool |
| date | `Y-m-d` | نیمه‌شب UTC |
| datetime | زمان مطلق | UTC |
| json | آرایه | آرایه |
| null | null | null |

`0.1` و `0.10` هر دو `0.1000000000` هستند. رقم اعشار یازدهم و بیش از ۲۰ رقم صحیح خطا می‌دهند، گرد نمی‌شوند. مقایسه decimal از یک کلید متنی با طول ثابت استفاده می‌کند تا SQLite مقدار را به float تقریب نزند. مقدار `value_decimal` روی MySQL و PostgreSQL همان `decimal(30,10)` است. اگر متن canonical موجود نباشد، خواندن خطا می‌دهد و دقت ازدست‌رفته را بازسازی نمی‌کند.

سقف کلید ۱۲۰، رشته ۵۱۲ کاراکتر، branch ۶۴، و JSON به‌صورت بایت است. config بزرگ‌تر از عرض ستون رد می‌شود و ستون را گشاد نمی‌کند.

رشته طولانی فارسی یا ایموجی نزدیک ۵۱۲ کاراکتر در ایندکس btree ذخیره نمی‌شود. برابری با hash و سپس خود مقدار چک می‌شود تا برخورد hash نتیجه اضافه نسازد.

## خطاهای رایج

- مدل ذخیره نشده: اول `save` کنید. بسته این کار را نمی‌کند.
- float برای پول: رشته decimal بدهید.
- JSON تودرتو روی SQLite: `MetaQueryException`.
- خاموش کردن تاریخچه کلید نسخه‌دار: `MetaVersionException`.
- branch روشن بدون resolver: `MetaException`.

جزئیات در [docs/errors.md](docs/errors.md).

## چهار مسیر کامل

### رنگ و وضعیت

```php
$product->setMeta('color', 'red');
$product->setMeta('note', null);
$product->setMetas(['color' => 'blue', 'visible' => true, 'count' => 10]);

$product->getMeta('color'); // blue
$product->getMeta('unknown', 'fallback'); // fallback
$product->hasMeta('note'); // true
$product->getMetas(['color', 'count', 'note', 'missing']);
// ['color' => 'blue', 'count' => 10, 'note' => null]
```

### فیلتر

چهار محصول: شمارش ۵ قرمز، ۱۰ قرمز، ۲۰ آبی، ۳۰ قرمز.

```php
Product::query()->whereMeta('count', '>=', 10)->count(); // 3
Product::query()
    ->whereMeta('count', '>=', 10)
    ->whereMeta('color', '=', 'red')
    ->count(); // 2
```

تست: `tests/Feature/ReadmeGuideTest.php`.

### قیمت نسخه‌دار

ساعت نمونه `2026-10-14` است. ثبت‌ها با زمان اثر مشخص انجام شده‌اند.

```php
$product->setMeta('price', '100.00', versioned: true, type: 'decimal'); // اثر: 2026-01-01
$product->setMeta('price', '120.00', type: 'decimal');                    // اثر: 2026-06-01
$product->setMetaAt('price', '150.00', '2027-01-01 00:00:00', type: 'decimal');

$product->getMeta('price'); // 120.0000000000
$product->getMetaAt('price', '2026-03-01 00:00:00'); // 100.0000000000
```

`whereMeta('price', '=', '100.00', type: 'decimal')` این مدل را برنمی‌گرداند. `whereMetaAt` در `2026-03-01` برمی‌گرداند. بعد از `2027-01-01` و `refreshMeta()` مقدار `150.0000000000` است. تست: `tests/Feature/ReadmeGuideTest.php`.

### فهرست ۱۰۰ محصول

`tests/Scenarios/Scenario20EagerLoadTest.php`: صد محصول، `withMeta(['color', 'price'])` حداکثر ۳ کوئری، خواندن بعدی صفر کوئری، رنگ اول `c1`، قیمت آخر `109.0000000000`.

## تست و کارایی

```bash
vendor/bin/phpunit
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --testsuite Scenarios
vendor/bin/phpunit --testsuite Integration
php bench/benchmark.php
```

هم‌زمانی بدون `META_INTEGRATION=1` و درایور `mysql` یا `pgsql` skip می‌شود. SQLite قفل ردیف را اثبات نمی‌کند. عددهای بنچمارک قبلی، قبل از ستون‌های hash و decimal متنی گرفته شده‌اند و برای اسکیمای فعلی معتبر نیستند تا بنچمارک دوباره اجرا شود.

بقیه سندها: [docs/README.md](docs/README.md).
