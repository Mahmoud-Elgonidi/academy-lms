<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Models\User;
use Modules\Import\Actions\CheckImport;
use Modules\Import\Actions\MapImportColumns;
use Modules\Import\Actions\RunImport;
use Modules\Import\Actions\StageImport;
use Modules\Import\Filament\Pages\ImportData;
use Modules\Import\Models\ImportRun;
use Modules\Import\Support\Cells;
use Modules\SharedKernel\Contact\Phone;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Bringing an academy's sheets in (docs/architecture/01: "استيراد من
 * CSV/Excel"). The fixture: academy "noor" on Cairo's clock, a Quran
 * subject, a Cairo teacher teaching a London student Tuesdays and Saturdays
 * at 18:00, a Riyadh teacher, and the plan "8 حصص" (2 a week, 30 minutes).
 * Monday 5 October 2026.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** @param  list<list<string>>  $rows  the first is the titles */
function sheetFile(array $rows, string $ext = 'csv'): string
{
    $path = tempnam(sys_get_temp_dir(), 'sheet-').'.'.$ext;
    if ($ext === 'csv') {
        $f = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($f, $row, escape: '');
        }
        fclose($f);
    } else {
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    return $path;
}

/** Stages, checks and (unless $dry) imports a sheet as the admin. */
function importSheet(string $kind, array $rows, bool $dry = false, array $options = [], string $ext = 'csv'): ImportRun
{
    $actor = test()->admin->id;
    $run = app(StageImport::class)->handle($kind, sheetFile($rows, $ext), "{$kind}.{$ext}", $actor);
    if ($options !== []) {
        $run = app(MapImportColumns::class)->handle($run, $run->mapping, $options);
    }
    $run = app(CheckImport::class)->handle($run, $actor);

    return $dry ? $run : app(RunImport::class)->handle($run, $actor);
}

function rowStatuses(ImportRun $run): array
{
    return $run->rows()->get()->mapWithKeys(fn ($r) => [$r->line => $r->status])->all();
}

it('reads what people type: numbers, days and hours, lengths and dates', function () {
    expect(Phone::normalize('0101 234 5678', '20'))->toBe('+201012345678')
        ->and(Phone::normalize('٠١٠١٢٣٤٥٦٧٨', '20'))->toBe('+201012345678')
        ->and(Phone::normalize('00966501234567'))->toBe('+966501234567')
        ->and(Phone::normalize('501234567', '966'))->toBe('+966501234567')
        ->and(Phone::normalize('+44 7700 900123'))->toBe('+447700900123')
        ->and(Phone::normalize('abc'))->toBeNull()
        ->and(Cells::weeklyTimes('الأحد 17:00، الأربعاء 5:30 م'))->toBe([['weekday' => 7, 'time' => '17:00'], ['weekday' => 3, 'time' => '17:30']])
        ->and(Cells::weeklyTimes('السبت والثلاثاء 6 م'))->toBe([['weekday' => 6, 'time' => '18:00'], ['weekday' => 2, 'time' => '18:00']])
        ->and(Cells::weeklyTimes('Sun 9:15 am; Wed 5pm'))->toBe([['weekday' => 7, 'time' => '09:15'], ['weekday' => 3, 'time' => '17:00']])
        ->and(Cells::weeklyTimes('كل يوم'))->toBeNull()
        ->and([Cells::minutes('45 دقيقة'), Cells::minutes('ساعة'), Cells::minutes('١.٥ ساعة')])->toBe([45, 60, 90])
        ->and(Cells::date('20/3/2015')?->toDateString())->toBe('2015-03-20')
        ->and(Cells::gender('مدرّسة'))->toBe('female');
});

it('maps columns by their titles, checks every row without writing anything, then imports teachers', function () {
    $users = User::query()->count();
    $rows = [
        ['الاسم', 'الموبايل', 'الإيميل', 'النوع', 'البلد', 'المواد', 'لغة الشرح', 'ملاحظات'],
        ['أ. سارة محمود', '0101 234 5678', '', 'أنثى', 'مصر', 'قرآن، تجويد', 'عربي، إنجليزي', 'ممتازة'],
        [$this->other->name, '', $this->other->email, 'ذكر', 'الرياض', 'قرآن، نحو', '', ''],
        ['أ. عمر', '01111111111', '', 'ممكن', 'مصر', 'قرآن', '', ''],
        ['أ. منى', '', '', 'أنثى', '', 'تجويد', '', ''],
        ['أ. هالة', '01222222222', '', 'أنثى', 'مصر', 'تجويد', '', ''],
    ];

    $checked = importSheet('teachers', $rows, dry: true);

    expect($checked->mapping)->toMatchArray(['name' => 0, 'phone' => 1, 'email' => 2, 'gender' => 3, 'timezone' => 4, 'subjects' => 5, 'languages' => 6])
        ->and(rowStatuses($checked))->toBe([2 => 'new', 3 => 'update', 4 => 'error', 5 => 'error', 6 => 'new'])
        ->and($checked->counts)->toBe(['new' => 2, 'update' => 1, 'skip' => 0, 'error' => 2])
        ->and($checked->rows()->where('line', 2)->value('message'))->toContain('تجويد')
        ->and($checked->rows()->where('line', 4)->value('message'))->toContain('ممكن')
        // Nothing written: no users, no new subject (the dry run rolled back).
        ->and(User::query()->count())->toBe($users)
        ->and(DB::table('subjects')->where('name_ar', 'تجويد')->exists())->toBeFalse();

    $done = app(RunImport::class)->handle($checked, $this->admin->id);
    $sara = User::query()->where('phone_e164', '+201012345678')->sole();

    expect(rowStatuses($done))->toBe([2 => 'created', 3 => 'updated', 4 => 'failed', 5 => 'failed', 6 => 'created'])
        ->and($sara->roles())->toBe([Role::Teacher])
        ->and($sara->timezone)->toBe('Africa/Cairo')
        ->and(DB::table('teacher_profiles')->where('user_id', $sara->id)->value('gender'))->toBe('female')
        ->and(DB::table('teacher_subjects')->where('teacher_id', $sara->id)->count())->toBe(2)
        ->and(DB::table('subjects')->where('name_ar', 'تجويد')->count())->toBe(1)     // made once, shared by Sara and Hala
        ->and(DB::table('teacher_subjects')->where('teacher_id', $this->other->id)->count())->toBe(2)
        ->and(fn () => app(RunImport::class)->handle($done, $this->admin->id))->toThrow(ValidationException::class);
});

it('brings families in: siblings share their guardian, children get accounts under them, an adult signs in alone', function () {
    $rows = [
        ['اسم الطالب', 'تاريخ الميلاد', 'البلد', 'ولي الأمر', 'موبايل ولي الأمر', 'صلة القرابة', 'إيميل الطالب'],
        ['يوسف أحمد', '2016', 'لندن', 'أحمد علي', '+44 7700 900123', 'أب', ''],
        ['مريم أحمد', '20/5/2013', 'لندن', 'أحمد علي', '+447700900123', 'أب', ''],
        ['خالد سمير', '1990-01-01', 'كندا', '', '', '', 'khaled.samir@example.com'],
        ['ليلى', '', '', 'زياد', $this->teacher->phone_e164 ?? '+201000000001', '', ''],
        ['سلمى', '', '', '', '', '', ''],
    ];
    if ($this->teacher->phone_e164 === null) {
        $this->teacher->forceFill(['phone_e164' => '+201000000001'])->save();
    }

    $run = importSheet('families', $rows);
    $guardian = User::query()->where('phone_e164', '+447700900123')->sole();
    $children = User::query()->whereIn('id', DB::table('guardian_student')->where('guardian_id', $guardian->id)->pluck('student_id'))->orderBy('name')->get();
    $adult = User::query()->where('email', 'khaled.samir@example.com')->sole();

    expect(rowStatuses($run))->toBe([2 => 'created', 3 => 'created', 4 => 'created', 5 => 'failed', 6 => 'failed'])
        ->and($guardian->roles())->toBe([Role::Guardian])
        ->and($children->pluck('name')->all())->toBe(['مريم أحمد', 'يوسف أحمد'])
        ->and($children->every(fn (User $c) => $c->is_managed_minor && str_starts_with($c->username, 'learner.') && $c->timezone === 'Europe/London'))->toBeTrue()
        ->and(DB::table('student_profiles')->where('user_id', $children[0]->id)->value('birth_date'))->toBe('2013-05-20')
        ->and(DB::table('guardian_student')->where('student_id', $children[1]->id)->value('relationship'))->toBe('father')
        ->and([$adult->is_managed_minor, $adult->timezone])->toBe([false, 'America/Toronto'])
        ->and($run->rows()->where('line', 5)->value('message'))->toContain($this->teacher->name);

    // The same file again: everyone is already here.
    expect(rowStatuses(importSheet('families', $rows)))->toBe([2 => 'skipped', 3 => 'skipped', 4 => 'skipped', 5 => 'failed', 6 => 'failed']);
});

it('brings running schedules over: an active enrollment, its weekly times and lessons ahead, the balance left and the plan', function () {
    importSheet('families', [
        ['اسم الطالب', 'ولي الأمر', 'موبايل ولي الأمر', 'البلد'],
        ['يوسف أحمد', 'أحمد علي', '+447700900123', 'لندن'],
        ['مريم أحمد', 'أحمد علي', '+447700900123', 'لندن'],
    ]);
    $teacherContact = $this->teacher->email;

    $run = importSheet('schedules', [
        ['الطالب', 'رقم التواصل', 'المدرس', 'المادة', 'مدة الحصة', 'المواعيد', 'توقيت المواعيد', 'الحصص المتبقية', 'الباقة'],
        ['يوسف أحمد', '+447700900123', $teacherContact, 'قرآن', '30', 'الأحد 17:00، الأربعاء 17:00', '', '5', '8 حصص'],
        ['مريم أحمد', '+447700900123', $teacherContact, 'Quran', '30 دقيقة', 'الاثنين 5 م، الخميس 5 م', 'الطالب', '1', '8 حصص'],
        ['يوسف أحمد', '+447700900123', $teacherContact, 'قرآن', '30', 'الأحد 17:00، الأربعاء 17:00', '', '5', ''],
        ['مريم أحمد', '+447700900123', $this->other->email, 'قرآن', '30', 'الثلاثاء 18:00', '', '', ''],
        ['سلمى', '+447700900123', $teacherContact, 'قرآن', '30', 'الأحد 17:00', '', '', ''],
        ['يوسف أحمد', '+447700900123', $teacherContact, 'قرآن', '45', 'الأحد 19:00', '', '', '8 حصص'],
    ]);

    expect(rowStatuses($run))->toBe([2 => 'created', 3 => 'created', 4 => 'skipped', 5 => 'created', 6 => 'failed', 7 => 'skipped']);

    $yousef = User::query()->where('name', 'يوسف أحمد')->sole();
    $enrollment = DB::table('enrollments')->where('student_id', $yousef->id)->sole();
    $slots = DB::table('recurring_slots')->where('enrollment_id', $enrollment->id)->orderBy('weekday')->get();
    $lessons = DB::table('sessions')->where('enrollment_id', $enrollment->id)->orderBy('starts_at')->get();
    $account = DB::table('enrollments')->where('id', $enrollment->id)->value('credit_account_id');

    expect($enrollment->status)->toBe('active')
        ->and($slots->map(fn ($s) => [$s->weekday, substr($s->local_start, 0, 5), $s->anchor_tz])->all())->toBe([[3, '17:00', 'Africa/Cairo'], [7, '17:00', 'Africa/Cairo']])
        ->and(CarbonImmutable::parse($lessons->first()->starts_at)->setTimezone('Africa/Cairo')->format('D H:i'))->toBe('Wed 17:00')
        ->and($lessons->where('funding_status', 'funded')->count())->toBe(5)            // five lessons left, five funded
        ->and((int) DB::table('credit_lots')->where('account_id', $account)->sum('granted_min'))->toBe(150)
        ->and(DB::table('subscriptions')->where('enrollment_id', $enrollment->id)->value('block_no'))->toBe(1)
        ->and(DB::table('invoices')->where('billing_account_id', DB::table('subscriptions')->where('enrollment_id', $enrollment->id)->value('billing_account_id'))->count())->toBe(1);  // Maryam's renewal only

    // Maryam's times were on her London clock: 17:00 there is 19:00 in Cairo; one lesson left, so the next block's invoice is out.
    $maryam = User::query()->where('name', 'مريم أحمد')->sole();
    $hers = DB::table('enrollments')->where('student_id', $maryam->id)->where('teacher_id', $this->teacher->id)->sole();
    expect(DB::table('recurring_slots')->where('enrollment_id', $hers->id)->orderBy('weekday')->pluck('local_start')->map(fn ($t) => substr($t, 0, 5))->all())->toBe(['19:00', '19:00'])
        ->and(DB::table('invoices as i')->join('subscriptions as s', fn ($j) => $j->on('s.academy_id', '=', 'i.academy_id')->on('s.id', '=', 'i.subscription_id'))
            ->where('s.enrollment_id', $hers->id)->where('i.status', 'issued')->count())->toBe(1);

    expect($run->rows()->where('line', 7)->value('message'))->toContain(__('import::rows.notes.schedule_exists'))
        ->and($run->rows()->where('line', 6)->value('message'))->toContain('سلمى');
});

it('refuses a schedule that clashes with the teacher\'s week, a plan that does not fit, a teacher who does not teach the subject', function () {
    importSheet('families', [['اسم الطالب', 'ولي الأمر', 'موبايل ولي الأمر'], ['يوسف أحمد', 'أحمد علي', '+447700900123']]);
    $nahw = app(CatalogApi::class)->createSubject('نحو', 'Grammar');

    $run = importSheet('schedules', [
        ['الطالب', 'رقم التواصل', 'المدرس', 'المادة', 'مدة الحصة', 'المواعيد', 'الباقة'],
        ['يوسف أحمد', '+447700900123', $this->teacher->email, 'قرآن', '30', 'الثلاثاء 18:00', ''],        // the fixture student's time
        ['يوسف أحمد', '+447700900123', $this->teacher->email, 'قرآن', '30', 'الأحد 17:00', '8 حصص'],      // 1 a week, the plan is 2
        ['يوسف أحمد', '+447700900123', $this->teacher->email, 'نحو', '30', 'الأحد 17:00', ''],
    ]);

    expect(rowStatuses($run))->toBe([2 => 'failed', 3 => 'failed', 4 => 'failed'])
        ->and($run->rows()->pluck('message')->all())->toBe([
            __('scheduling::find.errors.slot_taken'),
            __('scheduling::find.errors.plan_mismatch', ['minutes' => 30, 'per_week' => 1]),
            __('scheduling::import.errors.not_their_subject'),
        ])
        ->and(DB::table('enrollments')->count())->toBe(1);        // the fixture's only
});

it('welcomes new teachers and parents with their own link to choose a password, and lets them in', function () {
    $run = importSheet('teachers', [
        ['الاسم', 'الموبايل', 'الإيميل', 'النوع', 'المواد'],
        ['أ. سارة محمود', '01012345678', 'sara.m@example.com', 'أنثى', 'قرآن'],
    ]);
    importSheet('families', [['اسم الطالب', 'ولي الأمر', 'موبايل ولي الأمر'], ['يوسف', 'أحمد علي', '+447700900123']]);
    importSheet('teachers', [['الاسم', 'الموبايل', 'النوع', 'المواد'], ['أ. عمر', '01099999999', 'ذكر', 'قرآن']], options: ['invite' => false]);

    $sara = User::query()->where('email', 'sara.m@example.com')->sole();
    $parent = User::query()->where('phone_e164', '+447700900123')->sole();
    $omar = User::query()->where('phone_e164', '+201099999999')->sole();
    $welcome = Notification::query()->where('user_id', $sara->id)->where('template_key', 'import.welcome_teacher')->sole();

    expect($welcome->body)->toContain('/welcome/'.$sara->id)->toContain('signature=')
        ->and(Notification::query()->where('user_id', $parent->id)->where('template_key', 'import.welcome_family')->count())->toBe(1)
        ->and(Notification::query()->where('template_key', 'like', 'import.welcome%')->count())->toBe(2)   // not the child, not Omar
        ->and($omar->password)->toBeNull();

    preg_match('#(/welcome/[^\s]+)#', $welcome->body, $m);
    $link = 'http://noor.lms.test'.parse_url($m[1], PHP_URL_PATH).'?'.parse_url($m[1], PHP_URL_QUERY);
    $this->get($link)->assertOk()->assertSee($sara->name)->assertSee('sara.m@example.com');
    $this->get('http://noor.lms.test/welcome/'.$sara->id)->assertForbidden();     // unsigned

    $action = URL::temporarySignedRoute('identity.welcome.store', now()->addMinutes(30), ['user' => $sara->id], absolute: false);
    $this->post('http://noor.lms.test'.$action, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('http://noor.lms.test'.$action, ['password' => 'a-good-pass', 'password_confirmation' => 'a-good-pass'])->assertRedirect('http://noor.lms.test/teach');

    $this->actAsTenant($this->academy);     // the requests set and cleared the academy
    expect(Hash::check('a-good-pass', $sara->refresh()->password))->toBeTrue()
        ->and(Auth::id())->toBe($sara->id);
    $this->get($link)->assertOk()->assertSee(__('identity::welcome.already'));
});

it('signs people in with their mobile number as they write it, or their email', function () {
    $this->teacher->forceFill(['phone_e164' => '+201012345678', 'password' => Hash::make('secret-123')])->save();
    Filament::setCurrentPanel('teach');

    foreach (['01012345678', '+20 101 234 5678', $this->teacher->email] as $login) {
        Auth::logout();
        Livewire::test(SignIn::class)->fillForm(['email' => $login, 'password' => 'secret-123'])->call('authenticate')->assertHasNoFormErrors();
        expect(Auth::id())->toBe($this->teacher->id);
    }

    Auth::logout();
    Livewire::test(SignIn::class)->fillForm(['email' => '01012345678', 'password' => 'wrong'])->call('authenticate')->assertHasFormErrors(['email']);
    expect(Auth::check())->toBeFalse();
});

it('walks through the import screen: file, columns, review, import; with templates to start from', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $csv = "name,email,mobile,gender,subjects\nSara Mahmoud,sara.m@example.com,01012345678,female,Quran\nNo Contact,,,male,Quran\n";

    $page = Livewire::test(ImportData::class)
        ->call('template', 'families')->assertFileDownloaded('import-families.xlsx')
        ->set('kind', 'teachers')
        ->set('file', UploadedFile::fake()->createWithContent('teachers.csv', $csv))
        ->call('stage')
        ->assertSee(__('import::import.columns.title'))
        ->assertSet('mapping.name', '0')->assertSet('mapping.phone', '2')->assertSet('callingCode', '20')
        ->call('check')
        ->assertSee(__('import::import.status.new'))->assertSee(__('import::rows.errors.contact'))
        ->call('import')
        ->assertSee(__('import::import.done.title', ['file' => 'teachers.csv']));

    expect(User::query()->where('email', 'sara.m@example.com')->exists())->toBeTrue();
    $page->call('problems')->assertFileDownloaded('import-problems-teachers.xlsx');

    $accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->actingAs($accountant)->get('http://noor.lms.test/admin/settings/import')->assertForbidden();
});
