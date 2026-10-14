# نسخه‌بندی

پیش‌فرض `versioned: false` است. به‌روزرسانی کلید عادی همان root را عوض می‌کند و هیچ ردیفی در `meta_versions` نمی‌سازد.

## روشن کردن

```php
$product->setMeta('price', '100.00', versioned: true, type: 'decimal');
$product->setMeta('price', '110.00', type: 'decimal');
$product->setMetaAt('price', '150.00', '2027-01-01 00:00:00', type: 'decimal');
```

نوشتن بعدی بدون فلگ، mode را به ارث می‌برد. `versioned: false` روی کلید نسخه‌دار `MetaVersionException` می‌دهد و root و تاریخچه را عوض نمی‌کند.

مقدار جاری نسخه‌دار روی root نیست. آخرین نسخه با `effective_at <= now` و `is_deleted = 0`، به ترتیب `effective_at DESC, id DESC`، مقدار جاری است.

## سه نقطه زمانی

```php
$product->setMetaAt('price', '100.00', '2027-01-01 00:00:00', type: 'decimal');
$product->setMetaAt('price', '120.00', '2027-01-10 00:00:00', type: 'decimal');
$product->setMetaAt('price', '150.00', '2027-01-20 00:00:00', type: 'decimal');

$product->getMetaAt('price', '2027-01-05 00:00:00'); // 100.0000000000
$product->getMetaAt('price', '2027-01-15 00:00:00'); // 120.0000000000
$product->getMetaAt('price', '2027-01-21 00:00:00'); // 150.0000000000
```

اگر دو نسخه `effective_at` یکسان داشته باشند، `id` بزرگ‌تر برنده است.

## آینده

نسخه با تاریخ آینده ذخیره می‌شود و تا رسیدن آن زمان در `getMeta` و `whereMeta` دیده نمی‌شود. scheduler یا job لازم نیست. با جلو رفتن ساعت، همان ردیف مقدار جاری می‌شود.

## گذشته و ارتقا

کلید عادی تاریخچه ندارد. `setMetaAt` در گذشته، قبل از ارتقا، خطا می‌دهد و تاریخچه جعلی نمی‌سازد.

`setMeta('status', 'active', versioned: true)` مقدار فعلی را به‌عنوان اولین نسخه در همان لحظه ارتقا کپی می‌کند و سپس مقدار جدید را می‌نویسد. اگر هر دو `effective_at` یکی باشد، id بزرگ‌تر مقدار جاری است.

`getMetaAt` روی کلید عادی `MetaVersionException` می‌دهد. کلید نسخه‌داری که در آن لحظه نسخه‌ای ندارد default را برمی‌گرداند، نه خطا.

## حذف

`deleteMeta` روی کلید عادی root را پاک می‌کند. بار دوم `false` است.

روی کلید نسخه‌دار یک tombstone با `is_deleted=1` اضافه می‌شود. `getMeta` از آن به بعد default است، `hasMeta` برابر false است، و `metaHistory` نسخه‌های قبلی و نشان حذف را به ترتیب `effective_at ASC, id ASC` برمی‌گرداند.

نسخه جدید بعد از tombstone کلید را دوباره موجود می‌کند. تا قبل از `effective_at` آن نسخه، کلید غایب می‌ماند.

`metaHistoryPage($key, $perPage = 50)` همان ردیف‌ها را صفحه‌بندی می‌کند. ترتیب `effective_at` و سپس `id` است و فقط تاریخچه branch جاری را می‌بیند. `metaHistory` همچنان همه را یکجا برمی‌گرداند. ردیف نسخه از طریق Eloquent قابل update و delete نیست.

## audit

`getMetaAt` بازسازی مقدار با دانش امروز است: نسخه‌ای که الان در جدول است و زمان اثرش گذشته. این bitemporal نیست. اگر بعداً یک نسخه backdated اضافه شود، خواندن همان تاریخ گذشته نتیجه را عوض می‌کند.
