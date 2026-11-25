<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Messaging\Filament\Teacher\TeacherMessages;
use Modules\Messaging\MessagingRules;
use Modules\Platform\Models\Academy;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The teacher hears that guardians read along only when the student has
 * some, and an adults academy's conversations speak of trainers and
 * trainees, never of a family (docs/architecture/02, sections 17 and 29).
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app()->setLocale('ar');
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('tells the teacher the guardians will read it only when the student has guardians', function () {
    Livewire::test(TeacherMessages::class)->call('pick', $this->student->id)
        ->assertSee(__('messaging::screens.first.teacher'))->assertDontSee(__('messaging::screens.first.teacher_alone'));

    DB::table('guardian_student')->where('student_id', $this->student->id)->delete();
    app()->forgetInstance(MessagingRules::class);
    Livewire::test(TeacherMessages::class)->call('pick', $this->student->id)
        ->assertSee('ابدأ المحادثة مع الطالب.')->assertDontSee(__('messaging::screens.first.teacher'))
        ->assertSeeHtml('placeholder="اكتب رسالة للطالب…"');
});

it('speaks of trainers and trainees in an adults academy', function () {
    DB::table('guardian_student')->where('student_id', $this->student->id)->delete();
    DB::connection('pgsql_platform')->table('academies')->where('id', $this->academy->id)->update(['audience' => 'adults']);
    $this->actAsTenant(Academy::query()->findOrFail($this->academy->id)->toTenantRef());

    Livewire::test(TeacherMessages::class)->call('pick', $this->student->id)
        ->assertSee('ابدأ المحادثة مع المتدرّب.')
        ->assertSee('الأكاديمية تراجع المحادثات.')
        ->assertDontSee('ولي')
        ->assertSeeHtml('placeholder="اكتب رسالة للمتدرّب…"');
});
