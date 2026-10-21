<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\TeacherPublicCard;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * Noor, an academy with its page on (docs/architecture/02, section 24): the
 * owner, an admin, sales, a scheduler and an accountant; Quran (levels
 * Beginner to Advanced, tracks Memorising and Tajweed, a published program
 * with a description) and Arabic; four teachers who made a card (Sara on
 * the page, Mona keeps hers off, Huda's taken off by the academy, Laila's
 * account is off) and Omar who made none, each with an email and a number
 * that must never show; two price regions (Gulf in SAR for SA and KW, Egypt
 * in EGP for EG) and one plan priced in both.
 */
trait AcademySite
{
    protected function siteAcademy(bool $enabled = true): void
    {
        $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_ar' => 'أكاديمية نور', 'name_en' => 'Noor Academy', 'default_timezone' => 'Africa/Cairo']));
        $staff = fn (Role $role, string $name) => User::factory()->withRole($role)->create(['name' => $name, 'locale' => 'en']);
        $this->owner = $staff(Role::Owner, 'Hala');
        $this->admin = $staff(Role::Admin, 'Amr');
        $this->sales = $staff(Role::Sales, 'Reem');
        $this->scheduler = $staff(Role::Scheduler, 'Sami');
        $this->accountant = $staff(Role::Accountant, 'Adel');

        $quran = app(SaveSubject::class)->handle(null, 'قرآن', 'Quran',
            [['name_ar' => 'مبتدئ', 'name_en' => 'Beginner'], ['name_ar' => 'متوسط', 'name_en' => 'Intermediate'], ['name_ar' => 'متقدم', 'name_en' => 'Advanced']],
            [['name_ar' => 'حفظ', 'name_en' => 'Memorising'], ['name_ar' => 'تجويد', 'name_en' => 'Tajweed']]);
        $this->quran = $quran->id;
        $this->arabic = app(SaveSubject::class)->handle(null, 'لغة عربية', 'Arabic')->id;
        $levels = $quran->levels->sortBy('ordinal')->pluck('id')->values()->all();

        $program = app(SaveProgram::class)->handle(null, ['subject_id' => $this->quran, 'name_ar' => 'نور البيان', 'name_en' => 'Nour Al-Bayan',
            'description_ar' => 'من الحروف لحد القراءة', 'description_en' => 'From the letters to reading']);
        $version = $program->workingVersion();
        $level = app(SaveNode::class)->handle($version, null, ['kind' => 'level', 'title_ar' => 'المستوى الأول', 'title_en' => 'Level 1']);
        $unit = app(SaveNode::class)->handle($version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => 'الحروف', 'title_en' => 'Letters']);
        app(SaveNode::class)->handle($version, null, ['kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => 'درس 1', 'title_en' => 'Lesson 1']);
        app(PublishVersion::class)->handle($version, null);

        $this->teachers = [];
        foreach ([
            'sara' => ['Sara Fathy', '+201001110001', true, false, 'active', 'female'],
            'mona' => ['Mona Adel', '+201001110002', false, false, 'active', 'female'],
            'huda' => ['Huda Samir', '+201001110003', true, true, 'active', 'female'],
            'laila' => ['Laila Nabil', '+201001110004', true, false, 'disabled', 'female'],
            'omar' => ['Omar Hany', '+201001110005', null, false, 'active', 'male'],
        ] as $key => [$name, $phone, $shown, $hidden, $status, $gender]) {
            $teacher = User::factory()->withRole(Role::Teacher)->create(['name' => $name, 'email' => "{$key}.teacher@noor.example", 'phone_e164' => $phone, 'status' => $status]);
            app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($teacher->id, $gender, ['ar', 'en'], [$this->quran], ageGroups: ['kids', 'teen'],
                subjects: [new TeacherSubjectData($this->quran, $levels[0], $levels[1])]));
            if ($shown !== null) {
                TeacherPublicCard::query()->create(['teacher_id' => $teacher->id, 'display_name' => 'Ms '.explode(' ', $name)[0], 'bio' => "{$name} teaches Quran.",
                    'shown' => $shown, 'hidden_by_academy' => $hidden]);
            }
            $this->teachers[$key] = $teacher;
        }

        app(SavePriceRegions::class)->handle([
            ['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'countries' => ['SA', 'kw']],
            ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'countries' => ['EG']],
        ]);
        $this->regions = PriceRegion::query()->pluck('id', 'currency')->all();
        app(SavePlan::class)->handle(null, ['name_ar' => '8 حصص', 'name_en' => '8 lessons', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2,
            'prices' => [['price_region_id' => $this->regions['SAR'], 'amount' => '400'], ['price_region_id' => $this->regions['EGP'], 'amount' => '2200']]]);

        SiteSettings::query()->create(['enabled' => $enabled, 'content' => SiteContent::defaults()]);
        $this->clearTenant();
    }

    /** Runs a read in Noor. */
    protected function inNoor(callable $read): mixed
    {
        $this->actAsTenant($this->academy);
        try {
            return $read();
        } finally {
            $this->clearTenant();
        }
    }

    /** Every email and number of Noor's people, none of which the public page may carry. */
    protected function noorContacts(): array
    {
        return DB::connection('pgsql_platform')->table('users')->where('academy_id', $this->academy->id)->get(['email', 'phone_e164'])
            ->flatMap(fn ($u) => array_filter([$u->email, $u->phone_e164, $u->phone_e164 ? ltrim($u->phone_e164, '+') : null]))->values()->all();
    }
}
