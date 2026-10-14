# نصب

## چه چیزی نصب می‌شود؟

پکیج دو جدول می‌سازد و Trait و Facade را در دسترس می‌گذارد. config پیش‌فرض branch را خاموش نگه می‌دارد. برای شروع کار، انتشار فایل لازم نیست.

## دستورها

```bash
composer require karnoweb/laravel-meta
php artisan migrate
```

سپس Trait را به مدل ذخیره‌شده اضافه کنید:

```php
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Meta\Traits\HasMeta;

class Product extends Model
{
    use HasMeta;
}

$product->setMeta('color', 'red');
$product->getMeta('color'); // red
```

`setMeta` بلافاصله می‌نویسد. بعد از آن `save()` مدل لازم نیست.

## چرا publish لازم نیست؟

`MetaServiceProvider` در `boot` مسیر migration داخل پکیج را با `loadMigrationsFrom` بار می‌کند. Artisan همان فایل را می‌بیند. config هم با `mergeConfigFrom` ادغام می‌شود. فقط اگر می‌خواهید مقدارها را در اپلیکیشن override کنید:

```bash
php artisan vendor:publish --tag=meta-config
```

این دستور migration را کپی نمی‌کند. تگ دیگری برای migration وجود ندارد.

## نام ثابت migration

`database/migrations/2020_01_01_090000_create_meta_tables.php`

این نام بعد از انتشار عوض نمی‌شود. تغییر ستون روی همین فایل، برای نصب‌هایی که migration را اجرا کرده‌اند، دوباره اجرا نمی‌شود. تغییر schema بعد از release باید migration جدید با نام ثابت بعدی باشد، نه ویرایش فایل منتشرشده.

## config:cache

`config/meta.php` closure ندارد. `resolver` باید نام کلاس باشد تا `php artisan config:cache` خراب نشود. نمونه در [branch.md](branch.md) است.

## نیازمندی‌ها

- PHP `^8.3`
- Laravel `^13.0` (`illuminate/database` و `illuminate/support`)

Laravel 11 و 12 در این release تست نشده‌اند و در `composer.json` مجاز نیستند.
