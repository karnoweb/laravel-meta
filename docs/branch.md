# Branch

پیش‌فرض `meta.branch.enabled` برابر `false` است. همه ردیف‌ها با `branch_key=''` نوشته و خوانده می‌شوند.

## روشن کردن

Resolver باید کلاس قابل ساخت از container باشد و شیء برگشتی invokable. رشته غیرخالی تا ۶۴ کاراکتر برگرداند. closure داخل config نگذارید؛ `config:cache` آن را نگه نمی‌دارد.

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
// config/meta.php
return [
    'branch' => [
        'enabled' => true,
        'resolver' => App\Meta\BranchResolver::class,
    ],
];
```

اگر branch روشن باشد و resolver نباشد، کلاس ساخته نشود، invokable نباشد، یا کلید خالی/بلند برگرداند، عملیات با `MetaException` متوقف می‌شود. به branch عمومی برنمی‌گردد.

پکیج به `current_club_id` یا سیستم شعبه میزبان وصل نیست. هر بار از container کلاس را می‌سازد. در worker، resolver باید branch همان job را برگرداند. کش بارگذاری‌شده با عوض شدن کلید branch باطل می‌شود.

## دامنه

کلید branch دقیق است. `Branch-A` و `branch-a` یکی نیستند. روی MySQL ستون با `utf8mb4_0900_bin` ذخیره می‌شود تا collation پیش‌فرض حروف را یکی نگیرد. فاصله ابتدا یا انتها، و متنی که NFC نباشد، رد می‌شود و بی‌صدا اصلاح نمی‌شود. PostgreSQL به‌صورت پیش‌فرض به حروف حساس است. گذر SQLite این رفتار MySQL را ثابت نمی‌کند؛ همان تست در job مربوط به MySQL اجرا می‌شود.

فیلتر branch روی نوشتن، خواندن، `getMetas`، تاریخچه، حذف، eager load، جستجو و purge اعمال می‌شود. حذف در یک branch ردیف branch دیگر را پاک نمی‌کند.

## پاکسازی

`Meta::purge($model)` و `Meta::purgeMany($models)` فقط branch جاری و connection مدل را پاک می‌کنند. اول نسخه‌ها با query builder حذف می‌شوند، بعد rootها. این حذف سراسری بین branchها نیست.

`deleteMeta` روی کلید نسخه‌دار tombstone است و purge نیست.

پکیج تعیین نمی‌کند کدام کاربر اجازه دیدن مدل را دارد. جداسازی branch جایگزین authorization نیست.
