<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;

uses(EnrolledStudent::class, QuranProgram::class);

/*
 * GET learning/journey: what a student's own home shows in the app, in each
 * stage's look (stars, points and rank, streak, badges, each course's levels
 * with every lesson's state). The learner is the person's own, or one they
 * follow; anyone else is not found.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    app(EnrollmentApi::class)->attachCurriculum($this->booked->enrollmentId, $this->publishQuranProgram($this->quran)->id);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->stranger = User::factory()->withRole(Role::Guardian)->create();

    // Two lessons taught: Lesson 1 mastered, Lesson 2 mastered and Lesson 3 introduced.
    foreach ([[[0, 'mastered']], [[1, 'mastered'], [2, 'introduced']]] as $n => $items) {
        $session = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->skip($n)->firstOrFail();
        $session->update(['status' => 'completed']);
        event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
        app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $session->id)->firstOrFail(), $this->teacher->id, [
            'engagement' => 4, 'overall' => 4,
            'items' => array_map(fn ($i) => ['node_id' => $this->lessons[$i[0]], 'outcome' => $i[1]], $items),
        ]);
    }
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function journeyApp(User $user): string
{
    test()->actAsTenant(test()->academy);
    $token = $user->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();

    return $token;
}

it('gives a student their own journey, in their language and on their clock', function () {
    $journey = $this->withToken(journeyApp($this->student))->withHeaders(['Accept-Language' => 'ar', 'X-Timezone' => 'Asia/Riyadh'])
        ->getJson('/api/v1/learning/journey')
        ->assertOk()
        ->assertJsonPath('data.learner_id', $this->student->id)
        ->assertJsonPath('data.stars', 2)
        ->assertJsonPath('data.streak', 2)
        ->assertJsonPath('data.points', 2 * 100 + 2 * 20)
        ->assertJsonPath('data.rank.key', 'beginner')
        ->assertJsonPath('data.rank.label', __('learning::journey.ranks.beginner', [], 'ar'))
        ->json('data');

    expect(collect($journey['badges'])->firstWhere('key', 'first_star'))->toMatchArray(['earned' => true, 'name' => __('learning::journey.badges.first_star', [], 'ar')[0]]);

    $course = $journey['courses'][0];
    expect($course['enrollment_id'])->toBe($this->booked->enrollmentId)
        ->and($course['worlds'][0]['state'])->toBe('here')
        ->and(array_slice(array_column($course['worlds'][0]['lessons'], 'state'), 0, 3))->sequence(
            fn ($s) => $s->toBeIn(['done', 'review']),
            fn ($s) => $s->toBeIn(['done', 'review']),
            fn ($s) => $s->toBe('here'),
        )
        ->and($course['next'][0]['id'])->toBe($this->lessons[2])
        ->and($course['finish_on']['timezone'])->toBe('Asia/Riyadh');
});

it('gives a guardian their child\'s journey, and nobody else\'s', function () {
    $token = journeyApp($this->guardian);
    $this->withToken($token)->getJson('/api/v1/learning/journey')->assertOk()->assertJsonPath('data.learner_id', $this->student->id);
    $this->withToken($token)->getJson('/api/v1/learning/journey?learner_id='.$this->student->id)->assertOk();
    $this->withToken($token)->getJson('/api/v1/learning/journey?learner_id='.$this->stranger->id)->assertNotFound();

    $this->withToken(journeyApp($this->stranger))->getJson('/api/v1/learning/journey')->assertNotFound();
});
