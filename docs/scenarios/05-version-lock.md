# سناریو ۰۵ — جلوگیری از خاموش کردن تاریخچه

## پیش‌شرط‌ها

`price` از قبل نسخه‌دار است.

## مراحل

1. `setMeta('price', ..., versioned: false)` را صدا بزنید.

## ورودی

همان کلید با فلگ خاموش.

## نتیجه مورد انتظار

`MetaVersionException`. root و تعداد history عوض نمی‌شود.

## فایل تست

`tests/Scenarios/Scenario05VersionLockTest.php`
