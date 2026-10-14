# فاز ۲ — جستجوی نوع‌دار، Eager Loading و عملکرد در مقیاس بالا

> **این پرامپت را پس از اتمام و سبزشدن تمام تست‌های فاز ۱ اجرا کن.**
> هدف، تکمیل همان `karnoweb/laravel-meta` است؛ **پکیج جدید یا API ناسازگار نساز**. کدهای واقعی، تست‌ها، مستندات و اصلاحات Migration پیش‌از-release را در مخزن اعمال کن.

## ۱. مأموریت

تو مسئول جستجوی دقیق متادیتا در Eloquent، بارگذاری گروهی و بهینه‌سازی SQL هستی. ابتدا سورس فاز ۱، migration، `MetaService`, `MetaValue`, `Meta`, `MetaVersion`, `HasMeta` و تست‌ها را بخوان. سپس قابلیت‌های این سند را با ساده‌ترین طراحی قابل اتکا پیاده کن.

اصول غیرقابل تغییر از فاز ۱:

- `metas` برای متادیتای غیرنسخه‌دار یک رکورد/کلید دارد؛ `meta_versions` فقط برای نسخه‌دار استفاده می‌شود.
- `versioned` بر مبنای اولین ثبت تعیین می‌شود و تغییرهای بعدی mode را به ارث می‌برند.
- برای نسخه‌دار، زمان اعتبار از `effective_at` و جدیدترین رکورد معتبر با ترتیب `(effective_at DESC, id DESC)` تعیین می‌شود. tombstone یعنی **نبود مقدار**، نه fallback به نسخه قبل.
- هیچ snapshot کش‌شده‌ای که پس از رسیدن زمان تغییر آینده منسوخ شود، منبع حقیقت نیست. زمان‌بندی باید بدون Cron معتبر بماند.
- `tenant_key` در حالت پیش‌فرض `''` است و در حالت Tenant فعال هیچ Query حق حذف فیلتر آن را ندارد.
- نام ثابت مایگریشن همان `database/migrations/2020_01_01_090000_create_meta_tables.php` باقی بماند؛ تا قبل از اولین release مجاز هستی ایندکس‌های همان فایل را اصلاح کنی. **بعد از انتشار عمومی، تغییر schema باید در migration جدید با نام نسخه‌دار ثابت انجام شود، نه ویرایش migration منتشرشده.**

## ۲. خروجی قابل استفاده برای توسعه‌دهنده

همه مثال‌های زیر **باید دقیقاً اجرا شوند**. پیاده‌سازی scopeهای Eloquent با نام‌های متداول و کوتاه و بدون نیاز به Builder مستقل موازی باشد.

```php
use App\Models\Product;

// برابر و مقایسه نوع‌دار
Product::whereMeta('color', '=', 'red')->get();
Product::whereMeta('count', '>=', 10)->paginate(20);
Product::whereMeta('price', '>', '120.50', type: 'decimal')->get();
Product::whereMeta('visible', '=', false)->get();
Product::whereMeta('start_at', '<=', now())->get();
Product::whereMeta('birthday', '=', '2026-01-05', type: 'date')->get();

// مجموعه، بازه و وجود
Product::whereMetaIn('status', ['active', 'pending'])->get();
Product::whereMetaBetween('count', 10, 40)->get();
Product::whereHasMeta('color')->get();
Product::whereDoesntHaveMeta('color')->get();
Product::whereMetaNull('note')->get();
Product::whereMetaNotNull('note')->get();

// رشته و JSON
Product::whereMetaLike('title', '%laravel%')->get();
Product::whereMetaJsonContains('tags', 'featured')->get();

// نسخه‌دار در یک تاریخ مشخص
Product::whereMetaAt('price', '>=', '200.00', '2027-02-01 12:00:00', type: 'decimal')->get();

// بارگذاری گروهی با کلیدهای محدود
$products = Product::withMeta(['color', 'price'])->limit(100)->get();
foreach ($products as $product) {
    $color = $product->getMeta('color');
    $price = $product->getMeta('price');
}
```

**مهم:** امضای scopeهایی را که `type` دارند طوری پیاده کن که استفاده از named argument در مثال‌های بالا واقعاً معتبر باشد؛ PHP و Eloquent را با تست واقعی بررسی کن. اگر امضای دقیق مثال‌ها با قابلیت Laravel conflict داشت، API را حداقلی و یکپارچه اصلاح و همه مثال‌ها و تست‌ها را هم‌زمان به‌روزرسانی کن؛ هیچ مثال شکسته‌ای باقی نگذار.

## ۳. قرارداد جستجوی دقیق روی انواع داده

هدف این است که نتیجه جستجو مطابق نوع واقعی مقدار باشد، نه بر اساس متن سریال‌شده، Cast SQL پراشتباه یا رفتار تصادفی Collation.

| داده ثبت‌شده | شرط جستجو | رفتار الزامی |
|---|---|---|
| `10` از نوع `int` | `whereMeta('x', '=', 10)` | Match |
| `'10'` از نوع `string` | `whereMeta('x', '=', 10)` | **No match** |
| `true` از نوع `bool` | `whereMeta('x', '=', 1)` | **No match** |
| `false` از نوع `bool` | `whereMeta('x', '=', false)` | Match |
| `'10.20'` از نوع `decimal` | `whereMeta('x', '=', '10.2', type: 'decimal')` | Match بعد از normalize دقیق |
| `10` از نوع `int` | decimal `10.00` | **No match**؛ در نسخه اول نوع‌ها عمداً strict هستند |
| `null` صریح | `whereMetaNull('x')` | Match |
| کلید وجود ندارد | `whereMetaNull('x')` | No match |
| کلید وجود ندارد | `whereDoesntHaveMeta('x')` | Match |
| `''` از نوع `string` | `whereMeta('x', '=', '')` | Match، ولی با null مساوی نیست |
| نسخه آینده بدون نسخه فعلی | `whereHasMeta('x')` در زمان حال | No match |
| نسخه tombstone به‌عنوان آخرین نسخه | `whereHasMeta('x')` | No match |

توضیحات دقیق:

1. `whereMeta($key, $operator, $value, ?string $type = null)` فقط operatorهای `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=` را بپذیرد؛ لیست مجاز ثابت باشد، هیچ SQL operator دلخواه/Raw عبور داده نشود.
2. نوع خودکار از PHP مقدار ورودی تشخیص داده شود؛ رشته عددی خودکار به عدد تبدیل نشود. برای Decimal رشته‌ای، نوع `decimal` صریح لازم است.
3. مقایسه `int`, `decimal`, `date`, `datetime` با ستون‌های **type-specific** انجام شود، نه با `CAST(value_text AS ...)` روی کل جدول. `bool` فقط `=` و `!=`؛ `json` مقایسه مقادیر ساده ندارد و فقط API اختصاصی JSON دارد؛ `null` از scope اختصاصی استفاده کند.
4. `!=` صرفاً در دامنه رکوردهای **موجود و هم‌نوع** اعمال شود؛ کلید غایب یا نوع متفاوت را به‌عنوان «نابرابر» Match نکن. این قرارداد را واضح مستند کن.
5. `whereMetaIn` باید ورودی‌های mixed-type مانند `[false, 0, '0']` را به OR گروه‌بندی‌شده با سه نوع مجزا تبدیل کند و فقط رکوردهای همان نوع را بگیرد. آرایه خالی نتیجه صفر برگرداند، نه کل جدول.
6. `whereMetaBetween` فقط برای typeهای قابل مقایسه (int, decimal, date, datetime)؛ دو کران باید نوع سازگار یکسان داشته باشند. بازه معکوس باید نتیجه صفر یا خطای مشخص داشته باشد؛ رفتار را یکسان و مستند کن.
7. `whereMetaLike` فقط روی `value_type=string` و `value_string` مجاز باشد. Wildcardهای `%` و `_` و Escape مطابق DB و Bind Parameter ایمن باشد؛ درباره حساسیت به بزرگی حروف وابسته به collation هر DB توضیح بده؛ نتیجه یکسان بین DBها را بی‌دلیل ادعا نکن.
8. `whereMetaJsonContains` فقط روی `value_type=json` و ستون `value_json` با API native Laravel whereJsonContains، درایورهایی که پشتیبانی می‌شوند؛ از SQL دست‌ساز ناامن پرهیز کن. Arrays/objects کاربر باید JSON-serializable باشند و Null در JSON با NULL متادیتا فرق دارد.
9. `whereMetaNull` باید `value_type=null` صریح را بشناسد؛ `whereMetaNotNull` کلیدهای موجود غیرnull را برگرداند؛ `whereDoesntHaveMeta` نبود مقدار **معتبر در زمان حال** را بررسی کند.
10. تمام Valueها Bind Parameter باشند. نام key هرگز مستقیم در Raw SQL تزریق نشود؛ کلید از ورودی معتبرسازی‌شده و bound comparison عبور کند.
11. در رشته‌های فارسی/یونیکد و ایموجی، مقایسه بر اساس collation/encoding دیتابیس معتبر باشد؛ از PHP `strtolower` اجباری و خراب‌کردن محتوا اجتناب کن.

## ۴. الگوریتم Query برای متادیتای عادی و نسخه‌دار

پرس‌وجوی اصلی باید بتواند **هر دو نوع کلید** را یکجا پیدا کند. الگوی ترجیحی:

- روی مدل اصلی از `whereExists` یا یک `EXISTS` هم‌بسته با جدول `metas` استفاده کن تا با داشتن چند Metadata برای هر مدل، سطرهای تکراری محصول تولید نشود.
- مدل صاحب را با `metable_type = $model->getMorphClass()`، `metable_id = qualified primary key` (مقایسه درست int/UUID/ULID) و `tenant_key = resolved tenant key` محدود کن.
- برای `versioned=false`: مقدار را مستقیماً از `metas.value_*` نوع‌دار بخوان.
- برای `versioned=true`: `meta_versions` را محدود کن به **شناسه آخرین نسخه دارای `effective_at <= asOf`**؛ باید ترتیب `effective_at DESC, id DESC LIMIT 1` رعایت شود. فقط مقدار **همان آخرین نسخه** در Predicate فیلتر بررسی شود؛ از «هر نسخه تاریخی که شرط را داشته» استفاده نکن.
- Tombstone آخرین نسخه هرگز Match نشود، حتی اگر نسخه قبلی مقدار مطلوب را داشته باشد.
- خواندن نسخه در آینده نیازمند Job برای تغییر `metas` نباشد. زمان پیش‌فرض `now` و برای `whereMetaAt` پارامتر explicit `asOf` باشد؛ هیچ global/static `travelTo()` تغییرپذیر در کل request تعریف نکن.
- برای شرایط متعدد روی متادیتا از scopeهای Eloquent composable بهره بگیر تا `whereMeta('a',..)->whereMeta('b',..)` معنی منطقی AND و `orWhereMeta()` معنی OR داشته باشد. گروه‌بندی درست `OR` نسبت به فیلترهای دیگر (tenant, model conditions) الزامی است.
- `whereMetaAt` برای **کلیدهای نسخه‌دار** است؛ کلیدهای عادی در پرس‌وجوی تاریخ‌گذشته Match نشوند و تاریخچه‌ای جعل نشود. API آن باید یک زمان صریح دریافت کند، نه تغییر حالت query سراسری.
- نسخه‌های یک کلید با یک `effective_at` یکسان فقط به‌وسیله `id DESC` اولویت می‌گیرند و تحت DBهای مختلف نتیجه ثابت دارند.
- اگر ناگزیر از Raw SQL محدود برای «آخرین نسخه» هستی، فقط قطعات identifier ثابت داخلی داشته باش؛ مقدارها تماماً bind شوند و SQL با SQLite / MySQL 8+ / PostgreSQL 12+ تست شود.

## ۵. بارگذاری گروهی و حذف N+1

`withMeta()` را روی Trait یا Scope Eloquent بساز، نه با `getMeta` پشت سر هم برای هر مدل.

- `Product::withMeta(['color', 'price'])->get()` باید در حالت معمول برای ۱۰۰ محصول حداکثر **۳ Query** تولید کند: محصولات، rootهای متادیتای مرتبط و آخرین نسخه‌های معتبر برای کلیدهای نسخه‌دار (اگر نسخه‌داری وجود نداشت Query سوم می‌تواند وجود نداشته باشد).
- این بارگذاری نباید **تمام نسخه‌های تاریخی** را بخواند. یک رکورد آخر معتبر به‌ازای هر `meta_id` برای زمان مشخص انتخاب شود؛ از subquery محدود یا رابطه one-of-many قابل اتکا استفاده کن و Query آن را تست کن.
- تنها Keyهای درخواست‌شده بارگذاری شوند. `withMeta()` بدون آرگومان تمام کلیدهای فعلی را بارگذاری کند، اما نه تاریخچه کامل را.
- اگر `metas()` به شکل خام eager-loaded شد و برای نسخه‌دار مقدار current مشخص نبود، `getMeta()` باید منطقی رفتار کند؛ برای جلوگیری از N+1 در اسناد استفاده از `withMeta()` را توصیه کن. استفاده از `with('metas')` **به‌تنهایی** را به‌عنوان بهینه برای نسخه‌دار معرفی نکن.
- `Meta::load($models, ['color', 'price'])` را برای Collection مدل‌های از قبل دریافت‌شده پیاده کن؛ تعداد Query مستقل از تعداد مدل‌ها بماند. اگر collection خالی بود صفر Query.
- کش محلی مقادیر، اگر پیاده می‌شود فقط روی همان instance/Collection و با clear شدن پس از `setMeta`, `setMetaAt`, `deleteMeta` باشد. **کش سراسری/Redis در این فاز لازم نیست**.
- نسخه‌های آینده و گذر زمان: eager-loaded data تصویر لحظه Load است. اگر یک Model object از قبل بارگذاری‌شده بعد از زمان تغییر مجدداً استفاده شد، خواننده باید `refreshMeta()` داشته باشد تا snapshot را به‌روزرسانی کند. این محدودیت را روشن مستند کن؛ برای request جدید بدون cron مقدار معتبر خودکار انتخاب می‌شود.
- `refreshMeta()` یک متد واقعی باشد؛ رابطه‌ها/کش داخلی را با درنظرگرفتن Tenant و کلیدهای بارگذاری‌شده مجدداً تازه کند. تغییر Tenant در یک worker طولانی‌عمر نباید cache tenant قبلی را نمایش دهد؛ در صورت تفاوت tenant context کل کش instance را invalidate کن.
- eager loading فقط روی ownerهای انتخاب‌شده انجام شود؛ از Query به تمام جدول نسخه‌ها پرهیز کن.

## ۶. Indexها و DB compatibility

قبل از release، به Migration ثابت فاز ۱ ایندکس‌های عملی اضافه کن یا در صورت نیاز آن را اصلاح کن. هدف کم‌کردن هزینه `EXISTS` و جستجوی دقیق نوع‌دار است؛ ایندکس‌های بی‌مصرف و حجیم ایجاد نکن.

اولویت ایندکس‌ها:

1. unique root: `(tenant_key, metable_type, metable_id, key)`.
2. owner lookup: `(tenant_key, metable_type, metable_id)`.
3. نمونه typed string search: `(tenant_key, key, value_type, value_string)`.
4. typed int search: `(tenant_key, key, value_type, value_integer)`.
5. typed decimal search: `(tenant_key, key, value_type, value_decimal)`.
6. typed date/datetime و boolean، بسته به Query Plan و هزینه write؛ برای همه index مستقل نساز مگر براساس تست.
7. history lookup: `(meta_id, effective_at, id)`.

توجه:
- در MySQL محدودیت طول ایندکس utf8mb4 را با طول ستون‌های فاز ۱ بررسی کن؛ در PostgreSQL و SQLite نیز schema باید migrate شود.
- اگر برای دیتابیسی ایندکس خاصی عملی نبود، معادل صحیح آن را با migration/driver handling روشن پیاده کن؛ صرفاً حذف خاموش ایندکس قابل قبول نیست.
- `metable_type` را از `getMorphClass()` بگیر، نه `::class` hardcoded؛ تست Morph Map حتماً داشته باش.
- Queryها باید در MySQL 8.0+، PostgreSQL 12+ و SQLite نسخه دارای JSON functions طبق قابلیت هر درایور سازگار باشند. اختلاف‌های جزئی Collation و JSON را در مستندات ثبت کن.
- هیچ index جداگانه‌ای برای جستجوی تاریخچه نسخه‌های قدیمی بدون دلیل واقعی اضافه نکن؛ زمان فیلتر و انتخاب آخرین نسخه را با EXPLAIN بررسی کن.

## ۷. فایل‌های مورد انتظار این فاز

برحسب نیاز واقعی، این فایل‌ها را بساز/تکمیل کن (با حداقل کلاس‌های لازم):

```text
src/Traits/HasMeta.php                     # scopeهای Eloquent و withMeta
src/Models/Meta.php                        # روابط و index-aware query helpers
src/Models/MetaVersion.php                 # active version resolution
src/Services/MetaQuery.php                 # ساخت predicateهای امن/نوع‌دار
src/Services/MetaService.php               # load و refresh و هماهنگی API
src/MetaManager.php                        # facade API
src/Support/MetaValue.php                  # reuse type detection/normalization
src/Exceptions/MetaQueryException.php
config/meta.php
database/migrations/2020_01_01_090000_create_meta_tables.php

tests/Feature/MetaSearchTest.php
tests/Feature/MetaVersionSearchTest.php
tests/Feature/MetaEagerLoadingTest.php
tests/Feature/MetaTenantSearchTest.php
tests/Feature/MetaSqlSafetyTest.php
tests/Integration/DatabaseCompatibilityTest.php

docs/search.md
docs/performance.md
README.md
```

**ممنوع:** اضافه‌کردن `meta_current` یا Redis mandatory، Event/Job/HTTP API غیرضروری، cache سراسری بی‌invalidating، `N+1` مخفی در scopeها، افزودن نام‌گذاری عظیم برای هر عمل کوچک.

## ۸. ماتریس تست اجباری برای جستجو

داده پایه بساز: چند Product با مقادیر متادیتا، نسخه آینده، نسخه تاریخی، tombstone، null صریح و دو tenant. برای هر ماتریس **تست با Assert روی IDهای برگردانده‌شده** بنویس؛ فقط بررسی `count()` کافی نیست.

### ۸.۱. انواع داده

- `string`: equality، inequality، `LIKE`، UTF-8 فارسی/انگلیسی/ایموجی، حروف بزرگ/کوچک بر اساس collation.
- `int`: `=`, `!=`, `>`, `>=`, `<`, `<=`, `IN`, `BETWEEN` با مقادیر منفی، صفر و عدد بزرگ.
- `decimal`: مقایسه دقیق `0.1`, `0.10`, `10.20`, `100.0000000001`، عدد منفی و حدود precision؛ نمایش/جستجو بدون خطای float.
- `bool`: false و true در برابر اعداد صفر/یک و رشته `'0'`, `'1'`.
- `date`: `Y-m-d`، مرز بازه، مقایسه قبل/بعد.
- `datetime`: UTC، offset زمانی، مرز یک میکروثانیه، قبل/بعد و `DateTimeInterface`.
- `json`: وجود عضو آرایه و عنصر nested پشتیبانی‌شده، object/array معتبر، نبود عضو، تفاوت null متادیتا با null داخلی JSON.
- `null`: صرفاً رکورد صریح null، تفاوت `whereHasMeta`, `whereDoesntHaveMeta`, `whereMetaNull`, `whereMetaNotNull` و کلید حذف‌شده.
- Type mismatch: `'100'` در برابر `100`، `false` در برابر `0`, `int` در برابر `decimal`، و type تغییرکرده در نسخه‌های تاریخی.
- `IN` mixed types و array خالی؛ `BETWEEN` با نوع‌های ناسازگار؛ operator ناشناخته و key injection.

### ۸.۲. نسخه‌بندی

- نسخه فعلی `price=200` در برابر نسخه تاریخی `price=100`؛ `whereMeta('price','=',100)` نباید Model را match کند.
- نسخه آینده `price=300` تا قبل از تاریخ شروع آن نتیجه `200` باشد و بعد از تاریخ `300`.
- یک کلید با فقط نسخه آینده در `whereHasMeta` فعلاً وجود ندارد.
- tombstone اکنون، نسخه گذشته و scheduled restore در آینده؛ جستجو در سه زمان درست باشد.
- tie on `effective_at` با `id`؛ نتیجه deterministic.
- `whereMetaAt` باید فقط Versioned keys را ببیند؛ unversioned keys به تاریخچه جعلی نرسند.
- ترکیب دو شرط متادیتا روی یک مدل، شرط mixed versioned/unversioned و pagination بدون row duplication.

### ۸.۳. امنیت و Tenant

- وجود key یکسان روی چند مدل با `metable_type` متفاوت و id مساوی: عدم نشت.
- tenant A و tenant B برای یک owner/key با دو مقدار متفاوت: get/search/exists/delete/load همگی محصور در tenant صحیح باشند.
- resolver نامعتبر/خالی وقتی tenant فعال: exception؛ هرگز Query گسترده بدون tenant رخ ندهد.
- SQL injection در `key`, `operator`, `LIKE`، JSON path و type نامعتبر.

### ۸.۴. عملکرد و شمارش Query

- ۱۰۰ مدل با متادیتای عادی: `withMeta(['color'])` حداکثر ۲ Query و خواندن متوالی بدون Query جدید.
- ۱۰۰ مدل ترکیبی نسخه‌دار و عادی: `withMeta(['color','price'])` حداکثر ۳ Query، بدون بارگذاری همه تاریخچه.
- `Meta::load($collection,['color','price'])` نیز حداکثر ۲ Query پس از بارگذاری مدل‌ها برای متادیتا.
- شرط `whereMeta` با چند متادیتا باعث duplication سطرها و pagination غیرصحیح نشود.
- `refreshMeta` پس از ثبت تغییر و پس از گذر از زمان اعتبار آینده مقدار تازه بدهد.
- یک Model instance در Worker طولانی اگر Tenant عوض شد، داده tenant پیشین را بازنمی‌گرداند.
- Query Plan مربوط به نوع‌های متداول با `EXPLAIN` روی DBهای فعال ذخیره و بررسی شود؛ پوشش `EXPLAIN` می‌تواند Integration باشد، نه تست شکننده‌ای که هر DB مجبور باشد متن پلان یکسان دهد.

## ۹. ابزار تست و بنچمارک واقعی

ابزار جداگانه قابل اجرای CLI در `bench/` بساز که:

- حجم‌های `10000`, `100000`, و با درخواست صریح `1000000` رکورد متادیتا تولید کند؛ به‌طور پیش‌فرض بیش از ۱۰هزار رکورد seed نکند.
- نوشتن bulk، خواندن تکی، `whereMeta` string/int/decimal/datetime، جستجوی versioned، بارگذاری ۱۰۰ و ۱۰۰۰ مدل را اندازه بگیرد.
- warm-up، چند تکرار و گزارش `median`, `p95`, تعداد Query و peak memory داشته باشد.
- نوع DB، نسخه DB، حجم داده، تعداد نسخه‌ها، ایندکس‌ها و پارامترهای تست را در گزارش ثبت کند؛ هیچ ادعای ساختگی درباره میلی‌ثانیه یا «صد میلیون رکورد بدون مشکل» ننویسد.
- benchmarks را از تست‌های Functional جدا نگه دارد، چون latency روی CI متغیر است؛ فقط قرارداد **تعداد Query** در تست‌های Functional الزام سخت باشد.
- مهم‌ترین مقایسه: کارایی کلید عادی با `versioned=false` نباید هزینه join/subquery به جدول `meta_versions` را در مسیر خواندن یک کلید شناخته‌شده عادی تحمیل کند. اگر مسیر general search هر دو mode را پوشش می‌دهد، هزینه آن را گزارش کن و امکان Query shortcut نوع عادی را بدون پیچیده‌کردن API ارزیابی کن.

## ۱۰. معیار اتمام فاز ۲

- همه APIهای جستجو و `withMeta` واقعاً فعال باشند؛ Exampleهای بالا هم در تست اجرا شوند.
- تست انواع داده و mismatch دقیقاً با IDهای صحیح پاس شوند.
- روی SQLite تست‌های کامل؛ روی MySQL/PostgreSQL تست‌های integration در صورت دسترسی اجرا و status واقعی گزارش شود.
- indexهای لازم در Migration ثابت ساخته شده باشند؛ migration منتشرنشده باید با rollback و rerun سالم باشد.
- ترکیب Tenant + version + search هیچ مسیر بدون tenant و هیچ read از نسخه تاریخی به‌جای نسخه فعلی نداشته باشد.
- مستندات `docs/search.md` و `docs/performance.md` فارسی با مثال کامل و محدودیت‌های JSON/collation نوشته شوند.
- از پیچیدگی آینده‌نگرانه مانند Redis global cache، table partitioning خودکار و Elasticsearch اجباری پرهیز شود.

## ۱۱. شکل گزارش عامل

در پایان، تغییر فایل‌ها، همه دستورهای واقعی اجراشده، تعداد تست/Assertion، خروجی benchmark واقعی (در صورت اجرا)، SQL/EXPLAIN نمونه در DBهای قابل دسترس، و موارد حل‌نشده را گزارش کن. اگر به خاطر محدودیت محیطی تست MySQL/PostgreSQL اجرا نشد، واضح بنویس **تست نشده است**، نه «سازگار تأیید شد».

**هیچ انتقالی به فاز ۳ پیش از سبزشدن تست‌های SQLite و برطرف‌شدن N+1 اصلی انجام نده.**
