# karnoweb/laravel-meta

متادیتای نوع‌دار برای مدل‌های Eloquent. هر کلید یا یک مقدار جاری است، یا در صورت درخواست صریح، تاریخچه نسخه‌دار دارد. جستجو از روی ستون نوع‌دار انجام می‌شود و `withMeta` خواندن گروهی را در دو یا سه کوئری جمع می‌کند.

نسخه پیشنهادی تا سبز شدن هم‌زمانی MySQL 8 و PostgreSQL در CI: `0.1.0`. این مخزن هنوز روی MySQL و PostgreSQL در همین محیط اجرا نشده است.

## نصب

```bash
composer require karnoweb/laravel-meta
php artisan migrate
```

انتشار config و migration لازم نیست. فایل migration ثابت است:

`database/migrations/2020_01_01_090000_create_meta_tables.php`

## استفاده سریع

این نمونه همان مسیر `tests/Integration/DatabaseCompatibilityTest.php` است.

```php
use Karnoweb\Meta\Traits\HasMeta;

class Product extends Model
{
    use HasMeta;
}

$product->setMeta('color', 'قرمز');
$product->setMeta('count', 10);
$product->setMeta('price', '10.20', versioned: true, type: 'decimal');
$product->setMeta('price', '10.50', type: 'decimal');
$product->setMeta('tags', ['vip']);

Product::query()->whereMeta('color', '=', 'قرمز')->get();
Product::query()->whereMeta('count', '>=', 10)->get();
Product::query()->whereMeta('price', '=', '10.50', type: 'decimal')->get();
Product::query()->whereMetaJsonContains('tags', 'vip')->get();
Product::query()->withMeta(['color', 'price'])->paginate(20);
```

`setMeta` همان لحظه می‌نویسد. `save()` مدل برای متادیتا لازم نیست.

## مستندات

- [نصب](docs/installation.md)
- [استفاده](docs/usage.md)
- [نوع‌ها](docs/types.md)
- [نسخه‌بندی](docs/versioning.md)
- [جستجو](docs/search.md)
- [کارایی](docs/performance.md)
- [Tenant](docs/tenant.md)
- [قواعد](docs/rules.md)
- [خطاها](docs/errors.md)
- [تست](docs/testing.md)
- [سناریوها](docs/scenarios/01-basic-meta.md)

## مرز پکیج

پکیج مجوز کاربر را چک نمی‌کند. اینکه چه کسی حق دیدن یا ویرایش مدل را دارد با اپلیکیشن میزبان است. اگر tenant روشن باشد، خواندن و نوشتن و حذف و جستجو فقط همان tenant را می‌بینند.
