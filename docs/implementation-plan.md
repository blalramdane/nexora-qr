# Nexora QR — خطة التنفيذ التفصيلية

> هذه الخطة تُبنى على تقرير الـDiscovery & Architecture (20 قسمًا) + مراجعة الريبو الفعلية + القرارات المعتمدة أدناه.
> أي تعديل على هذه القرارات يستلزم تحديث هذه الخطة قبل الانتقال للتنفيذ.

---

## 0. القرارات المعتمدة (Decisions Log)

| # | القرار | القيمة المعتمدة | التاريخ |
|---|---|---|---|
| D1 | الخطوة التالية | خطة تنفيذ تفصيلية (هذه الوثيقة) — لا كود Nexora قبل موافقة صريحة | 2026-09-17 |
| D2 | مصير Task API القديمة | **حُذفت بالكامل** (controller/service/model/migration) والريبو نظيف الآن | 2026-09-17 |
| D3 | فرونت تطبيق العميل | **Inertia.js + React فوق Laravel** (وليس Next.js منفصل) — SSR مدعوم رسميًا، deploy واحد، فريق واحد | 2026-09-17 |
| D4 | قاعدة البيانات للتطوير | **SQLite محليًا**، إنتاج MySQL | 2026-09-17 |
| D5 | فرونت الداشبورد/الأدمن | نفس الـmonolith: Inertia.js + React (مسارات وlayouts منفصلة عن المنيو) | 2026-09-17 |
| D6 | بنية المستودع | **Monorepo واحد** (لا 3 مستودعات منفصلة) — Inertia يلغي مبرر الفصل؛ الباك والفرونت في نفس الريبو | 2026-09-17 |
| D7 | WhatsApp في MVP | `wa.me` فقط (وفق التقرير — مؤكد) | 2026-09-17 |
| D8 | هاتف المطعم | `ai-core` يبقى هو الريبو الرسمي للمنصة، ويُعاد تسميته لاحقًا عند الاستقرار | 2026-09-17 |

> **ملاحظة D3/D5/D6**: هذا القرار يعدّل القسم 13 و14 من تقرير الـDiscovery (الذي اقترح Next.js + منتجا React منفصلان). المبرر: فريق صغير، MVP سريع، لا حاجة لاستقلالية النشر في هذه المرحلة، وتجربة Inertia الناضجة في Laravel 12 مع SSR كامل للمطعم العام. الانتقال لـNext.js لاحقًا يبقى ممكنًا لأن الـAPI موحد من اليوم الأول.

---

## 1. نظرة عامة على البنية النهائية (MVP)

```
nexora-qr (repo: ai-core حاليًا)
├── app/
│   ├── Models/            # Eloquent + BelongsToRestaurant trait (tenant scope)
│   ├── Policies/          # صلاحيات لكل مورد مربوط بـrestaurant
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Public/    # واجهات العميل (menu, orders, tracking)
│   │   │   ├── Restaurant/# لوحة المطعم (menu builder, orders, tables, settings)
│   │   │   └── Admin/     # لوحة Nexora (restaurants, templates, impersonation)
│   │   ├── Requests/      # Form Requests صارمة لكل مدخل
│   │   ├── Middleware/    # tenant context, impersonation banner, role check
│   │   └── Resources/     # API Resources للـpayload الموحد
│   ├── Services/          # OrderService, PricingService, QrCodeService ...
│   ├── Jobs/              # SendWhatsAppNotify, GenerateQrBatch, ProcessImageUpload
│   └── Policies/          # تسجيل كل Resource policy
├── database/
│   ├── migrations/        # بترتيب المراحل أدناه
│   ├── seeders/           # GovernoratesSeeder, DemoRestaurantSeeder, TemplatesSeeder
│   └── factories/         # لكل موديل (اختبارات + demo data)
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Menu/      # تطبيق العميل (Scan → Order) — Inertia pages
│   │   │   ├── Dashboard/ # لوحة المطعم
│   │   │   └── Admin/     # لوحة Nexora
│   │   ├── Components/    # Design System components مشتركة
│   │   └── Layouts/       # MenuLayout / DashboardLayout / AdminLayout
│   └── views/app.blade.php  # نقطة دخول Inertia الوحيدة
├── routes/
│   ├── web.php            # Inertia routes (menu, dashboard, admin)
│   ├── api.php            # لـwebhooks فقط (WhatsApp) — auth عبر HMAC
│   └── console.php
└── tests/
    ├── Feature/           # رحلة العميل الكاملة، عزل المستأجرين، State machine
    └── Unit/              # PricingService, StateMachine, AvailabilityRules
```

**لماذا هذا الشكل؟** Inertia يسمح بثلاث تجارب واجهة (منيو/داشبورد/أدمن) في deploy واحد، مع SSR للمنيو العام (SEO + سرعة 3G)، وحماية CSRF وauth جاهزة من Laravel — دون بنية Frontend منفصلة تحتاج فريقًا ثانيًا.

---

## 2. المراحل (Phases) والمعالم (Milestones)

> كل مرحلة تنتهي بحالة **Runnable**: `composer install && php artisan migrate --seed && npm run dev` تنتج تطبيقًا يعمل ويستجيب.

### Phase 0 — التأسيس (Foundation) — ⏱️ ~1 أسبوع
**الهدف:** سكيما نظيفة + عزل مستأجرين مثبت باختبارات.

| المهمة | التفاصيل |
|---|---|
| **P0.1** إعادة تسمية منطقية | APP_NAME=Nexora QR، تحديث `.env.example`، حذف أي بقايا scaffold (تم جزئيًا) |
| **P0.2** Inertia 2 + React + TypeScript | `inertiajs/inertia-laravel`, `@inertiajs/react`, Tailwind 4 (موجود), `laravel-vite-plugin` كما هو |
| **P0.3** Auth scaffold | تسجيل/دخول صاحب المطعم (Breeze-style مكتوب يدويًا — لا مستخدم فريق بعد) |
| **P0.4** Migrations النواة | `restaurants, branches, users, restaurant_users, governorates, delivery_areas` |
| **P0.5** Tenant Scope | `BelongsToRestaurant` trait + `TenantContext` middleware + Global Scope تلقائي |
| **P0.6** Policies + Roles | owner/manager فقط مفعل في MVP (الباقي schema-ready) |
| **P0.7** اختبارات العزل | TenantIsolationTest: مستخدم A لا يرى أي بيانات مطعم B (إلزامي قبل أي Feature تالية) |

**Definition of Done:** مستخدم يسجل، ينشئ مطعمًا، البيانات معزولة تمامًا عن أي مطعم آخر (مثبت باختبارات).

### Phase 1 — المنيو + التصميم (Menu & Design) — ⏱️ ~2 أسابيع
**الهدف:** صاحب المطعم يبني منيو كامل ويختار تصميمًا ويشوفه live.

| المهمة | التفاصيل |
|---|---|
| **P1.1** Design System migrations | `templates, template_versions, restaurant_themes` — مع `is_premium` على `templates` من اليوم الأول (قرار القسم 17، كان ناقصًا في سكيما القسم 9) |
| **P1.2** Menu migrations | `menus, categories, products, product_variants, modifier_groups, modifier_options, product_modifier_groups` |
| **P1.3** Template Versioning | immutable snapshots؛ المطعم مرتبط بـ`template_version_id` محددة؛ rollback = تبديل مرجع |
| **P1.4** 2-3 Templates عالية الجودة | Fast Food / Cafe / Fine Dining — schema-driven rendering في React مع tokens من الـAPI |
| **P1.5** Menu Builder UI | Bulk-add products (جدول سريع) + single mode بالصور + modifier groups قابلة لإعادة الاستخدام |
| **P1.6** Onboarding Checklist | Shopify-style progress checklist (وليس wizard خطي جامد) |
| **P1.7** Availability + Out-of-stock | `availability_rules_json` + toggle نفاد فوري (realtime عبر Reverb) |
| **P1.8** Image pipeline | رفع → تحقق magic bytes → re-encode → WebP/AVIF متعدد الأحجام → S3-compatible (محليًا: `storage/`) |

**DoD:** مطعم جديد يمكنه الانتقال من التسجيل إلى نشر منيو مصمم بالكامل في < 30 دقيقة.

### Phase 2 — الطلبات (Ordering) — ⏱️ ~2 أسبوع
**الهدف:** الزبون يطلب فعليًا والمطعم يستقبل ويعالج.

| المهمة | التفاصيل |
|---|---|
| **P2.1** QR/Tables migrations | `tables, qr_codes` — **short_code عشوائي غير تسلسلي** (قرار القسم 9) |
| **P2.2** QR resolver | `GET /qr/{short_code}` → restaurant/branch/table context → menu page |
| **P2.3** Customer flow | Product sheet (modal)، Cart sheet، Checkout حسب النوع (dine-in = صفر حقول) |
| **P2.4** Order migrations | `customers, delivery_addresses, orders, order_items (+snapshots), order_item_modifiers (+snapshots), order_status_history` |
| **P2.5** PricingService | **السعر يُحسب حصراً server-side** من قاعدة البيانات وقت الـcheckout — لا ثقة بأي سعر من الفرونت |
| **P2.6** OrderStateMachine | NEW → CONFIRMED → PREPARING → READY → (OUT_FOR_DELIVERY →) DELIVERED/COMPLETED + CANCELLED/FAILED؛ كل انتقال مُقيّد بمن يحق له ومتى |
| **P2.7** Idempotency | `idempotency_key` يُنشأ عند فتح الـcheckout + re-validation للسعر/التوفر قبل الإنشاء |
| **P2.8** Order Tracking | `tracking_token` (UUID عشوائي) + صفحة تتبع حية (Reverb) |
| **P2.9** Restaurant Orders UI | Kanban بالحالة + تحديث حي + تغيير الحالة بتوقيع في `order_status_history` |
| **P2.10** wa.me integration | عند إنشاء الطلب: queued job يفتح رابط `wa.me` للمالك (تنبيه بشري MVP) |
| **P2.11** Tenant isolation للطلبات | كل استعلام orders مقيّد بـrestaurant scope + اختبارات |

**DoD:** رحلة كاملة Scan → Order → Track → Restaurant يقبل → COMPLETED، مع snapshot صحيح للأسعار حتى لو المنتج اتعدل بعدين.

### Phase 3 — التشغيل والتحليلات (Ops & Analytics) — ⏱️ ~1.5 أسبوع
| المهمة | التفاصيل |
|---|---|
| **P3.1** Nexora Admin | إدارة مطاعم + impersonation **مسجل بالكامل** (started_at, ended_at, reason) + banner واضح + session timeout أقصر |
| **P3.2** Activity logs | `activity_logs` لكل عملية حساسة (تعديل سعر، حذف منتج، تغيير حالة) |
| **P3.3** Analytics basic | `menu_events` (scan/view/cart/checkout) — **من اليوم الأول** لتجنب فقدان بيانات تاريخية للـPhase 2+ |
| **P3.4** Metrics endpoints | QR Scans, Conversion Rate, Cart Abandonment, AOV, Time to Publish |
| **P3.5** Overview Dashboard | أرقام سريعة للمالك + أفضل/أسوأ منتجات + peak hours |

**DoD:** Nexora Admin يستطيع دعم أي مطعم بأمان كامل، والمطعم يرى أرقامه الأساسية.

### Phase 4 — الإطلاق التجريبي (Pilot) — ⏱️ مستمرة
- تسجيل 5-10 مطاعم pilot
- دليل onboarding للمطعم (فيديو/كتيب عربي)
- مراقبة WhatsApp delivery + order timeouts
- جمع feedback → أولويات Phase 2 الحقيقية

---

## 3. السكيما النهائية (تعديلات على سكيما التقرير)

> التقرير (قسم 9) سليم في معظمه. هذه **تصحيحات إلزامية** ناتجة عن مراجعتي:

| الجدول | التعديل | السبب |
|---|---|---|
| `customers` | **إضافة `restaurant_id` + `unique(restaurant_id, phone)`** | الخرق الأكبر في سكيما التقرير: بدون هذا، هوية العميل تتسرب بين المطاعم وتحظر الـscope |
| `templates` | إضافة `is_premium boolean default false` | قرار قسم 17 ينص عليها من اليوم الأول لكن سكيما قسم 9 أغفلتها |
| `branches` | إضافة `supported_order_types json` | مطعم dine-in فقط لازم يُمنع من استقبال delivery |
| `products` | إضافة `is_featured` | مهم للـupsell rule-based وترتيب العرض |
| `orders` | `delivery_area_id` FK على `delivery_areas` (وليس نص حر) | ضبط مناطق التوصيل + حساب مصاريف التوصيل لاحقًا |
| `governorates` + `delivery_areas` | جداول مرجعية جديدة (seeder بيانات مصر) | بدونها الـdropdown في الـcheckout بلا مصدر حقيقة |
| `menu_events` | جدول جديد (restaurant_id, branch_id, qr_code_id, event_type, session_id, metadata_json, created_at) | الـKPIs (قسم 18) تحتاج بيانات من اليوم الأول، خاصة قبل Phase 2 |
| `order_items` | التأكيد على عدم الاعتماد على FK حي فقط — **snapshots إلزامية** | مغطى في التقرير لكن يستحق تثبيت كقرار |
| كل الجداول الرئيسية | `restaurant_id` صريح + index مركب `(restaurant_id, is_active)` | Tenant scoping على مستوى الاستعلام |
| `whatsapp_messages_log` | كما هو (cost_estimate يُترك للـPhase 2) | جاهزية الفوترة مستقبلًا |
| `plans`/`subscriptions` | كما هو (schema-ready غير مفعّل) | جاهزية Billing للـPhase 2 |

---

## 4. Design System — خطة التنفيذ العملية

1. **Phase 1.1-1.3:** Migrations + models + relationships.
2. **Phase 1.4:** كل template = مكونات React ثابتة الكود (`FastFoodTemplate`, `CafeTemplate`, `FineDiningTemplate`) تحقق نفس الـcontract:
   ```ts
   interface TemplateProps {
     tokens: DesignTokens;         // من template_version
     overrides: ThemeOverrides;    // من restaurant_themes
     menu: MenuPayload;            // categories/products/availability
     context: { restaurant, branch, table?, orderType };
   }
   ```
3. Tokens تُمرر كـCSS custom properties في runtime (`--nx-color-primary`) — لا CSS generation في الـbuild.
4. **Customization Slots** تُعرَّف في `components_json` لكل نسخة template: أي حقل مسموح للمطعم يعدله من `overrides_json`. أي شيء خارجها مرفوض server-side.
5. **Rollback** = تغيير `template_version_id` للمطعم. لا حذف بيانات أبدًا.
6. Code splitting: كل template chunk منفصل (Vite dynamic imports) — مطعم ما يحمّل كود قالبِه فقط.

---

## 5. WhatsApp — خطة MVP العملية (تأكيد قرار التقرير)

- MVP: `wa.me` فقط. عند تأكيد الطلب، queued job ينشئ deep link بصيغة:
  `https://wa.me/<restaurant_whatsapp>?text=<order summary encoded>`
- **رسالة للمطعم** أولوية (إشعار بالطلب الجديد)، ثم link "تواصل مع العميل" في تفاصيل الطلب بالداشبورد.
- `whatsapp_messages_log` يسجل كل محاولة (direction=outbound, template_used='wa_me_order_alert', status) منذ اليوم الأول — للجاهزية الفوترة Phase 2.
- **النقطة الحرجة (من تقرير القسم 7):** فشل الإشعار لا يفشل الطلب — الإشعار queued job منفصل تمامًا عن معاملة إنشاء الطلب.
- Phase 2 (مؤجل): 360dialog/Twilio BSP + message templates معتمدة من Meta + webhook HMAC verification + shared Nexora number.

---

## 6. الأمان — Checklist تنفيذية (كل بند له اختبار)

- [ ] Global Scope (`BelongsToRestaurant`) على كل موديل يحمل `restaurant_id` + **TenantIsolationTest** (إلزامي قبل أي ميزة تالية)
- [ ] Server-side pricing حصراً (PricingService) + اختبار: تعديل سعر من الفرونت لا يؤثر على الإجمالي
- [ ] Idempotency key + اختبار double-tap
- [ ] Rate limiting على `POST /orders` (per IP + per table) و`GET /qr/{code}`
- [ ] `tracking_token` + `short_code` عشوائيان غير تسلسليان (لا تخمين)
- [ ] Image upload: magic bytes + re-encode + S3-compatible منفصل
- [ ] Webhook HMAC verification (Phase 2 للـWhatsApp)
- [ ] Activity logs لكل عملية حساسة
- [ ] Impersonation: تسجيل كامل + banner + timeout قصير
- [ ] Policies على كل مورد + role checks في Form Requests

---

## 7. الأداء — Checklist

- [ ] SSR للمنيو العام (Inertia SSR) — هدف FCP < 1.5s على 3G
- [ ] Endpoint مجمّع واحد للمنيو: `GET /menu/{slug}` يرجع كل شيء دفعة واحدة (لا N+1)
- [ ] Redis cache للمنيو المُجهّز (يُبطل فورًا عند أي تعديل) — محليًا: file/array cache
- [ ] صور WebP/AVIF متعددة الأحجام عند الرفع (ليس عند الطلب) + lazy loading
- [ ] Code splitting لكل template (dynamic import)
- [ ] Service Worker خفيف للأصول الثابتة فقط (PWA محدود كما نص التقرير — لا offline ordering)
- [ ] Indexes: `(restaurant_id, is_active)` على كل جدول رئيسي + `short_code`, `tracking_token`, `idempotency_key` unique
- [ ] RTL كامل + خط عربي حقيقي + `preload` للخط والشعار

---

## 8. RTL & إمكانية الوصول (غير قابلة للتفاوض)

- `dir="rtl"` من الجذر + اتجاهات مكونات معكوسة منطقيًا (سلة تفتح من اليمين، أسهم معكوسة)
- خط عربي حقيقي من اليوم الأول (Cairo / Tajawal / IBM Plex Sans Arabic) — ليس placeholder لاتيني
- WCAG AA: تباين ألوان، alt text، أحجام خط قابلة للتكبير — **معيار جودة وليس ميزة تسويقية**
- اختبار على موبايل منخفض الإمكانيات + شبكة 3G (Chrome DevTools throttling) قبل أي إطلاق

---

## 9. الاستراتيجية التقنية المعتمدة

| الطبقة | الاختيار | ملاحظات |
|---|---|---|
| Backend | **Laravel 12** (كما هو) | نضج، Policies/Queues/Reverb جاهزة |
| Frontend (الكل) | **Inertia.js 2 + React + TypeScript** | SSR للمنيو العام فقط؛ Dashboard/Admin CSR كافية |
| DB | SQLite (تطوير) / MySQL (إنتاج) | json columns متوافقة بينهما |
| Cache/Queues | Redis (إنتاج) / file/sync (تطوير) | Reverb للـrealtime (orders, out-of-stock) |
| Storage | S3-compatible (R2/Spaces) — محليًا `storage/app/public` | نفس الكود عبر Filesystem API |
| CDN | Cloudflare أمام التطبيق | مجاني، حماية DDoS، قريب من مصر |
| Hosting | مصر/إقليمي أولًا، Hetzner/DO عند التوسع الخليجي | عبر Cloudflare |
| Testing | Pest/PHPUnit + Playwright (رحلة العميل E2E) | الاختبارات شرط دمج وليس رفاهية |

**لماذا Inertia وليس Next.js؟** (قرار D3) فريق أصغر يقدم MVP أسرع بنفس جودة SSR، دون إدارة deploys منفصلة أو CORS أو مصادقة عبر نطاقين. الـAPI موحد من اليوم الأول يجعل الانتقال لاحقًا غير مكلف إن نضج الفريق أو تغيرت المتطلبات.

---

## 10. معايير القبول على مستوى المشروع

1. كل مرحلة تنتهي **Runnable** + `php artisan test` أخضر بالكامل.
2. لا ميزة تُدمج قبل اختباراتها (feature + edge cases من قسم 19 في التقرير).
3. لا استعلام بدون tenant scope على أي جدول يحمل `restaurant_id` — يُراجع في code review + مغطى بالاختبارات.
4. لا سعر ولا صلاحية تُحتسب من الفرونت إطلاقًا.
5. كل جدول يمر عبر migrations + factories (لا بيانات يدوية).
6. README و`docs/api-reference` يُحدّثان مع كل phase (التقرير أشار لغيابهما — لا نكرر هذا الخطأ).

---

## 11. المخاطر الرئيسية وخطط التخفيف

| الخطر | التخفيف |
|---|---|
| Over-engineering الـDesign System قبل الـPMF | 2-3 قوالب فقط في MVP، تحقق سوقي قبل التوسع |
| اعتماد MVP على تفاعل المطعم يدويًا (wa.me) | دليل تشغيل + تدريب للمطاعم الأولى + متابعة أسبوعية في الـpilot |
| تعقيد Multi-tenancy + Versioning | Global scope إلزامي + TenantIsolationTest في CI من Phase 0 |
| SQLite dev / MySQL prod انحرافات | تجنب ميزات SQL غير قياسية؛ تشغيل مجموعة اختبارات على MySQL في CI لاحقًا |
| بيانات مصر المرجعية (محافظات/مناطق) تتغير | جدول مرجعي قابل للتحديث بseeder versioned |

---

## 12. ما التالي فورًا؟

بمجرد الموافقة على هذه الخطة (أو تعديلها)، يبدأ **Phase 0** مباشرة:
1. إعداد Inertia + React + TS + layouts الثلاثة
2. Migrations النواة (P0.4) مع factories
3. `BelongsToRestaurant` trait + TenantContext middleware
4. أول اختبار عزل مستأجرين أخضر

> **تم الإيقاف هنا كما طُلب — لا كود Nexora قبل موافقتك على الخطة.**
