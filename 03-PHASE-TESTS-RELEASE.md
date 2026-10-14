# فاز ۳ — تست سناریویی، ایمن‌سازی، مستندسازی و آماده‌سازی انتشار

> **پرامپت پایانی برای Cursor / Codex.** ابتدا فازهای ۱ و ۲ و همه فایل‌های ساخته‌شده را بخوان؛ سپس پکیج واحد `karnoweb/laravel-meta` را کامل و قابل انتشار کن. صرفاً نوشتن گزارش یا لیست پیشنهادها کافی نیست: تست، رفع نقص، مستندات، CI و نمونه مصرف‌کننده را **واقعاً پیاده کن**.

## ۱. خروجی نهایی و اصل سادگی

معیار موفقیت: یک توسعه‌دهنده با سه کار شروع کند:

```bash
composer require karnoweb/laravel-meta
php artisan migrate
```

```php
use Karnoweb\Meta\Traits\HasMeta;

class Product extends \Illuminate\Database\Eloquent\Model
{
    use HasMeta;
}

$product = Product::findOrFail(1);
$product->setMeta('color', 'red');
$product->setMeta('rating', 4);
$product->setMeta('price', '150.00', versioned: true, type: 'decimal');
$product->setMetaAt('price', '200.00', '2027-01-01 00:00:00', type: 'decimal');

Product::whereMeta('rating', '>=', 4)->withMeta(['color', 'price'])->get();
```

هیچ انتشار دستی کانفیگ/مایگریشن، ایجاد Repository میزبان، Service registration، Cron یا Queue برای این سناریو لازم نباشد. قابلیت Tenant، JSON query، نسخه‌بندی، تاریخچه و Facade نیز در همان پکیج موجود و مستند باشند، نه پروژه دوم.

## ۲. قراردادهای قطعی که نباید در مرحله بازنویسی شوند

- ساختار `metas` و `meta_versions` و نام migration اصلی `2020_01_01_090000_create_meta_tables.php` ثابت.
- کلید عادی فقط یک رکورد در `metas`، بدون تاریخچه؛ کلید نسخه‌دار با پرچم اولیه `versioned: true` و تنها برای آن append به جدول `meta_versions`.
- تاریخ اعتبار (effective time) با زمان ثبت (recorded time) متفاوت است. `getMetaAt` **بازسازی وضعیت معتبر در زمان مشخص با دانش فعلی** است و ادعای سیستم حسابرسی دوبعدی (bitemporal audit) نمی‌کند.
- scheduled changes بدون job و بدون cache stale روی درخواست تازه درست عمل می‌کنند.
- Type-safe queries نوع String / Int / Decimal / Bool / Date / Datetime / Null / JSON را مستقل از هم و طبق قرارداد فاز ۲ بررسی می‌کنند.
- multi-tenant اختیاری، غیرفعال به‌صورت پیش‌فرض؛ وقتی فعال است fail-closed و بدون هرگونه وابستگی به سیستم شعبه/سازمان یا پکیج دیگر.
- مسیر معمول استفاده با Trait و Facade هر دو موجودند؛ هیچ override جادویی از Eloquent attributes لازم نیست.
- استفاده از classes/interfaces کوتاه و استاندارد؛ پیچیدگی غیرضروری را در مرحله انتشار حذف کن، نه اضافه.

## ۳. استاندارد سخت‌گیرانه تست: سناریوی چندمرحله‌ای + نتیجه دقیق

تست‌ها را علاوه بر Unit/Feature به شکل Scenario آماده کن. نام‌ها کوتاه و منظم، PHP namespace معتبر و مستندات فارسی برای **هر سناریو**.

```text
tests/Scenarios/ScenarioTestCase.php
tests/Scenarios/Scenario01BasicMetaTest.php
tests/Scenarios/Scenario02VersionOptInTest.php
...
docs/scenarios/01-basic-meta.md
docs/scenarios/02-version-opt-in.md
...
```

هر تست سناریویی دارای متد `test_complete_scenario()` یا نام واضح مشابه باشد، چند عملیات پشت سر هم انجام دهد و بعد از **هر مرحله** مقدار خروجی، ردیف‌های واقعی دیتابیس و تاریخچه را Assert کند. مستندات سناریو باید داده اولیه، عملیات گام‌به‌گام، نتیجه عددی واقعی، خطاهای مورد انتظار و Test class مربوط را ذکر کنند. ترجیحاً کامنت‌ها، نام‌ها و Assertها روشن و قابل خواندن باشند.

## ۴. حداقل ۲۴ سناریوی عملیاتی اجباری

سناریوهای زیر را با Test class و Markdown فارسی متناظر اجرا کن. در مثال‌ها تاریخ‌ها ثابت و UTC باشند تا تست deterministic شود.

| # | سناریو | مراحل و نتیجه دقیق |
|---|---|---|
| 01 | متادیتای معمولی | برای محصول A مقدار `color=red` ثبت کن؛ سپس `blue` و `green`. مقدار نهایی `green`، دقیقاً ۱ root و ۰ version. |
| 02 | نسخه‌بندی انتخابی | `price=100.00` را با `versioned:true` ثبت کن؛ سپس `110.00` بدون فلگ. دقیقاً ۱ root و ۲ version؛ مقدار جاری `110.00`. |
| 03 | ترکیب mode روی یک محصول | `color=red` عادی، `price=100.00` نسخه‌دار. پس از ۳ تغییر در هرکدام: `color` یک root و ۰ history، `price` یک root و ۴ history (ثبت اولیه + ۳ تغییر). |
| 04 | ارتقای عادی به نسخه‌دار | ابتدا `status=draft` عادی؛ ارتقا و تغییر به `active`. مقدار قبلی در نسخه اول و مقدار جدید در نسخه دوم، بدون گم‌شدن داده. |
| 05 | جلوگیری از خاموشی تصادفی تاریخچه | روی `price` نسخه‌دار، `versioned:false` صریح بده؛ Exception مشخص، بدون تغییر root یا history. |
| 06 | زمان آینده | در `2027-01-01` مقدار `100` و از `2027-02-01` مقدار `200`؛ در ۳۱ ژانویه `100`، در ۱ فوریه `200`، بدون scheduler و با clock freeze. |
| 07 | ویرایش گذشته | نسخه‌های `100`، `120`، `150` با ۳ تاریخ؛ نسخه backdated `110` وسط زمان. `getMetaAt` برای هر نقطه دقیقاً نسخه معتبر مربوط را بدهد. |
| 08 | دو نسخه هم‌زمان | دو نسخه یک کلید با `effective_at` برابر و ID متفاوت ثبت کن؛ نسخه دارای ID بزرگ‌تر در `getMeta` و `whereMeta` برنده باشد. |
| 09 | حذف عادی | مقدار null یا false را با نبود key اشتباه نکن؛ `deleteMeta` روی عادی root را حذف کند، دومین حذف false برگرداند. |
| 10 | حذف نسخه‌دار | بعد از `price=100` نسخه tombstone بساز؛ `getMeta` مقدار default دهد، `hasMeta=false`، ولی `metaHistory` نسخه‌های قبلی را نگه دارد. |
| 11 | بازگردانی پس از حذف | بعد از tombstone در زمان آینده نسخه `price=120` ثبت کن؛ قبل از بازگردانی وجود نداشته باشد، بعد از آن موجود باشد. |
| 12 | String در برابر Integer | روی مدل A `code='10'` و مدل B `code=10`؛ سرچ String فقط A و سرچ Int فقط B، حتی اگر کاربر عدد مشابه ببیند. |
| 13 | Bool / null / missing | چهار محصول با `false`, `0`, `null` و کلید غایب؛ هر فیلتر تنها IDهای مجاز خودش را بازگرداند. |
| 14 | Decimal دقیق | مقدارهای `0.10`, `0.20`, `10.20` و `100.0000000001` با type decimal؛ equality/range خروجی درست داشته باشد و rounding ناخواسته رخ ندهد. |
| 15 | Integer range | `count=-5,0,10,20,100`، بازه ۱۰ تا ۲۰ فقط دو شناسه مربوط را برگرداند، مرتب‌سازی و limit رفتار درست داشته باشند. |
| 16 | Date / Datetime | date `2027-01-05` و datetime با offset متفاوت؛ مقایسه بر مبنای روز/date و لحظه UTC، با مرز دقیق microseconds. |
| 17 | JSON search | آرایه `tags=['vip','sale']` در برابر `['free']`؛ contains `vip` فقط اولی. تست nested JSON با رفتار واقعی درایورهای پشتیبانی‌شده. |
| 18 | IN با انواع ترکیبی | کلید یکسان روی مدل‌های با مقادیر `false`, `0`, `'0'`, `null`; `whereMetaIn` گروه‌بندی types درست، با آرایه خالی نتیجه صفر. |
| 19 | فیلتر نسخه فعلی | قیمت گذشته `100` و فعلی `200`؛ `whereMeta(price=100)` نباید آن مدل را برگرداند؛ `whereMetaAt` تاریخ گذشته برگرداند. |
| 20 | بارگذاری گروهی | ۱۰۰ محصول با `color` و ۱۰۰ مقدار نسخه‌دار `price`: `withMeta` حداکثر ۳ Query، بدون per-model request؛ ID و مقدارهای همه صحیح. |
| 21 | ترکیب Query | شرط `status=active`، شرط Meta و pagination؛ تعداد واقعی و صفحه‌بندی صحیح، بدون Duplicate ناشی از JOIN. |
| 22 | جداسازی Tenant | tenant A مقدار `red` و tenant B مقدار `blue` برای همان owner/key؛ read/search/history/delete در A نباید B را تغییر یا آشکار کند. |
| 23 | Morph/UUID/ULID | مدل با id عددی، UUID و ULID و morph map اختصاصی؛ هر مدل فقط metadata خودش را ببیند. |
| 24 | Rollback چندعملیاتی | `setMetas` دو مقدار معتبر + یک مقدار نامعتبر؛ تراکنش rollback، هیچ بخشی از batch ثبت نشود. |

در صورت نیاز به جلوگیری از تقلب ساده تست، برای هرکدام از مقدارها از fixture واقعی استفاده کن، `assertDatabaseCount`, `assertDatabaseHas`, `assertSame` و assertions روی IDهای دقیق را باهم ترکیب کن. تست موفق که فقط `true` برمی‌گرداند کافی نیست.

## ۵. تست‌های مقاومت و حالت‌های مرزی

افزون بر سناریوهای ۲۴گانه:

### ۵.۱. خطا و اعتبارسنجی

- مدل unsaved، key خالی/بسیار بلند/شامل control chars، مقدار string بلندتر از ۵۱۲، JSON بسیار بزرگ‌تر از حد کانفیگ، Model/object غیرمجاز، date نامعتبر، decimal overflow، type نامعتبر و اپراتور نامعتبر.
- `null` صریح، حذف منطقی tombstone، نبود ردیف و مقدار empty string چهار حالت مستقل باشند.
- تغییر type در یک key نسخه‌دار: Query باید **فقط جدیدترین نسخه و نوع همان نسخه** را ملاک قرار دهد.
- `setMetas([])`, `whereMetaIn([])`, collection خالی، keyهای تکراری و خطای اتصال DB کنترل شوند.
- نصب با Laravel strict mode که lazy-loading ناخواسته را ممنوع کرده است، behavior مستند داشته باشد.

### ۵.۲. هم‌زمانی روی دیتابیس واقعی

یک suite به نام `Integration` یا `Concurrency` با تست MySQL 8+ و PostgreSQL 12+ بساز. **SQLite in-memory را برای اثبات row locking استفاده نکن.**

- دو process یا connection مستقل هم‌زمان یک key جدید ایجاد کنند: unique root همچنان **۱** باشد، هیچ Duplicate Key خطای مدیریت‌نشده در caller نماند.
- دو نویسنده روی key نسخه‌دار: هر دو version ثبت شود؛ اگر effective_at برابر شد ID تعیین‌کننده باشد.
- یک request نسخه‌بندی را روشن و هم‌زمان دیگری مقدار جدید ثبت کند: پس از Commit، حالت root و history سازگار بماند و هیچ تاریخچه‌ای بی‌صدا دور ریخته نشود.
- rollback در میانه ثبت نسخه روی هر دو DB، root/history را در حالت قبل نگه دارد.
- Deadlock یا lock timeout با Retry محدود و Backoff معقول در سطح Service مدیریت شود، فقط خطاهای قابل Retry و نه تمام Exceptionها؛ از loop بی‌نهایت اجتناب کن.
- اگر runner دیتابیس واقعی ندارد، تست‌ها را با شرط محیطی **skip صریح** کن؛ «passed» گزارش نده.

### ۵.۳. Tenant و امنیت

- Tenant فعال با resolver null یا مقدار خالی باید fail closed کند.
- Tenant A و B با owner ID و morph type یکسان: تمام عملیات create/read/search/eager load/history/delete صرفاً در context خودشان.
- عوض‌شدن resolver بین دو عملیات در worker ماندگار هیچ مقدار loadشده Tenant قبلی را افشا نکند.
- raw SQL injection از طریق key, string value, operator, datetime, JSON path و morph type اثری نداشته باشد.
- اسناد صریح بگویند **پکیج metadata authorization انجام نمی‌دهد**: کنترل اینکه «کدام کاربر حق دیدن/ویرایش محصول را دارد» بر عهده اپ میزبان است. با این حال جداسازی Tenant در queryهای پکیج الزامی است.

### ۵.۴. پاکسازی و حذف صاحب متادیتا

Polymorphic FK نمی‌تواند به همه جداول صاحبان متادیتا اشاره کند. رفتار روشن تعریف و پیاده کن:

- هنگام Soft Delete مدل، metadata به‌طور پیش‌فرض حفظ شود تا بازیابی ممکن باشد.
- امکان **opt-in** برای پاکسازی در Hard Delete/Force Delete از طریق Trait Event و یک تنظیم ساده فراهم کن؛ پیش‌فرض این گزینه **خاموش** باشد چون حذف مدل‌های موجود و داده‌های مرتبط ممکن است سیاست تجاری داشته باشد.
- پاکسازی وقتی opt-in است باید در scope tenant فعلی و در همان DB connection مدل انجام شود؛ تاریخچه نسخه‌ها به واسطه FK `onDelete('cascade')` پاک شود.
- تفاوت Eloquent `delete()` با bulk `where(...)->delete()` را شفاف بنویس: bulk delete رویدادهای مدل را اجرا نمی‌کند و auto-cleanup را تضمین نمی‌کند. راهنمای حذف/پاکسازی دسته‌ای از طریق متد صریح Service بده؛ پاکسازی cross-tenant عمومی **بدون اختیار روشن** ممنوع.
- اگر برای lifecycle یک API پیشنهاد می‌کنی، نام ساده `Meta::purge($model)` با semantics مستند و محدود به Tenant فعال انتخاب کن؛ `deleteMeta` نسخه‌دار باید همچنان فقط tombstone بسازد. `purge` حذف فیزیکی همه کلیدهای مالک است و نباید به‌طور ضمنی صدا زده شود.

## ۶. ماتریس اتصال و انتشار

### ۶.۱. تست ماتریس

| محیط | حداقل انتظار |
|---|---|
| PHP 8.3 + Laravel 13 | Unit/Feature/Scenario بر SQLite |
| PHP 8.4 + Laravel 13 | Unit/Feature/Scenario بر SQLite |
| MySQL 8+ | Schema + typed query + versioning + concurrency suite |
| PostgreSQL 12+ | Schema + typed query + versioning + concurrency suite |
| SQLite مدرن | Schema + core + query + JSON مورد پشتیبانی |

پشتیبانی از Laravel 11/12 در Scope این release نیست. معماری باید بتواند بعداً گسترش پیدا کند، اما dependency constraints را به‌صورت غیرآزمایش‌شده گسترده نکن.

### ۶.۲. CI (آزمایش خودکار روی GitHub Actions)

- Workflow برای Composer validation، install، Pint (با check/--test)، PHP syntax و PHPUnit.
- Matrix PHP و DB تا جایی که سرویس‌های CI واقعی فراهم‌اند؛ PostgreSQL/MySQL را با service container مناسب اجرا کن.
- نتایج skip شده واضح گزارش شوند؛ اجازه نده workflow در حالی سبز باشد که کل Integration suite به دلیل تنظیم اشتباه silent skip شده است. یک CI job مخصوص Integration باید اجباری باشد و نبود DB در آن باعث fail شود.
- مراقب اجرای مکرر migrate روی همان DB و collision migration باش؛ schema cleanup درست باشد.
- Composer package tests از autoload-dev و ServiceProvider auto-discovery واقعی استفاده کنند.

## ۷. بنچمارک و Performance Gate

تست‌های Functional نمی‌توانند «۵ میلی‌ثانیه در همه سرورها» را تضمین کنند. به‌جای آستانه زمانی ساختگی، حدهای ساختاری تعیین کن:

- ۱۰۰ مدل با Meta عادی و `withMeta`: حداکثر ۲ Query برای خواندن مدل+Meta.
- ۱۰۰ مدل ترکیبی با versioning: حداکثر ۳ Query.
- `getMeta('color')` برای Model دارای بارگذاری گروهی: بدون Query اضافه.
- `whereMeta` و فیلتر versioned: نتایج صحیح و بدون Duplicate، `EXPLAIN` روی DBهای اصلی بررسی‌شده.
- نوشتن کلید غیرنسخه‌دار بعد از ۱۰ بار تغییر: ۱ root و ۰ version؛ هیچ insert اضافی به جدول history.
- اسکریپت bench فاز ۲ روی ۱۰هزار و در صورت منابع کافی ۱۰۰هزار رکورد اجرا شود؛ نتایج واقعی median/p95/memory/query در `docs/performance.md` با تاریخ تست و مشخصات محیط درج شود.
- بنچمارک یک میلیون رکورد اختیاری و فقط در محیط مناسب؛ حذف خودکار داده واقعی، اجرای ناخواسته migration روی Production و seed بدون کنترل ممنوع.
- اگر مزیت cache ثابت نشده، cache سراسری اضافه نکن؛ کم‌کردن Query و انتخاب ایندکس صحیح اولویت دارد.

## ۸. پوشش مستندات نهایی

حداقل این فایل‌ها را کامل کن؛ محتوای فارسی واضح و **فرآیندمحور** باشد، با دستورات نصب و مثال کامل:

```text
README.md
CHANGELOG.md
LICENSE
composer.json
phpunit.xml
.github/workflows/tests.yml

docs/README.md
docs/installation.md
docs/usage.md
docs/types.md
docs/versioning.md
docs/search.md
docs/performance.md
docs/tenant.md
docs/rules.md
docs/errors.md
docs/testing.md
docs/scenarios/*.md
```

برای هر فایل محتوای لازم:

1. **installation.md:** `composer require`, `php artisan migrate`, Trait, نخستین set/get، رفتار config cache، چرا انتشار config/migration لازم نیست، نام ثابت فایل migration و policy تغییر migration پس از release.
2. **usage.md:** استفاده از Trait و Facade، چند meta، null و حذف، مدلی با UUID، فراخوانی `withMeta` و paginate.
3. **types.md:** جدول typeها، `decimal` رشته‌ای و عدد دقیق، Date/Datetime و UTC، تفاوت `'10'` با `10`، محدودیت JSON/Object.
4. **versioning.md:** versioned false پیش‌فرض، `versioned:true` در ثبت، inherit mode، نمونه ۳تاریخی با مقادیر عددی، backdating، scheduled future، tombstone، audit caveat و ارتقا از عادی.
5. **search.md:** **تمام scopeها** با ورودی و خروجی مورد انتظار، مثال string/int/decimal/bool/date/datetime/json/null، `!=` type semantics، Date range، pagination، SQL safety.
6. **performance.md:** N+1 با ۱۰۰ محصول، تعداد Query درست، ایندکس‌ها، EXPLAIN و benchmark واقعی اگر اجرا شد؛ توصیه نگهداری ویژگی‌های پرتکرار گزارش مالی در ستون تخصصی.
7. **tenant.md:** مثال `TenantResolver` خارج از پکیج؛ کلید tenant، fail-closed، عدم وابستگی به branch، تغییر tenant worker و Scope پاکسازی.
8. **rules.md:** قراردادهای atomicity، قفل، حذف منطقی، null/missing، انتقال حالت versioning و concurrency.
9. **errors.md:** نام Exceptionها، مثال علت و راه‌حل.
10. **testing.md:** دستور Unit/Feature/Scenario/Integration، تنظیمات DB و skip policy، اجرای benchmarks، PHPUnit config.
11. **docs/scenarios:** هر سناریو ۱ فایل MD با «پیش‌شرط‌ها، مراحل، ورودی، نتیجه مورد انتظار، فایل تست».

**مستندات نباید امکاناتی را که واقعاً پیاده نشده‌اند وعده دهند.** مثال‌های README و docs را به Integration smoke test وصل کن تا drift به‌سرعت تشخیص داده شود.

## ۹. بازبینی امنیتی و معماری پیش از Release

چک‌لیست اجباری:

- [ ] همه APIها Trait و Facade به همان MetaService متصل هستند.
- [ ] برای متادیتای معمولی هیچ هزینه ثبت تاریخچه نداریم.
- [ ] تغییر نوع مقدار، ستون‌های type قدیمی را پاک می‌کند.
- [ ] هیچ `unserialize` روی ورودی غیرقابل‌اعتماد نداریم.
- [ ] همه inputهای query bind می‌شوند؛ operatorها whitelist دارند.
- [ ] tenant filter در خواندن، نوشتن، حذف، history، eager loading و search اعمال می‌شود.
- [ ] استفاده از connection مدل با تراکنش درست تضمین می‌شود؛ مدل متصل به DB دیگر اشتباهاً DB پیش‌فرض را نمی‌نویسد.
- [ ] دو نویسنده هم‌زمان نمی‌توانند root تکراری ایجاد کنند.
- [ ] تاریخچه Versioned append-only است و tombstone درست عمل می‌کند.
- [ ] اولویت نسخه‌ها `effective_at DESC, id DESC` است، نه زمان ورود/updated_at تنها.
- [ ] مدل تازه بدون persist خطا می‌دهد و پنهانی صاحب را ذخیره نمی‌کند.
- [ ] کلیدهای متادیتا ویژگی‌های Eloquent را override نمی‌کنند.
- [ ] نصب نیازمند انتشار config/migrations نیست.
- [ ] نام migration ثابت است و duplicate registration نداریم.
- [ ] `composer.json` بدون minimum-stability dev و بدون version دستی است.
- [ ] تست‌ها assert واقعی دارند و CI Integration صرفاً به دلیل skip «سبز» اعلام نمی‌شود.

## ۱۰. معیار Done / Ready و نحوه گزارش

در پایان این فاز این خروجی را ارائه بده:

1. فایل‌های نهایی ساخته‌شده و تغییرکرده، با مسیر دقیق.
2. دستورهای `composer validate`, format check, static analysis (اگر ابزار نصب شد), `vendor/bin/phpunit` و اجرای suites؛ **موفقیت/شکست واقعی**.
3. تعداد Testها و Assertionها به تفکیک Unit/Feature/Scenarios/Integration؛ skipped و علل آن‌ها صریح.
4. خروجی ۳ سناریوی مهم همراه با مقدار عددی: normal/no-history؛ versioned/scheduled؛ typed search با mixed types.
5. وضعیت بررسی MySQL و PostgreSQL. اگر سرویس قابل دسترس نبوده، آماده‌بودن سازمانی را **مشروط** اعلام کن و ریسک را توضیح بده.
6. Benchmark واقعی، Query count و EXPLAIN مربوط به شرایط تست.
7. فهرست ریسک‌های باقی‌مانده، تغییرات شکسته احتمالی و پیشنهاد Semantic Version (`0.1.0` تا زمان تثبیت یا `1.0.0` پس از تکمیل همه Gateها).
8. راهنمای ۵دقیقه‌ای نصب و استفاده برای یک توسعه‌دهنده تازه‌وارد.

**تعریف موفقیت:** خروجی باید کد واقعی نصب‌شدنی و تست‌شده باشد؛ نه صرفاً PRD، proposal، چند کلاس ناقص یا ادعای بدون آزمایش. اگر مانعی وجود داشت تا جایی که ممکن است مشکل را برطرف کن و باقی‌مانده را شفاف گزارش بده.
