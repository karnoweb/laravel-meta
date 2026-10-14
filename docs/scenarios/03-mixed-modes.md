# سناریو ۰۳ — ترکیب mode روی یک محصول

## پیش‌شرط‌ها

یک محصول.

## مراحل

1. `color=red` عادی.
2. `price=100.00` نسخه‌دار.
3. هر کلید را سه بار دیگر عوض کنید.

## ورودی

رشته برای رنگ. decimal برای قیمت.

## نتیجه مورد انتظار

`color`: یک root و صفر history. `price`: یک root و چهار version (ثبت اولیه به‌علاوه سه تغییر).

## فایل تست

`tests/Scenarios/Scenario03MixedModesTest.php`
