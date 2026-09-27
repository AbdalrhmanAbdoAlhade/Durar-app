# Durar Store API

Laravel REST API لمتجر إلكتروني متخصص في **الحلي · الأحجار الكريمة · النيازك**، مع نظام مزادات مباشر، سلة للضيف والمستخدم، كوبونات على مستوى الأقسام، ودفع عبر **EdfaPay** وإشعارات **Firebase Cloud Messaging**.

---

## جدول المحتويات

- [المتطلبات](#المتطلبات)
- [التثبيت](#التثبيت)
- [إعداد البيئة](#إعداد-البيئة)
- [تشغيل المشروع](#تشغيل-المشروع)
- [الهيكل المعماري](#الهيكل-المعماري)
- [الوحدات (Modules)](#الوحدات-modules)
- [المصادقة والصلاحيات](#المصادقة-والصلاحيات)
- [API Overview](#api-overview)
- [الدفع (EdfaPay)](#الدفع-edfapay)
- [الإشعارات (Firebase)](#الإشعارات-firebase)
- [المزادات والجدولة](#المزادات-والجدولة)
- [الاختبارات](#الاختبارات)
- [Postman](#postman)
- [ملاحظات مهمة](#ملاحظات-مهمة)

---

## المتطلبات

| الأداة | الإصدار |
|--------|---------|
| PHP | ^8.5 |
| Composer | ^2 |
| Laravel | 13+ |
| MySQL / SQLite | — |
| Ext: `bcmath`, `ctype`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `fileinfo` |

**الحزم الأساسية:**

- `laravel/sanctum` — API authentication
- `spatie/laravel-permission` — Roles & permissions
- `kreait/firebase-php` — FCM notifications

---

## التثبيت

```bash
# 1. استنساخ المشروع
git clone <repo-url> Durar-app
cd Durar-app

# 2. تثبيت الاعتماديات
composer install

# 3. إعداد البيئة
cp .env.example .env
php artisan key:generate

# 4. ضبط قاعدة البيانات في .env ثم:
php artisan migrate
php artisan db:seed

# 5. ربط التخزين العام (للصور)
php artisan storage:link

# 6. (اختياري) نشر إعدادات Spatie
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

---

## إعداد البيئة

أضف / عدّل المتغيرات التالية في `.env`:

```env
APP_NAME="Durar Store"
APP_URL=http://localhost:8000
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=durar
DB_USERNAME=root
DB_PASSWORD=

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1

# EdfaPay
EDFAPAY_BASE_URL=https://dev-api.edfapay.com
EDFAPAY_MERCHANT_ID=
EDFAPAY_MERCHANT_PASSWORD=
EDFAPAY_CURRENCY=SAR
EDFAPAY_RETURN_URL="${APP_URL}/payment/return"
EDFAPAY_CALLBACK_URL="${APP_URL}/api/payments/edfapay/webhook"

# Firebase
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
```

> ضع ملف **Service Account JSON** من Firebase Console في المسار أعلاه، أو غيّر المسار في `.env`.

---

## تشغيل المشروع

```bash
# خادم التطوير
php artisan serve

# طابور الإشعارات / المهام (إن وُجد)
php artisan queue:work

# جدولة المزادات (يُفضّل Cron في الإنتاج)
php artisan schedule:work
```

**حساب الأدمن الافتراضي** (بعد الـ seeder):

| الحقل | القيمة |
|-------|--------|
| Email | `admin@gemsstore.com` |
| Password | `Admin@12345` |

> غيّر كلمة المرور فوراً في بيئة الإنتاج.

---

## الهيكل المعماري

```
app/
├── Http/
│   ├── Controllers/Api/     # طبقة الـ HTTP فقط
│   ├── Requests/            # Form Requests (validation)
│   ├── Resources/           # API Resources (response shaping)
│   └── Middleware/
├── Models/                  # Eloquent + relations + scopes
├── Services/                # منطق الأعمال (Cart, Order, Auction, EdfaPay, FCM…)
└── ...
database/
├── migrations/
├── factories/
└── seeders/
routes/
└── api.php                  # كل مسارات الـ API
```

**مبادئ التصميم:**

- Controllers رفيعة → تستدعي Services
- Validation عبر Form Requests
- Responses موحّدة عبر `ApiResponseTrait` (`success` / `error`)
- صلاحيات الأدمن عبر Spatie `role:admin`
- سلة الضيف تعتمد على Session؛ بعد تسجيل الدخول يتم الدمج تلقائياً

---

## الوحدات (Modules)

| # | الوحدة | الوصف |
|---|--------|--------|
| 1 | **Categories** | أقسام مسطحة (Flat) — بدون تفرعات |
| 2 | **Products** | منتجات مع غلاف + معرض + metadata JSON + خصم نسبة + كمية |
| 3 | **Banners** | بانرات عادية / عروض — polymorphic + ربط بكوبون |
| 4 | **Coupons** | نسبة أو مبلغ ثابت — تُطبَّق على **قسم كامل** |
| 5 | **Cart** | سلة User أو Guest (session) |
| 6 | **Reviews** | تقييمات تحتاج موافقة أدمن |
| 7 | **Auctions** | مزادات مستقلة + مزايدات حية + إغلاق وتحديد فائز |
| 8 | **Orders + Payments** | طلبات منتجات أو مزاد + EdfaPay |
| 9 | **Auth + Roles** | Sanctum + Spatie (`admin` / `customer`) |
| 10 | **Notifications** | FCM tokens + إرسال عبر Firebase |

> **Store Info** مؤجّل وغير داخل البناء الحالي.

---

## المصادقة والصلاحيات

| الدور | الوصول |
|-------|--------|
| **Guest** | تصفح الكتالوج، السلة، المزادات العامة |
| **Customer** (`auth:sanctum`) | سلة، طلبات، مزايدات، تقييمات، FCM |
| **Admin** (`auth:sanctum` + `role:admin`) | إدارة كاملة تحت `/api/admin/*` |

Headers شائعة:

```http
Authorization: Bearer {token}
Accept: application/json
X-Locale: ar          # أو en
```

---

## API Overview

### عام (بدون تسجيل)

| Method | Endpoint | الوصف |
|--------|----------|--------|
| POST | `/api/auth/register` | تسجيل |
| POST | `/api/auth/login` | دخول |
| GET | `/api/categories` | الأقسام |
| GET | `/api/products` | المنتجات |
| GET | `/api/banners` | البانرات النشطة |
| GET | `/api/auctions` | المزادات |
| GET | `/api/auctions/{id}/bids` | سجل المزايدات |
| GET | `/api/products/{id}/reviews` | التقييمات المعتمدة |
| GET/POST/PUT/DELETE | `/api/cart…` | سلة الضيف |
| POST | `/api/coupons/check` | التحقق من كوبون |
| POST | `/api/payments/edfapay/webhook` | Webhook الدفع |

### عميل مسجّل

| Method | Endpoint | الوصف |
|--------|----------|--------|
| POST | `/api/auth/logout` | خروج |
| GET | `/api/auth/me` | الملف الشخصي |
| POST | `/api/products/{id}/reviews` | إضافة تقييم |
| POST | `/api/auctions/{id}/bids` | مزايدة |
| POST | `/api/orders/checkout` | طلب من السلة |
| POST | `/api/auctions/{id}/checkout` | طلب بعد الفوز بمزاد |
| GET | `/api/orders` | طلباتي |
| GET | `/api/orders/{id}/payment` | حالة الدفع |
| POST/DELETE | `/api/fcm-tokens` | تسجيل/حذف توكن الجهاز |

### أدمن (`/api/admin/*` + `role:admin`)

CRUD كامل لـ: Categories · Products · Coupons · Banners · Auctions  
إضافة إلى: موافقة التقييمات، تحديث حالة الطلبات، إغلاق المزادات.

تفاصيل الحقول والأمثلة موجودة في **Postman Collection**.

---

## الدفع (EdfaPay)

1. عند الـ checkout يُنشأ الطلب بحالة `pending` / `payment_status = unpaid`.
2. `EdfaPayService::initiate()` يفتح جلسة دفع ويعيد `payment_redirect_url`.
3. العميل يُحوَّل لصفحة الدفع.
4. EdfaPay تستدعي:

   `POST /api/payments/edfapay/webhook`

5. السيرفر **لا يثق** في الـ payload وحده؛ يعيد التحقق عبر `checkStatus()` ببيانات التاجر، ثم يحدّث الطلب والدفع ويرسل إشعار FCM.

> صيغة الـ hash تختلف بين نسخ EdfaPay (v1/v2). راجع الـ Postman Collection / الداشبورد قبل التحويل لـ Live، واستخدم دائماً `checkStatus()` كمصدر حقيقة.

---

## الإشعارات (Firebase)

**التثبيت:**

```bash
composer require kreait/firebase-php
```

**الملف:** `storage/app/firebase/service-account.json`  
(من Firebase Console → Project Settings → Service Accounts)

**حالات الإرسال الحالية:**

- تجاوز مزايدة سابقة
- الفوز بمزاد
- الموافقة على تقييم
- تغيّر حالة الطلب
- نجاح / فشل الدفع

`NotificationService` يتجاهل الإرسال بهدوء إذا كان ملف الاعتماديات غير موجود (مناسب للتستات والبيئة المحلية).

---

## المزادات والجدولة

- المزاد كيان مستقل (غير مربوط بجدول المنتجات).
- الحالات: `scheduled` · `active` · `ended` · (إلغاء عند الحاجة).
- المزايدة محمية بـ `lockForUpdate` لمنع race conditions.
- الإغلاق اليدوي: `POST /api/admin/auctions/{id}/close`
- **Scheduled Command** يُفضّل أن يغلق المزادات المنتهية تلقائياً عند `ends_at` (أضفه في `routes/console.php` / Kernel schedule).

مثال جدولة:

```php
Schedule::command('auctions:close-expired')->everyMinute();
```

---

## الاختبارات

```bash
# كل التستات
php artisan test

# مجموعة محددة
php artisan test --filter=CartTest
php artisan test --filter=AuctionTest
php artisan test --filter=PaymentTest
```

- تعتمد على `RefreshDatabase` و Factories متوافقة مع الـ schema.
- `EdfaPayService` و `NotificationService` يُعمل لهم mock داخل التستات الحساسة حتى لا تعتمد على ملفات/شبكات خارجية.

---

## Postman

استورد الملف:

`postman/Gems_Store_API.postman_collection.json`

**متغيرات الكولكشن:**

| Variable | الاستخدام |
|----------|-----------|
| `base_url` | `http://localhost:8000/api` |
| `token` | توكن العميل (يُحفظ بعد Login) |
| `admin_token` | توكن الأدمن |
| `session_id` | سلة الضيف إن لزم |

ترتيب مقترح للتجربة: Login Admin → إنشاء قسم/منتج → Cart → Login Customer → Checkout → Payment.

---

## ملاحظات مهمة

1. **اللغة:** أرسل `X-Locale: ar` أو `en`؛ الحقول النصية ثنائية (`name_ar` / `name_en` …).
2. **المخزون:** يُخصم عند إنشاء طلب المنتج ويُسترجع عند الإلغاء (حسب منطق `OrderService`).
3. **الكوبون:** يُطبَّق على منتجات الأقسام المرتبطة بالكوبون فقط، وليس بالضرورة على كل السلة.
4. **الصور:** تُخزَّن على قرص `public`؛ تأكد من `php artisan storage:link`.
5. **الإنتاج:** عطّل `APP_DEBUG`، غيّر كلمات المرور الافتراضية، استخدم HTTPS، وراجع بيانات EdfaPay Live وملف Firebase.

---

## الترخيص

خاص — جميع الحقوق محفوظة لمالك المشروع.

---

## الدعم

للمشاكل التقنية أو طلبات التطوير، افتح Issue في المستودع أو تواصل مع فريق التطوير.
