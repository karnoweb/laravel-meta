# سناریو ۱۷ — جستجوی JSON

## پیش‌شرط‌ها

دو محصول.

## مراحل

1. `tags` یکی `['vip','sale']` و دیگری `['free']`.
2. `whereMetaJsonContains('tags', 'vip')`.
3. یک شیء تودرتو را هم جستجو کنید.

## ورودی

آرایه PHP.

## نتیجه مورد انتظار

جستجوی `vip` فقط محصول اول است. fragment تودرتو در SQLite `MetaQueryException` می‌دهد. در درایورهای دیگر همان محصول برمی‌گردد.

## فایل تست

`tests/Scenarios/Scenario17JsonSearchTest.php`
