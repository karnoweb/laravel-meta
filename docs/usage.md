# استفاده

## Trait

```php
$product->setMeta('color', 'blue');
$product->setMeta('stock_label', '10');
$product->setMeta('visible', false);
$product->setMetas([
    'color' => 'green',
    'visible' => true,
]);

$product->getMeta('color');
$product->getMeta('missing', 'fallback');
$product->hasMeta('visible');
$product->deleteMeta('color');
```

`setMeta` و `setMetas` و `deleteMeta` همان لحظه روی connection مدل می‌نویسند. ذخیره خود مدل جداست. مدل ذخیره‌نشده (`exists === false` یا کلید خالی) `UnsavedModelException` می‌دهد و پکیج `save()` را صدا نمی‌زند.

`hasMeta` برای مقدار `false` و `null` صریح true است. برای کلید غایب و tombstone false است.

`setMetas` یک تراکنش است. کلیدها به ترتیب نام پردازش می‌شوند. کلید موجود mode خودش را نگه می‌دارد. کلید جدید نسخه‌دار نمی‌شود و نوع از روی مقدار PHP حدس زده می‌شود. decimal را با `setMeta(..., type: 'decimal')` بنویسید، نه داخل `setMetas`.

## Facade

```php
use Karnoweb\Meta\Facades\Meta;

Meta::set($product, 'color', 'blue');
Meta::get($product, 'color');
Meta::setMany($product, ['color' => 'green', 'visible' => true]);
Meta::delete($product, 'color');
Meta::load($products, ['color', 'price']);
Meta::purge($product);
```

Trait و Facade هر دو به `MetaManager` و از آنجا به `MetaService` می‌رسند.

## چند مقدار و null

```php
$product->getMetas(['color', 'count', 'note', 'missing']);
$product->getMetas();
$product->metaHistoryPage('price', 50);
```

`getMetas` کلید غایب و tombstone را حذف می‌کند و `null` صریح را نگه می‌دارد. آرایه خالی صفر کوئری است. `getMetas()` همه کلیدهای موجود را به ترتیب نام برمی‌گرداند. `metaHistoryPage` همان ترتیب `metaHistory` را صفحه‌بندی می‌کند.

| وضعیت | `getMeta` | `hasMeta` |
| --- | --- | --- |
| کلید هرگز نوشته نشده | default | false |
| مقدار `null` صریح | `null` | true |
| رشته خالی | `''` | true |
| `false` | `false` | true |
| tombstone نسخه‌دار | default | false |
| حذف فیزیکی کلید عادی | default | false |

## UUID و ULID

کلید مدل به رشته تبدیل و در `metable_id` ذخیره می‌شود. حداکثر ۶۴ کاراکتر است و کوتاه نمی‌شود. morph class حداکثر ۱۵۰ کاراکتر است. `Relation::enforceMorphMap` رعایت می‌شود؛ جستجو alias نقشه را هم به کلاس برمی‌گرداند.

## withMeta و صفحه‌بندی

```php
$products = Product::query()
    ->where('status', 'active')
    ->whereMeta('color', '=', 'red')
    ->withMeta(['color', 'price'])
    ->paginate(20);
```

`whereMeta` با `whereExists` کار می‌کند و سطر مدل را تکثیر نمی‌کند. بعد از گرفتن صفحه، `withMeta` متادیتای همان مدل‌ها را در حافظه می‌گذارد. `getMeta` روی همان کلیدها کوئری تازه نمی‌زند تا `refreshMeta()` یا یک نوشتن.

اگر فقط بخشی از کلیدها را بارگذاری کنید، فقط همان کلیدها در snapshot معتبرند. کلید بارگذاری‌نشده از دیتابیس خوانده می‌شود.

`getMeta` رابطه `metas` را lazy-load نمی‌کند. در strict mode لاراول هم برای خواندن یک کلید خطای lazy-load نمی‌دهد.

## allowlist

```php
class Product extends Model
{
    use HasMeta;

    protected array $metaKeys = ['color', 'visible'];
}
```

کلید خارج از این فهرست رد می‌شود. بدون این property همه کلیدهای معتبر مجازند. کلید خالی، بلندتر از ۱۲۰ کاراکتر، یا دارای کاراکتر کنترل رد می‌شود.

## رابطه

`$product->metas()` ردیف‌های root همان connection و branch را برمی‌گرداند. مقدار نسخه‌دار روی خود root خالی است؛ مقدار جاری در آخرین نسخه است. مسیر خواندن مقدار نسخه‌دار `getMeta` / `withMeta` است، نه `with('metas')`.
