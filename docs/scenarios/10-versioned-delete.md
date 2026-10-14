# سناریو ۱۰ — حذف نسخه‌دار

## پیش‌شرط‌ها

`price=100` نسخه‌دار است.

## مراحل

1. `deleteMeta('price')` را صدا بزنید.
2. `getMeta`، `hasMeta` و `metaHistory` را بخوانید.

## ورودی

کلید نسخه‌دار.

## نتیجه مورد انتظار

`getMeta` مقدار default را می‌دهد. `hasMeta` برابر false است. تاریخچه، نسخه قبلی و tombstone را هنوز دارد. root حذف فیزیکی نشده است.

## فایل تست

`tests/Scenarios/Scenario10VersionedDeleteTest.php`
