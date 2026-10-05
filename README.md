# Academy LMS

منصة SaaS لأكاديميات التعليم الأونلاين اللي بتدّي حصص فردية مباشرة (1:1) في القرآن واللغة العربية واللغات والمواد الدراسية. المنصة بتخدم أكتر من أكاديمية، وكل أكاديمية بتدير مدرسيها وشفتاتهم، وطلابها وأولياء أمورهم، والباقات والدفع، والحصص المباشرة، والمنهج والتقييم.

> **الحالة:** التنفيذ شغال. فيه دلوقتي أساس الـ backend (تعدد الأكاديميات بـ RLS، والمستخدمين، وتسجيل الدخول، والتوقيت المحلي)، ولوحة الإدارة: **لوحة العمليات اللحظية** بشكلين، و**الهوية والألوان** بقوالب، و**إعداداتي**. التفاصيل في [apps/api/README.md](apps/api/README.md).

## جرّبه على جهازك

محتاج بس [Docker Desktop](https://www.docker.com/products/docker-desktop/):

```sh
git clone https://github.com/Mahmoud-Elgonidi/academy-lms.git
cd academy-lms
docker compose up
```

أول مرة بتاخد كام دقيقة (بتنزّل المكتبات وتجهّز قاعدة البيانات). لما تظهر رسالة `Server running`، افتح:

- **http://localhost:8000/admin**
- الدخول: `owner@demo.test` / `password`

البيانات التجريبية فيها مساء كامل من الحصص حوالين وقت التشغيل: مدرس متأخر، وطالب ما دخلش، واتصال ضعيف، وحصة مش مدفوعة، وحصص ماشية وجاية. عشان تبدأ مساء جديد بمواعيد جديدة: `docker compose exec api composer demo`.

## الوثيقة المعمارية

ابدأ من [docs/architecture/README.md](docs/architecture/README.md):

| الملف | المحتوى |
|---|---|
| [README.md](docs/architecture/README.md) | الملخص التنفيذي، والقرارات المعمارية، وجدول تتبّع المتطلبات، والمصطلحات |
| [01-features.md](docs/architecture/01-features.md) | الأدوار والصلاحيات، وكتالوج الميزات، والتدفقات الأساسية |
| [02-domain-rules.md](docs/architecture/02-domain-rules.md) | قواعد العمل: الوقت والجدولة، والشفتات، والمطابقة، والرصيد، والسياسات، والمنهج، والتقييم |
| [03-technical-architecture.md](docs/architecture/03-technical-architecture.md) | المعمارية التقنية والبيانات والمدفوعات والفيديو والتوسع والأمن |
| [04-design-patterns.md](docs/architecture/04-design-patterns.md) | الـ Modular Monolith، والـ modules، والـ design patterns، وهيكل الكود |
| [05-roadmap.md](docs/architecture/05-roadmap.md) | المراحل، ونطاق الـ MVP، والمخاطر، والأسئلة المفتوحة |

## التقنيات

| الجزء | التقنية |
|---|---|
| Backend + لوحات الإدارة | Laravel (Modular Monolith) + Filament |
| قاعدة البيانات | PostgreSQL مع Row-Level Security |
| Queues وCache وRealtime | Redis، وHorizon، وReverb |
| تطبيقات الموبايل | Flutter: تطبيق واحد للطالب وولي الأمر والمدرس |
| الويب | Next.js: البوابة، والفصل المباشر، والمواقع العامة |
| الفيديو | LiveKit |

## التشغيل السريع

```sh
docker compose up -d          # Postgres (بالأدوار التلاتة) + Redis + Mailpit
cd apps/api
composer install && cp .env.example .env && php artisan key:generate
composer migrate && php artisan db:seed
composer test
```

## الهيكل المخطط للريبو

```text
apps/
  api/        Laravel: REST API + Filament + workers
  mobile/     Flutter
  web/        Next.js
packages/
  openapi/          عقد الـ API (openapi.json)
  api-client-dart/  بيتولّد تلقائيًا
  api-client-ts/    بيتولّد تلقائيًا
  design-tokens/
infra/        terraform/, docker/, runbooks/
docs/         الوثيقة المعمارية + ADRs
```

التفاصيل في [04 › هيكل المجلدات](docs/architecture/04-design-patterns.md#6-هيكل-المجلدات).
