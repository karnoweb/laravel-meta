# فاز ۱ — پیاده‌سازی کامل هسته Laravel Meta

> **این فایل یک پرامپت اجرایی برای Cursor / Codex است. آن را در ریشه مخزن پکیج قرار بده و به عامل کدنویسی بده.**
> فاز ۱ باید واقعاً فایل‌ها و کلاس‌های قابل اجرا را بسازد، نه صرفاً طراحی، شبه‌کد یا نمونه ناقص. فازهای ۲ و ۳ بر قرارداد همین سند بنا می‌شوند.

## ۱. نقش و خروجی مورد انتظار

تو یک توسعه‌دهنده ارشد Laravel 13، طراح پکیج Composer و متخصص Eloquent، SQL و هم‌زمانی تراکنش‌ها هستی. پکیج جدید **`karnoweb/laravel-meta`** را با Namespace ثابت **`Karnoweb\Meta`** پیاده‌سازی کن؛ مطابق سبک کلی پکیج‌های `karnoweb/laravel-inventory` و `karnoweb/laravel-accounting` از نظر ساختار `src/Models`, `src/Services`, `src/Traits`, `src/Facades`, ServiceProvider، تست‌های PHPUnit/Testbench و مستندات فارسی. کد آن پروژه‌ها را بی‌دلیل کپی نکن؛ این پکیج مستقل است.

کاربر باید فقط پکیج را نصب کند، `php artisan migrate` بزند، Trait را به مدل اضافه کند و شروع به استفاده کند. **انتشار config یا migration پیش‌نیاز نصب نیست.**

هنگام اجرا:
1. اگر مخزن کد دارد ابتدا آن را بررسی کن و بدون تخریب فایل‌های خارج از دامنه کار ادامه بده؛ اگر خالی است آن را کامل بساز.
2. هر فایل را با **تمام کد واقعی** ایجاد کن. `TODO`, placeholder, pseudo-code، متد خالی و دستور «خودتان تکمیل کنید» ممنوع است.
3. برای هر قابلیت، تست اجرایی در همان فاز بنویس؛ اگر ابزارهای PHP/Composer در محیط فعال نیست، این محدودیت را صریح گزارش کن و وانمود نکن تستی اجرا شده است.
4. بعد از پیاده‌سازی، خروجی را با مسیر فایل‌های تغییرکرده، تست‌های اجراشده، نتیجه واقعی و نکات باقیمانده تحویل بده.

## ۲. الزامات پایه و قراردادهای غیرقابل تغییر

- پشتیبانی رسمی اولیه: **PHP `^8.3` و Laravel `^13.0`**. وابستگی‌های Composer را به مؤلفه‌های موردنیاز Illuminate محدود کن. تست با `orchestra/testbench:^11` و PHPUnit سازگار با Laravel 13؛ وابستگی اضافه صرفاً در صورت ضرورت واقعی.
- کدنویسی PSR-12، تایپ‌های صریح، `declare(strict_types=1)` در کد جدید، بدون static state تغییرپذیر که بین درخواست‌ها نشت کند.
- نام‌ها کوتاه و متداول: `Meta`, `MetaVersion`, `HasMeta`, `MetaManager`, `MetaService`, `MetaType`, `MetaException`, `MetaServiceProvider`, `Meta` Facade؛ از نام‌های طولانی و معماری چندلایه بی‌دلیل پرهیز کن.
- نسخه‌بندی **به‌صورت پیش‌فرض خاموش** است: ویژگی معمولی فقط **یک رکورد** در `metas` دارد و با هر تغییر همان رکورد آپدیت می‌شود؛ هیچ رکوردی در `meta_versions` تولید نمی‌کند.
- هنگام نخستین ثبت یک ویژگی می‌توان `versioned: true` داد؛ از آن لحظه **این کلید روی همان مدل نسخه‌دار باقی می‌ماند** و تغییر بعدی حتی بدون تکرار فلگ هم نسخه جدید تولید می‌کند. `versioned: false` روی کلید نسخه‌دار خطای دامنه بدهد؛ تبدیل به غیرنسخه‌دار با حذف خاموش تاریخچه ممنوع. برای کلید جدید، `null` یا `false` یعنی غیرنسخه‌دار.
- ثبت تاریخ‌دار با `setMetaAt()` به‌وضوح نسخه‌بندی را انتخاب می‌کند؛ روی کلید جدید نسخه‌دار می‌سازد و روی کلید غیرنسخه‌دار موجود باید ابتدا «ارتقا» را **در همان تراکنش** انجام دهد. مقدار قبلی در لحظه ارتقا به‌عنوان نسخه اولیه با تاریخ ارتقا حفظ شود. اگر تاریخ ارائه‌شده قبل از ارتقا است، تاریخچه ساختگی ایجاد نکن؛ برای وضعیت نامشخص گذشته خطای واضح یا قرارداد مستند اجرا کن و تست بده.
- ثبت متادیتا صرفاً روی مدل **ذخیره‌شده** مجاز است؛ اگر مدل هنوز Primary Key ندارد خطای صریح `UnsavedModelException` بده. پنهانی `save()` نکن و با جادوی Eloquent روی propertyهای اصلی تداخل ایجاد نکن.
- از Eloquent polymorphic با `getMorphClass()` استفاده کن. شناسه صاحب متادیتا را در `varchar(64)` ذخیره کن تا کلیدهای عددی، UUID و ULID کار کنند. برای کلیدهای طولانی‌تر خطای مشخص بده، بی‌صدا truncate نکن.
- زمان‌ها در دیتابیس UTC و با دقت میکروثانیه (در DBهای پشتیبانی‌کننده) باشند. ورودی `DateTimeInterface` و رشته معتبر را قبول کن؛ رشته‌های مبهم نسبی (`+2 weeks`) در هسته پیش‌فرض پذیرفته نشوند. زمان مقایسه با Clock لاراول/Carbon قابل Freeze در تست باشد.
- مدل‌های مربوط به امور مالی، انبار، سازمان، سفارش، شعبه یا Tenant را داخل پکیج تعریف نکن. هیچ وابستگی به پکیج‌های دیگر Karnoweb یا Bindingهایی مانند `current_club_id` نداشته باش.

## ۳. ساختار فایل‌های مورد انتظار

ساختار حداقلی زیر را بساز. اگر در حین پیاده‌سازی **نیاز واقعی** به Helper کوچک جدید بود، مجاز است اما ایجاد لایه‌های بی‌مصرف ممنوع:

```text
composer.json
README.md
LICENSE
CHANGELOG.md
phpunit.xml
config/meta.php
database/migrations/2020_01_01_090000_create_meta_tables.php
src/MetaServiceProvider.php
src/MetaManager.php
src/Facades/Meta.php
src/Traits/HasMeta.php
src/Models/Meta.php
src/Models/MetaVersion.php
src/Services/MetaService.php
src/Support/MetaType.php
src/Support/MetaValue.php
src/Support/MetaClock.php
src/Exceptions/MetaException.php
src/Exceptions/UnsavedModelException.php
src/Exceptions/MetaTypeException.php
src/Exceptions/MetaVersionException.php
tests/TestCase.php
tests/Fixtures/Product.php
tests/Fixtures/User.php
tests/Feature/InstallationTest.php
tests/Feature/MetaCrudTest.php
tests/Feature/MetaVersionTest.php
tests/Feature/MetaTypesTest.php
docs/README.md
docs/usage.md
docs/rules.md
```

اگر کلاس `MetaClock` غیرضروری شد، منطق زمان را به یک سرویس قابل تست محدود کن؛ time-travel سراسری و تغییر state محیطی ممنوع است.

## ۴. نصب بدون Publish و Migration با نام ثابت

- `composer.json` شامل PSR-4، autoload-dev، لایسنس MIT، auto-discovery provider و alias اختیاری `Meta` باشد. `minimum-stability: dev` را اضافه نکن؛ شماره نسخه را به شکل دستی داخل `composer.json` قفل نکن.
- در `register()` از `mergeConfigFrom(__DIR__.'/../config/meta.php', 'meta')` و binding مناسب `MetaManager` استفاده کن.
- در `boot()` از **`loadMigrationsFrom(__DIR__.'/../database/migrations')`** استفاده کن؛ بنابراین صرفاً `php artisan migrate` کافی است.
- Migration حتماً فایل **`2020_01_01_090000_create_meta_tables.php`** باشد؛ اسم آن در هر build و release یکسان بماند، timestamp پویا ممنوع. از anonymous migration استفاده کن؛ `up()` و `down()` واقعی و مستقل.
- **Migration را در این نسخه publish نکن** تا فایل نصب‌شده دوباره در مسیر پروژه کپی نشود و خطر اجرای تکراری وجود نداشته باشد. تنها `config/meta.php` با tag اختیاری `meta-config` قابلیت انتشار داشته باشد، ولی منتشرنکردن آن هیچ قابلیتی را مختل نکند.
- `config:cache` و Composer package discovery کار کنند. مقدار پیش‌فرض‌های تودرتو به `mergeConfigFrom` سطح‌اول متکی نباشد؛ خواندن کانفیگ در نقاط کاربردی با fallback واضح انجام شود.
- اتصالات دیتابیس اختصاصی اختیاری باشند، اما نسخه اول ترجیحاً connection مدل میزبان را دنبال کند. اگر دو مدل از connection متفاوت استفاده می‌کنند، عملیات Meta همان connection صاحب مدل را بکار ببرد؛ با یک Transaction روی connection نامرتبط ادعای Atomicity نکن.

## ۵. Schema دقیق و ثابت

### جدول `metas` — یک رکورد به‌ازای هر مدل و کلید

- `id`: big integer افزایشی، Primary Key.
- `tenant_key`: `string(64)` **non-null** و با مقدار پیش‌فرض `''` در حالت بدون Tenant.
- `metable_type`: `string(150)` بر پایه `getMorphClass()`.
- `metable_id`: `string(64)`.
- `key`: `string(120)`.
- `versioned`: boolean پیش‌فرض `false`.
- `value_type`: `string(16)->nullable()`؛ مقدارهای معتبر: `null`, `string`, `int`, `decimal`, `bool`, `date`, `datetime`, `json`.
- `value_string`: `string(512)->nullable()`.
- `value_integer`: `bigInteger()->nullable()`.
- `value_decimal`: `decimal(30, 10)->nullable()`.
- `value_boolean`: `boolean()->nullable()`.
- `value_date`: `date()->nullable()`.
- `value_datetime`: `dateTime(..., 6)->nullable()`.
- `value_json`: `json()->nullable()`.
- `created_at`, `updated_at`.
- unique index ثابت برای `(tenant_key, metable_type, metable_id, key)`؛ **`tenant_key` را NULL نگذار** چون در MySQL/PostgreSQL یکتایی NULL رفتار مورد انتظار را تضمین نمی‌کند.
- ایندکس مناسب روی `(tenant_key, metable_type, metable_id)`؛ در فاز ۲ ایندکس‌های جستجوی نوع‌دار اضافه/بازبینی می‌شوند، تا آن زمان حداقل ایندکس درست داشته باش.

### جدول `meta_versions` — فقط برای کلیدهای نسخه‌دار

- `id`: big integer افزایشی و PK.
- `meta_id`: FK معتبر به `metas.id` با حذف cascade فقط هنگام حذف واقعی کلید/صاحب آن.
- `effective_at`: تاریخ شروع اعتبار UTC با دقت میکروثانیه.
- `is_deleted`: boolean پیش‌فرض `false` (نسخه حذف منطقی، نه برابر با مقدار `null`).
- همان ستون‌های نوع و مقدار جدول `metas`.
- `created_at` به‌عنوان زمان ثبت واقعی نسخه؛ `updated_at` درصورت نیاز ORM، ولی نسخه موجود **هرگز update نشود**.
- index ترکیبی `(meta_id, effective_at, id)`؛ ترتیب انتخاب نسخه: `effective_at DESC, id DESC`.
- **عدم نیاز به جدول سوم یا جدول cache فعلی**. برای ردیف‌های `versioned=true`، ستون‌های `value_*` در `metas` خالی هستند و تنها `meta_versions` منبع حقیقت است.

### قرارداد تاریخچه

- برای کلید عادی: `UPDATE metas` و **صفر INSERT** در `meta_versions`.
- برای کلید نسخه‌دار: ردیف مادر در `metas` ثابت می‌ماند و هر تغییر یک `INSERT` در `meta_versions` دارد.
- مقدار فعلی کلید نسخه‌دار، جدیدترین نسخه غیرحذفی از میان نسخه‌های با `effective_at <= now` است؛ **اما اگر آخرین نسخه tombstone (`is_deleted=1`) بود کلید در آن لحظه وجود ندارد**؛ به نسخه پیشین fallback نکن.
- نسخه‌های آینده قبل از زمان اعتبار دیده نمی‌شوند؛ خواندن آن‌ها با گذشت زمان **بدون cron، scheduler یا بازنویسی snapshot** درست می‌شود.
- `getMetaAt($key, $at)` فقط برای کلید نسخه‌دار معنی دارد. برای کلید عادی که تاریخچه ندارد، خطای واضح `MetaVersionException` بده؛ هرگز مقدار امروزی را به‌عنوان مقدار گذشته جعل نکن.
- Backdated write (نوشتن نسخه با زمان اعتبار در گذشته) مجاز است؛ `getMetaAt` وضعیت معتبرِ بازسازی‌شده با دانش امروز را می‌دهد، نه «در آن روز سیستم چه می‌دانست». این تفاوت را در مستندات ثبت کن.
- در `meta_versions` تاریخچه فقط الحاقی (append-only) است. حذف نسخه، ویرایش مستقیم نسخه، و پاک‌سازی بی‌سروصدا از API عمومی پکیج ممنوع. `deleteMeta` برای نسخه‌دار tombstone ثبت می‌کند؛ برای کلید عادی ردیف را حذف می‌کند. پاکسازی فیزیکی تاریخی خارج از API روزمره و فقط از عملیات نگهداری آگاهانه میزبان ممکن است.

## ۶. Type system (شناخت نوع داده)

`MetaType` و `MetaValue` را پیاده کن:

| ورودی | نوع ذخیره | خروجی `getMeta` | نکته |
|---|---|---|---|
| `null` | `null` | `null` | وجود کلید از مقدار null مستقل است |
| `string` | `string` | `string` | حداکثر ۵۱۲ کاراکتر، خطای واضح در بزرگ‌تر |
| `int` | `int` | `int` | محدوده signed BIGINT، بدون overflow |
| `float` | `decimal` | `string` ده‌دهی | تبدیل مشخص و مستند؛ استفاده از float برای پول توصیه نشود |
| decimal string با `type: 'decimal'` | `decimal` | `string` | حفظ مقدار دقیق تا ۱۰ رقم اعشار؛ reject در precision نامعتبر |
| `bool` | `bool` | `bool` | `true` با `1` یکسان نیست |
| `DateTimeInterface` | `datetime` | `CarbonImmutable` | normalization به UTC |
| string با `type: 'date'` | `date` | `CarbonImmutable` | قالب `Y-m-d`، بدون وابستگی timezone |
| `array` | `json` | `array` | JSON معتبر و پایدار؛ Objects پیش‌فرض مجاز نیستند |

- `type` اختیاری و برای انواع مبهم ضروری است. رشته `'100'` خودکار عدد نمی‌شود. `'false'` خودکار boolean نمی‌شود. `null` **ارزش** است، نه دستور حذف.
- Date/Datetime، precision و Decimal را با Validation سختگیرانه پیاده کن؛ مقدار نامعتبر نباید بی‌صدا تبدیل شود.
- فقط ستون متناسب با نوع مقدار را مقداردهی کن و بقیه را NULL کن. در تغییر نوع از string به int ستون قدیمی باید پاک شود.
- Object دلخواه PHP، `unserialize`, Class Injection، Model Eloquent به‌عنوان Value و Data URL نامحدود ممنوع‌اند.

## ۷. API عمومی که واقعاً باید پیاده شود

### Trait `Karnoweb\Meta\Traits\HasMeta`

```php
use Karnoweb\Meta\Traits\HasMeta;

class Product extends Model
{
    use HasMeta;
}

$product->setMeta('color', 'blue');
$product->setMeta('stock_label', '10');             // string; not integer
$product->setMeta('visible', false);               // bool
$product->setMeta('price', '120.5000000000', versioned: true, type: 'decimal');
$product->setMeta('price', '130.00', type: 'decimal'); // mode inherited: versioned
$product->setMetaAt('price', '150.00', '2027-01-01 00:00:00', type: 'decimal');
$product->setMetas(['color' => 'green', 'visible' => true]);

$product->getMeta('color');
$product->getMeta('missing', 'fallback');
$product->hasMeta('visible');              // true even when value is false or null
$product->getMetaAt('price', '2027-01-02 00:00:00');
$product->metaHistory('price');            // ordered versions, including deleted markers
$product->deleteMeta('color');
```

- امضای دقیق `setMeta(string $key, mixed $value, ?bool $versioned = null, ?string $type = null): static`.
- امضای دقیق `setMetaAt(string $key, mixed $value, DateTimeInterface|string $at, ?string $type = null): static`.
- `setMetas(array $values): static` کل batch را در یک تراکنش ثبت کند؛ mode کلید موجود را به ارث ببرد و برای کلیدهای جدید غیرنسخه‌دار باشد. برای ویژگی‌های دارای mode/type خاص از `setMeta` جداگانه استفاده می‌شود.
- `getMeta(string $key, mixed $default = null): mixed`, `hasMeta(string $key): bool`, `deleteMeta(string $key): bool`, `getMetaAt(string $key, DateTimeInterface|string $at, mixed $default = null): mixed`, `metaHistory(string $key): Collection`.
- `metas()` رابطه Eloquent به رکوردهای مادر `Meta` باشد؛ `Meta::versions()` رابطه نسخه‌ها باشد. وجود متادیتا به معنی تزریق جادویی پراپرتی‌ها در Eloquent نیست. هیچ تغییری در `getAttribute` / `setAttribute` و Castهای مدل میزبان نده.
- `setMeta` فوری روی دیتابیس ذخیره شود؛ نیازی به `save()` مجدد مدل نیست. این موضوع در README روشن باشد.

### Facade و Manager

```php
use Karnoweb\Meta\Facades\Meta;

Meta::set($product, 'color', 'blue');
Meta::get($product, 'color');
Meta::has($product, 'color');
Meta::delete($product, 'color');
Meta::setAt($product, 'price', '170.00', '2027-02-01 00:00:00', type: 'decimal');
Meta::history($product, 'price');
```

- `MetaManager` facade accessor واقعی باشد و به `MetaService` قابل تزریق تکیه کند؛ API مدل و facade به **یک منطق مشترک** برسند، نه کپی‌کاری.
- روی مدل امکان `protected array $metaKeys = ['color', 'visible']` برای allowlist اختیاری باشد؛ حالت پیش‌فرض همه کلیدها مجاز، ولی متدهای متادیتا keyهای خالی، بیش از ۱۲۰ کاراکتر، و حاوی کاراکترهای کنترل را رد کنند.

## ۸. هم‌زمانی و تراکنش

- نوشتن هر کلید در یک transaction روی connection مرتبط با مدل صاحب انجام شود.
- روی رکورد `metas` قفل `lockForUpdate()` بگیر. برای کلید اولیه که هنوز رکورد ندارد، از uniqueness در DB به همراه الگوی امن insert-if-absent / duplicate retry استفاده کن؛ race مربوط به دو درخواست هم‌زمان نباید دو root بسازد.
- عملیات `setMetas` را در یک تراکنش واحد اجرا کن؛ برای جلوگیری از deadlock، کلیدها را در یک ترتیب پایدار (مثلاً نام کلید) پردازش کن.
- هنگام تبدیل یک کلید از غیرنسخه‌دار به نسخه‌دار، مقدار قبل از تبدیل را به‌عنوان اولین نسخه با `effective_at` برابر با زمان تبدیل نگهدار و سپس نسخه جدید را بنویس؛ اگر زمان اثر نسخه دوم همان لحظه است، tie-breaker با ID بزرگ‌تر برنده می‌شود.
- آپدیت کلید عادی باید با عملیات row-level atomic انجام شود؛ اگر هم‌زمان دو درخواست مختلف ارسال شدند، در نهایت آخرین commit برنده است و رکورد تکراری وجود ندارد. برای کلید نسخه‌دار تمام تغییرها ثبت شوند؛ ترتیب انتخاب deterministic باشد.
- رویداد اجباری/Queue/Scheduler نساز؛ اضافه‌کردن Event در فاز بعد تنها با `afterCommit` و تست مجاز است.

## ۹. آماده‌سازی Multi-tenant بدون وابستگی به میزبان

در `config/meta.php` مقدارهای زیر را تعریف کن:

```php
return [
    'tenant' => [
        'enabled' => false,
        'resolver' => null, // FQCN::class, invokable class or container-resolvable callable identifier
    ],
    'max_key_length' => 120,
    'max_string_length' => 512,
    'max_json_bytes' => 1048576,
];
```

- وقتی Tenant غیرفعال است **همه عملیات** از `tenant_key=''` استفاده کنند.
- وقتی فعال شد، resolver باید یک کلید غیرخالی تا ۶۴ کاراکتر برگرداند؛ نبود/خرابی resolver **fail-closed** باشد، نه fallback به tenant عمومی.
- resolver را در هر عملیات از DI/Container فراخوانی کن؛ هیچ `app()->bound('current_tenant')` یا وابستگی به tenant package خاصی نگذار.
- لایه reading/writing حتی `history()` و `deleteMeta()` همیشه بر اساس Tenant فعال فیلتر شود. سازوکار جستجوی بین مدل‌ها در فاز ۲ همین invariant را رعایت خواهد کرد.
- در config فایل closure مستقیم نگذار تا `config:cache` کار کند. مستندات نمونه resolver مبتنی بر کلاس ارائه کنند.

## ۱۰. تست‌های الزامی این فاز

با PHPUnit + Testbench و SQLite in-memory این تست‌ها را **واقعاً پیاده کن و اجرا کن**:

1. `test_provider_auto_discovers_and_loads_fixed_migration_without_publish`
2. `test_normal_metadata_uses_one_row_and_zero_history_rows_after_many_updates`
3. `test_versioning_is_opt_in_and_inherited_on_subsequent_writes`
4. `test_explicit_disabling_of_versioned_key_is_rejected`
5. `test_promotion_preserves_original_value_in_history`
6. `test_future_version_becomes_current_when_time_advances_without_scheduler`
7. `test_as_of_uses_effective_at_and_id_tie_breaker`
8. `test_backdated_version_and_tombstone_semantics`
9. `test_delete_unversioned_is_physical_and_versioned_is_logical`
10. `test_missing_and_explicit_null_are_distinct`
11. `test_all_supported_value_types_round_trip_correctly`
12. `test_type_change_clears_old_value_columns`
13. `test_numeric_string_does_not_auto_cast`
14. `test_invalid_decimal_date_json_key_and_overflow_are_rejected`
15. `test_unsaved_owner_throws_without_creating_metadata`
16. `test_int_uuid_ulid_owner_ids_and_morph_map_are_supported`
17. `test_set_metas_is_atomic_and_rolls_back_on_error`
18. `test_tenant_resolution_is_fail_closed_and_isolates_two_tenants`
19. `test_facade_and_trait_share_same_behavior`
20. `test_config_cache_does_not_need_config_publication`

TestClock / Carbon freeze را برای تاریخ‌ها استفاده کن، نه `sleep()`؛ روی DB تراکنشی SQLite محدودیت‌های concurrency را دروغین تست نکن. تست هم‌زمانی واقعی برای MySQL/PostgreSQL در فاز ۳ نوشته می‌شود.

## ۱۱. مستندات و معیار تحویل فاز

- `README.md`: نصب با `composer require karnoweb/laravel-meta` و `php artisan migrate`؛ نمونه کامل Trait، Facade، انواع داده و نسخه‌بندی اختیاری.
- `docs/usage.md`: مثال‌های مرحله‌به‌مرحله؛ بدون نیاز به انتشار config/migration؛ شامل تفاوت `setMeta` فوری با Eloquent `save()`.
- `docs/rules.md`: سیاست null/missing/deleted، ارتقا به نسخه‌دار، تاریخ گذشته/آینده، UTC، Tenant و محدودیت دیتابیس.
- `CHANGELOG.md`, `LICENSE`, `phpunit.xml`, `composer.json` و autoload-dev واقعی ایجاد شوند.
- کار باید با `composer validate`, `composer dump-autoload`, test suite و syntax checks گذرانده شود؛ گزارش واقعی تعداد تست/Assertion بده.
- فایل مایگریشن باید **همان نام ثابت** را داشته باشد؛ از ساخت مایگریشن timestampدار دیگر بدون نیاز اجتناب کن.
- هر مسئله‌ای که در کد و تست حل نشده دقیق گزارش شود؛ از اعلام «Production Ready» قبل از فازهای ۲ و ۳ پرهیز کن.

## ۱۲. فرمت پاسخ نهایی عامل کدنویسی

1. فهرست فایل‌های ساخته‌شده/تغییرکرده با مسیر دقیق.
2. توضیح کوتاه تصمیم معماری `metas + meta_versions`.
3. نمونه اجرای صحیح `setMeta` عادی و نسخه‌دار، و `getMetaAt`.
4. دستورهای تست، تعداد تست/Assertion واقعی و موفق/ناموفق بودن هرکدام.
5. محدودیت‌ها یا موارد باقیمانده برای فاز ۲.

**مهم:** قبل از رفتن به فاز ۲، تمام معیارهای فاز ۱ باید پاس شوند؛ اگر تناقض اجرایی پیدا شد، همان‌جا آن را با کمترین تغییر در API عمومی حل و مستند کن.
