# سناریو ۱۳ — bool و null و کلید غایب

## پیش‌شرط‌ها

چهار محصول.

## مراحل

1. مقدارها را `false`، `0`، `null`، و بدون کلید بگذارید.
2. `whereMeta`، `whereMetaNull`، `whereHasMeta` و `whereDoesntHaveMeta` را جدا اجرا کنید.

## ورودی

bool، int، null، و نبود نوشتن.

## نتیجه مورد انتظار

هر فیلتر فقط شناسه مجاز خودش را برمی‌گرداند. `false` با `0` یکی نیست. null صریح با کلید غایب یکی نیست.

## فایل تست

`tests/Scenarios/Scenario13BoolNullMissingTest.php`
