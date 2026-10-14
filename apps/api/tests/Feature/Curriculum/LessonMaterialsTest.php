<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Actions\Resources\MoveNodeResource;
use Modules\Curriculum\Actions\Resources\RemoveNodeResource;
use Modules\Curriculum\Actions\Resources\UpdateNodeResource;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Models\User;
use Modules\Learning\Contracts\LearningApi;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\TestCase;

uses(EnrolledStudent::class, QuranProgram::class);

beforeEach(function () {
    Storage::fake('local');
    $this->enrolStudentOnPlan();
    $this->program = $this->publishQuranProgram($this->quran);
    $this->enrollmentId = $this->booked->enrollmentId;
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, Program::query()->value('id'));
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->first = Node::query()->findOrFail($this->lessons[0]);
});

function lrPdf(string $name = 'Al-Fatiha page.pdf', int $kb = 120): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, 'application/pdf');
}

function lrAdd(Node $lesson, array $data, ?UploadedFile $file = null): NodeResource
{
    return app(AddNodeResource::class)->handle($lesson, $data, $file, test()->admin->id);
}

function lrRefusal(Closure $run): ?string
{
    try {
        $run();
    } catch (ValidationException $e) {
        return array_key_first($e->errors());
    }

    return null;
}

it('keeps files and links on a lesson, in order, each for everyone or for teachers only', function () {
    $pdf = lrAdd($this->first, ['kind' => 'file', 'title_ar' => 'صفحة الفاتحة'], lrPdf());
    $audio = lrAdd($this->first, ['kind' => 'file', 'audience' => 'all'], UploadedFile::fake()->create('fatiha-recitation.mp3', 900, 'audio/mpeg'));
    $link = lrAdd($this->first, ['kind' => 'link', 'title_en' => 'Recitation video', 'url' => 'https://www.youtube.com/watch?v=abc']);
    $guide = lrAdd($this->first, ['kind' => 'file', 'title_ar' => 'دليل المدرس', 'audience' => 'teachers'], lrPdf('guide.pdf'));

    expect(NodeResource::query()->where('node_id', $this->first->id)->orderBy('ordinal')->pluck('id')->all())->toBe([$pdf->id, $audio->id, $link->id, $guide->id])
        ->and($pdf->type())->toBe('pdf')->and($audio->type())->toBe('audio')->and($link->type())->toBe('link')
        ->and([$pdf->title_ar, $pdf->title_en])->toBe(['صفحة الفاتحة', 'صفحة الفاتحة'])            // one language stands in for the other
        ->and([$audio->title_ar, $audio->title_en])->toBe(['fatiha-recitation', 'fatiha-recitation']) // no name: the file's
        ->and($pdf->file_path)->toStartWith("academies/{$this->first->academy_id}/curriculum/{$this->first->id}/")
        ->and(Storage::disk('local')->exists($pdf->file_path))->toBeTrue()
        ->and(app(UsageMeters::class)->measure(Limit::StorageMb, UsagePeriod::monthOf(CarbonImmutable::now(), 'UTC')))->toBe(2);   // 1.1 MB of files, counted in whole MB up

    app(MoveNodeResource::class)->handle($link, 'up');
    app(MoveNodeResource::class)->handle($pdf, 'up');     // already first: stays
    expect(NodeResource::query()->where('node_id', $this->first->id)->orderBy('ordinal')->pluck('id')->all())->toBe([$pdf->id, $link->id, $audio->id, $guide->id]);

    app(UpdateNodeResource::class)->handle($link, ['title_ar' => 'فيديو التلاوة', 'title_en' => 'Recitation', 'audience' => 'teachers', 'url' => 'https://youtu.be/xyz']);
    expect($link->refresh()->only(['title_ar', 'audience', 'url']))->toBe(['title_ar' => 'فيديو التلاوة', 'audience' => 'teachers', 'url' => 'https://youtu.be/xyz']);

    app(RemoveNodeResource::class)->handle($pdf);
    expect(NodeResource::query()->find($pdf->id))->toBeNull()->and(Storage::disk('local')->exists($pdf->file_path))->toBeFalse();
});

/** A real upload whose bytes PHP cannot name: only the browser's word says what it is. */
function lrUntold(string $name, string $claimed): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'lr');
    file_put_contents($path, '%PDF-1.4 fake');

    return new class($path, $name, $claimed, null, true) extends UploadedFile
    {
        public function getMimeType(): ?string
        {
            return null;
        }
    };
}

it('refuses what a lesson cannot take', function () {
    $level = Node::query()->where('kind', 'level')->firstOrFail();

    expect(lrRefusal(fn () => lrAdd($level, ['kind' => 'link', 'title_en' => 'x', 'url' => 'https://a.test'])))->toBe('node')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'file'], UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'))))->toBe('file')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'file'], lrPdf('big.pdf', 21 * 1024))))->toBe('file')
        // A file whose type its bytes do not tell is refused, whatever the browser said it was.
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'file'], lrUntold('notes.pdf', 'application/pdf'))))->toBe('file')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'link', 'title_en' => 'x', 'url' => 'javascript:alert(1)'])))->toBe('url')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'link', 'title_en' => 'x', 'url' => 'ftp://files.test/a'])))->toBe('url')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'link', 'url' => 'https://a.test'])))->toBe('title_ar')
        ->and(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'link', 'title_en' => 'x', 'url' => 'https://a.test', 'audience' => 'parents'])))->toBe('audience');

    foreach (range(1, AddNodeResource::MAX_PER_LESSON) as $i) {
        lrAdd($this->first, ['kind' => 'link', 'title_en' => "Page {$i}", 'url' => "https://a.test/{$i}"]);
    }
    expect(lrRefusal(fn () => lrAdd($this->first, ['kind' => 'link', 'title_en' => 'one more', 'url' => 'https://a.test/13'])))->toBe('node')
        ->and(NodeResource::query()->count())->toBe(AddNodeResource::MAX_PER_LESSON)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('takes a draft lesson\'s files with it when the lesson goes', function () {
    $draft = Program::query()->firstOrFail()->workingVersion();
    $draft->update(['status' => 'draft']);
    $lesson = app(SaveNode::class)->handle($draft, null, ['kind' => 'lesson', 'parent_id' => $this->first->parent_id, 'title_ar' => 'جديد', 'title_en' => 'New']);
    $file = lrAdd($lesson, ['kind' => 'file'], lrPdf());

    app(DeleteNode::class)->handle($lesson);
    expect(NodeResource::query()->find($file->id))->toBeNull()->and(Storage::disk('local')->exists($file->file_path))->toBeFalse();
});

/** Signs in through the API as this person. */
function lrApiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => $user->email, 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

it('shows the family the lesson\'s materials, and its teacher the teachers\' too, through signed links', function () {
    lrAdd($this->first, ['kind' => 'file', 'title_en' => 'Page'], lrPdf());
    lrAdd($this->first, ['kind' => 'link', 'title_en' => 'Video', 'url' => 'https://www.youtube.com/watch?v=abc']);
    lrAdd($this->first, ['kind' => 'file', 'title_en' => 'Guide', 'audience' => 'teachers'], lrPdf('guide.pdf'));
    $this->clearTenant();

    $family = lrApiAs($this->guardian)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/roadmap")->assertOk();
    $materials = $family->json('data.levels.0.units.0.lessons.0.materials');
    expect(array_column($materials, 'title'))->toBe(['Page', 'Video'])
        ->and($materials[1]['url'])->toBe('https://www.youtube.com/watch?v=abc')
        ->and($materials[0]['type'])->toBe('pdf')
        ->and($materials[0]['url'])->toContain('/materials/')->toContain('signature=');

    $teacher = lrApiAs($this->teacher)->getJson("/api/v1/learning/enrollments/{$this->enrollmentId}/roadmap")->assertOk();
    expect(array_column($teacher->json('data.levels.0.units.0.lessons.0.materials'), 'title'))->toBe(['Page', 'Video', 'Guide'])
        ->and($teacher->json('data.levels.0.units.0.lessons.0.materials.2.teachers_only'))->toBeTrue();

    // The signed address opens the file without signing in, on the academy's host; changed, or late, it does not.
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $path = parse_url($materials[0]['url'], PHP_URL_PATH).'?'.parse_url($materials[0]['url'], PHP_URL_QUERY);
    $this->get('http://noor.lms.test'.$path)->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('http://noor.lms.test'.str_replace('signature=', 'signature=0', $path))->assertForbidden();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(13));
    $this->get('http://noor.lms.test'.$path)->assertForbidden();
    CarbonImmutable::setTestNow();
});

it('puts the plan\'s materials beside the video: all of them for the teacher, everyone\'s for the student', function () {
    lrAdd($this->first, ['kind' => 'file', 'title_en' => 'Fatiha page'], lrPdf());
    lrAdd($this->first, ['kind' => 'file', 'title_en' => 'Teacher guide', 'audience' => 'teachers'], lrPdf('guide.pdf'));

    $lesson = Session::query()->where('enrollment_id', $this->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $teacherPlan = app(LearningApi::class)->planFor($this->enrollmentId, $lesson->starts_at);
    $studentPlan = app(LearningApi::class)->planFor($this->enrollmentId, $lesson->starts_at, forTeacher: false);
    expect(array_map(fn ($m) => $m->title, $teacherPlan[0]->materials))->toBe(['Fatiha page', 'Teacher guide'])
        ->and(array_map(fn ($m) => $m->title, $studentPlan[0]->materials))->toBe(['Fatiha page']);

    $this->clearTenant();
    $this->actingAs($this->teacher)->get("http://noor.lms.test/class/{$lesson->id}")->assertOk()
        ->assertSee('Fatiha page')->assertSee('Teacher guide');
    app('auth')->forgetGuards();
    $this->actingAs($this->student)->get("http://noor.lms.test/class/{$lesson->id}")->assertOk()
        ->assertSee(__('classroom::classroom.materials.title'))->assertSee('Fatiha page')->assertDontSee('Teacher guide');
});

it('lets the curriculum managers put materials on a lesson from the builder', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->call('toggleMaterials', $this->first->id)->assertSet('openLesson', $this->first->id)
        ->callAction('addMaterial', ['url' => 'https://quran.com/1', 'title_ar' => 'الفاتحة على quran.com', 'audience' => 'all'], ['node' => $this->first->id, 'kind' => 'link'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('curriculum::curriculum.resources.added'))
        ->assertSee('الفاتحة على quran.com');

    expect(NodeResource::query()->where('node_id', $this->first->id)->value('url'))->toBe('https://quran.com/1');

    $file = lrAdd($this->first, ['kind' => 'file'], lrPdf())->id;
    $this->clearTenant();
    $this->get("http://noor.lms.test/curriculum/resources/{$file}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    app('auth')->forgetGuards();
    $this->actingAs($this->teacher)->get("http://noor.lms.test/curriculum/resources/{$file}")->assertForbidden();
});
