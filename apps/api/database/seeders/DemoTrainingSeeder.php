<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Actions\SaveBillingSettings;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Actions\SaveTransferDetails;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Database\Seeders\DemoShiftsSeeder;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Policy\PolicySettings;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Tenancy\Audience;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Domain\Page\Templates;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Modules\Site\Models\SiteSettings;

/**
 * The demo's second kind of academy (docs/architecture/02, section 29): a
 * training office that sells online programmes to adult professionals
 * (academies.audience = adults: no guardians, no children, the training
 * words everywhere). Academy "training" (training.<platform domain>, or
 * header X-Academy: training) on Pro, with its owner, a coordinator and an
 * accountant, three trainers in engineering, project management and data,
 * four adult trainees on their own accounts (each their own payer), plans of
 * one-hour sessions priced in riyals and pounds, two programmes booked
 * (one paid, one waiting for payment), its reviewed policy, the games area
 * hidden, and its public page for adults on (the training template,
 * published), where a visitor books an introductory session for themself.
 *
 * Every account is …@demo.test with the demo password (config('app.demo'),
 * so identity:demo-secrets reaches them); sign in as training.owner@demo.test
 * (back office) or training.trainee@demo.test (the trainee's portal).
 * Once: nothing when the academy is there already, and nothing without the
 * demo platform (DemoCatchUpSeeder calls it only beside the demo academy).
 */
final class DemoTrainingSeeder extends Seeder
{
    public const SLUG = 'training';

    public function run(PlatformApi $platform, TenantDirectory $directory, TenantContext $tenants): void
    {
        if ($directory->bySlug(self::SLUG) !== null) {
            return;
        }

        $academy = $platform->createAcademy(new NewAcademy(
            slug: self::SLUG,
            nameAr: 'مكتب الخبرة للتدريب المهني',
            nameEn: 'Khibra Professional Training',
            baseCurrency: 'SAR',
            defaultTimezone: 'Asia/Riyadh',
            defaultLocale: 'ar',
            country: 'SA',
            // On Pro where the platform sells it (a server without the demo's plans: the default plan).
            plan: SaasPlan::query()->where('code', 'pro')->exists() ? 'pro' : null,
            audience: Audience::Adults,
        ));

        $tenants->run($academy, function (): void {
            $ownerId = $this->people();
            AuditActor::use(new AuditActor('user', $ownerId, 'م. طارق الدوسري'));
            try {
                $this->identity($ownerId);
                AcademyModules::query()->firstOrCreate([], ['hidden' => ['games']]);
                app(SavePolicySettings::class)->handle(PolicySettings::defaults()->toArray(), $ownerId);
                $subjects = $this->programmes();
                $this->trainers($subjects, $ownerId);
                $this->prices();
                $this->bookings($subjects, $ownerId);
                $this->page($subjects['pmp']);
            } finally {
                AuditActor::forget();
            }
        });

        $this->command?->info('Seeded academy "training" (adults). Sign in as training.owner@demo.test with the demo password.');
    }

    /** The office's team, trainers and trainees; returns the owner's id. */
    private function people(): string
    {
        $identity = app(IdentityApi::class);
        $password = (string) config('app.demo.password');
        $ids = [];
        foreach ([
            ['owner', 'م. طارق الدوسري', [Role::Owner, Role::Admin], 'Asia/Riyadh', null],
            ['scheduler', 'منسّقة البرامج', [Role::Scheduler], 'Asia/Riyadh', null],
            ['accountant', 'محاسب المكتب', [Role::Accountant], 'Asia/Riyadh', null],
            ['sales', 'لمى الحربي', [Role::Sales], 'Asia/Riyadh', null],
            ['trainer', 'م. هشام عبد الله', [Role::Teacher], 'Africa/Cairo', '+201001240001'],
            ['pm', 'أ. ريم القحطاني', [Role::Teacher], 'Asia/Riyadh', '+966500100002'],
            ['data', 'أ. يوسف المصري', [Role::Teacher], 'Africa/Cairo', '+201001240003'],
            ['trainee', 'م. عبد الرحمن السبيعي', [Role::Student], 'Asia/Riyadh', '+966500200001'],
            ['nada', 'م. ندى الشمري', [Role::Student], 'Asia/Riyadh', '+966500200002'],
            ['fahad', 'فهد العتيبي', [Role::Student], 'Asia/Riyadh', '+966500200003'],
            ['omar', 'م. عمر حجازي', [Role::Student], 'Europe/London', '+447700900301'],
        ] as [$key, $name, $roles, $tz, $phone]) {
            $ids[$key] = $identity->createUser(new NewUser(name: $name, roles: $roles, email: "training.{$key}@demo.test", phoneE164: $phone,
                password: $password, locale: 'ar', timezone: $tz));
        }
        // Grown-ups all: each their own way in and their own payer.
        $people = app(PeopleApi::class);
        foreach (['trainee' => 31, 'nada' => 27, 'fahad' => 35, 'omar' => 42] as $key => $age) {
            $people->saveStudentProfile($ids[$key], CarbonImmutable::now()->subYears($age)->subMonths(3)->startOfDay(), null);
        }

        return $ids['owner'];
    }

    /** Its name and logo, as Settings › Identity and colours sets them (the demo's drawing). */
    private function identity(string $ownerId): void
    {
        $academy = app(TenantContext::class)->require();
        $path = UpdateAcademyIdentity::directory($academy->id).'/logo.png';
        Storage::disk('public')->put($path, (string) file_get_contents(base_path('modules/Platform/database/seeders/files/demo-logo.png')));
        app(UpdateAcademyIdentity::class)->handle($academy->nameAr, $academy->nameEn, $path, null, $ownerId);
    }

    /** @return array<string, string> the programmes it sells: key => subject id */
    private function programmes(): array
    {
        $catalog = app(CatalogApi::class);

        return [
            'pmp' => $catalog->createSubject('إدارة المشاريع الاحترافية (PMP)', 'Project Management Professional (PMP)'),
            'etabs' => $catalog->createSubject('التصميم الإنشائي بـ ETABS', 'Structural design with ETABS'),
            'data' => $catalog->createSubject('تحليل البيانات بـ Excel وPower BI', 'Data analysis with Excel and Power BI'),
            'lead' => $catalog->createSubject('القيادة وإدارة الفرق', 'Leadership and team management'),
        ];
    }

    /** @param  array<string, string>  $subjects */
    private function trainers(array $subjects, string $ownerId): void
    {
        $people = app(PeopleApi::class);
        $user = fn (string $key) => (string) DB::table('users')->where('email', "training.{$key}@demo.test")->value('id');
        $profiles = [
            'trainer' => ['male', ['ar', 'en'], [$subjects['etabs']], [[7, '17:00', '22:00'], [2, '17:00', '22:00'], [4, '17:00', '22:00']]],
            'pm' => ['female', ['ar', 'en'], [$subjects['pmp'], $subjects['lead']], [[7, '18:00', '22:00'], [1, '18:00', '22:00'], [3, '18:00', '22:00']]],
            'data' => ['male', ['ar'], [$subjects['data']], [[6, '16:00', '21:00'], [1, '16:00', '21:00'], [3, '16:00', '21:00']]],
        ];
        $since = CarbonImmutable::now()->subMonths(2)->toDateString();
        foreach ($profiles as $key => [$gender, $languages, $teaches, $week]) {
            $id = $user($key);
            // Adults only: no age group but adults, and no group lessons yet.
            $people->saveTeacherProfile(new TeacherProfileData($id, $gender, $languages, $teaches, maxWeeklyMinutes: 20 * 60, ageGroups: ['adults']));
            $tz = (string) DB::table('users')->where('id', $id)->value('timezone');
            $version = ShiftSchedule::query()->create([
                'teacher_id' => $id, 'version' => 1, 'timezone' => $tz, 'status' => 'active', 'effective' => "[{$since},)", 'approved_by' => $ownerId,
            ]);
            foreach (DemoShiftsSeeder::week($week, $tz)->blocks as $block) {
                $version->rules()->create(['teacher_id' => $id, 'weekday' => $block->weekday, 'local_start' => $block->start(), 'duration_min' => $block->durationMin]);
            }
        }
    }

    /** VAT 15% included, two regions, plans of one-hour sessions, and where to transfer. */
    private function prices(): void
    {
        app(SaveBillingSettings::class)->handle(15, true, '310987654300003', 'KT-', 3, 'مكتب الخبرة للتدريب المهني', true);
        app(SavePriceRegions::class)->handle([
            ['name_ar' => 'السعودية والخليج', 'name_en' => 'Saudi Arabia and the Gulf', 'currency' => 'SAR', 'is_default' => true, 'countries' => ['SA', 'AE', 'KW', 'QA', 'BH', 'OM']],
            ['name_ar' => 'بريطانيا', 'name_en' => 'United Kingdom', 'currency' => 'GBP', 'countries' => ['GB']],
        ]);
        $region = DB::table('price_regions')->pluck('id', 'currency');
        //  [name ar, name en, block, per week, [SAR, GBP]]
        foreach ([
            ['4 جلسات · جلسة في الأسبوع', '4 sessions · once a week', 4, 1, [900, 190]],
            ['8 جلسات · جلستان في الأسبوع', '8 sessions · twice a week', 8, 2, [1700, 360]],
            ['12 جلسة · 3 في الأسبوع', '12 sessions · 3 a week', 12, 3, [2400, 500]],
        ] as $i => [$ar, $en, $block, $perWeek, [$sar, $gbp]]) {
            $plan = app(SavePlan::class)->handle(null, [
                'name_ar' => "{$ar} · 60 دقيقة", 'name_en' => "{$en} · 60 min",
                'session_minutes' => 60, 'sessions_per_block' => $block, 'sessions_per_week' => $perWeek, 'credit_scope' => 'enrollment',
                'prices' => [['price_region_id' => $region['SAR'], 'amount' => $sar], ['price_region_id' => $region['GBP'], 'amount' => $gbp]],
            ]);
            $plan->forceFill(['sort' => $i])->save();
        }
        app(SaveTransferDetails::class)->handle("تحويل بنكي: البنك الأهلي · SA03 1000 0000 1234 5678 9012 · مكتب الخبرة للتدريب المهني\nثم اضغط «دفعت بالتحويل» وأرسل صورة الإيصال.");
    }

    /**
     * Two programmes through the real actions (hold, enrol: invoice, weekly
     * times, sessions): the trainee's PMP twice a week, paid; Nada's ETABS
     * once a week, waiting for her payment.
     *
     * @param  array<string, string>  $subjects
     */
    private function bookings(array $subjects, string $ownerId): void
    {
        $billing = app(BillingApi::class);
        $user = fn (string $key) => (string) DB::table('users')->where('email', "training.{$key}@demo.test")->value('id');
        $regions = collect($billing->regions())->keyBy(fn (RegionOption $r) => $r->currency);
        $from = CarbonImmutable::now()->addDays(2);
        $enrol = function (string $trainer, string $trainee, string $subjectId, array $times) use ($user, $billing, $regions, $from, $ownerId) {
            $trainerId = $user($trainer);
            $tz = (string) DB::table('users')->where('id', $trainerId)->value('timezone');
            $occurrences = [];
            foreach ($times as [$weekday, $clock]) {
                $day = $from->setTimezone($tz)->startOfDay();
                $day = $day->addDays(($weekday - $day->dayOfWeekIso + 7) % 7);
                for ($i = 0; $i < 8; $i++, $day = $day->addWeek()) {
                    $occurrences[] = CarbonImmutable::parse($day->toDateString().' '.$clock, $tz)->getTimestamp();
                }
            }
            $plan = collect($billing->planOptions(60))->first(fn (PlanOption $p) => $p->sessionMinutes === 60 && $p->sessionsPerWeek === count($times));
            $batch = app(PlaceBookingHold::class)->handle($trainerId, $user($trainee), $occurrences, 60, $ownerId);

            return app(EnrollStudent::class)->handle($batch, $user($trainee), $subjectId, new EnrollmentTerms($plan->id, $user($trainee), $regions['SAR']->id), $ownerId);
        };

        $paid = $enrol('pm', 'trainee', $subjects['pmp'], [[7, '19:00'], [3, '19:00']]);
        $billing->recordPayment($paid->invoice->id, 'bank_transfer', $paid->invoice->total, 'SA-71204', CarbonImmutable::now()->subHours(5), $user('accountant'));
        $enrol('trainer', 'nada', $subjects['etabs'], [[2, '19:00']]);
    }

    /** Its page for adults, on and published: the training template; visitors book their own introductory session. */
    private function page(string $defaultSubjectId): void
    {
        SiteSettings::query()->create(['enabled' => true, 'content' => new \stdClass, 'trial_booking' => 'family', 'response_hours' => 4, 'default_trial_subject_id' => $defaultSubjectId]);
        $doc = Templates::make('training', 'adults');
        $doc['settings']['whatsapp'] = '+966500100000';
        $doc = SitePage::read($doc);
        $now = now();
        $page = new SitePage;
        $page->forceFill(['draft' => $doc, 'published' => $doc, 'draft_revision' => 1, 'published_revision' => 1, 'template' => 'training',
            'draft_saved_at' => $now, 'published_at' => $now])->save();
        SitePagePublish::query()->create(['revision' => 1, 'page' => $doc, 'published_at' => $now]);
    }
}
