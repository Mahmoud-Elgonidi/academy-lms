<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Filament\Pages\LeadPage;
use Modules\Crm\Models\Lead;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\SendMessage;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\ApproveErasure;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Actions\SweepDataRequests;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\Safeguarding\Actions\AcknowledgeConcern;
use Modules\Safeguarding\Actions\CloseConcern;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Privacy\PersonalDataSource;
use Modules\Support\Actions\OpenTicket;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Every personal data source registered with PersonalData, at once
 * (docs/architecture/03, section 11, "حقوق أصحاب البيانات"): a student, their
 * guardian (who pays) and their teacher each hold something in every source
 * that concerns them. Each source must show in the copy of whoever it is
 * about, hold an erasure back for its own reasons only, and once erased
 * remove or blank what it says it does, keeping the rest without the name.
 * After the three are erased, no table anywhere still carries their email
 * address or mobile number. A source registered without a line in
 * PRIVACY_COVERAGE fails here, so a new module's data is never forgotten.
 */

/**
 * Each source: whose copy it shows in, and whether erasing removes or blanks
 * something (true) or keeps the record as the law or the academy needs it
 * (false). Imports keep no copy on purpose: a spreadsheet row is a copy of
 * what the account itself holds.
 */
const PRIVACY_COVERAGE = [
    'account' => ['copy' => ['student', 'guardian', 'teacher'], 'erases' => true],
    'profile' => ['copy' => ['student', 'guardian', 'teacher'], 'erases' => true],
    'billing' => ['copy' => ['guardian'], 'erases' => false],
    'recordings' => ['copy' => ['student', 'guardian'], 'erases' => false],
    'messages' => ['copy' => ['student', 'guardian', 'teacher'], 'erases' => true],
    'enquiries' => ['copy' => ['student', 'guardian'], 'erases' => true],
    'courses' => ['copy' => ['student', 'teacher'], 'erases' => false],
    'ratings' => ['copy' => ['student'], 'erases' => true],
    'games' => ['copy' => ['student'], 'erases' => true],
    'imports' => ['copy' => [], 'erases' => true],
    'learning' => ['copy' => ['student', 'guardian'], 'erases' => true],
    'messaging' => ['copy' => ['student', 'guardian', 'teacher'], 'erases' => false],
    'earnings' => ['copy' => ['teacher'], 'erases' => false],
    'safeguarding' => ['copy' => ['student'], 'erases' => false],
    'lessons' => ['copy' => ['student', 'teacher'], 'erases' => true],
    'support' => ['copy' => ['student'], 'erases' => false],
    // What the office sent: in the sender's copy (AnnouncementsTest), none of these three's; kept on erasure.
    'announcements' => ['copy' => [], 'erases' => false],
    // What the guardian asked from the academy's page with her number, before she had an account.
    'site_requests' => ['copy' => ['guardian'], 'erases' => true],
];

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    Storage::fake('local');
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    // Names, addresses and numbers no other row shares, to look for them everywhere afterwards.
    $this->student->forceFill(['name' => 'Zubaida Qasr', 'email' => 'zubaida.qasr@noor.example', 'phone_e164' => '+966511122233'])->save();
    $this->guardian->forceFill(['name' => 'Marwan Qasr', 'email' => 'marwan.qasr@noor.example', 'phone_e164' => '+966511122244'])->save();
    $this->teacher->forceFill(['name' => 'Fadwa Lutfi', 'email' => 'fadwa.lutfi@noor.example', 'phone_e164' => '+201011122255'])->save();
    $this->people = ['student' => $this->student, 'guardian' => $this->guardian, 'teacher' => $this->teacher];
    pdsSeedEverySource($this);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** One row (or file) of each kind for the student, their guardian and their teacher. */
function pdsSeedEverySource(object $t): void
{
    [$s, $g, $tr] = [$t->student, $t->guardian, $t->teacher];
    $now = CarbonImmutable::now();
    $id = fn () => (string) Str::uuid7();
    $academy = $t->academy->id;
    $lesson = DB::table('sessions')->where('teacher_id', $tr->id)->orderBy('starts_at')->value('id');
    $file = function (string $folder, string $name) use ($academy): string {
        $path = "academies/{$academy}/{$folder}/".Str::uuid7().'-'.$name;
        Storage::disk('local')->put($path, 'the bytes of '.$name);

        return $path;
    };

    // account: a device signed in and Google linked.
    $s->createToken('phone');
    DB::table('social_identities')->insert(['id' => $id(), 'user_id' => $s->id, 'provider' => 'google', 'subject' => 'g-zubaida', 'email' => 'zubaida.qasr@gmail.example', 'via' => 'settings', 'linked_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

    // profile: the teacher's vetting scan (the student's profile and the guardian link come with the fixture).
    $t->checkScan = $file('teacher-checks', 'record.jpg');
    DB::table('teacher_checks')->insert(['id' => $id(), 'teacher_id' => $tr->id, 'kind' => 'criminal_record', 'reference' => 'CR-778899', 'uploaded_by' => $tr->id,
        'file_path' => $t->checkScan, 'file_name' => 'record.jpg', 'created_at' => $now, 'updated_at' => $now]);
    // ...and the teacher's card on the academy's page, in her own words.
    DB::table('teacher_public_cards')->insert(['id' => $id(), 'teacher_id' => $tr->id, 'display_name' => 'Fadwa Lutfi', 'bio' => 'Twenty years teaching, reach me on +201011122255',
        'shown' => true, 'created_at' => $now, 'updated_at' => $now]);

    // billing: a receipt the guardian sent for the course's invoice (the invoice comes with the fixture).
    $t->receipt = $file('payment-proofs', 'transfer.jpg');
    DB::table('manual_payment_proofs')->insert(['id' => $id(), 'invoice_id' => $t->booked->invoice->id, 'payer_id' => $g->id, 'channel' => 'family', 'method' => 'bank_transfer',
        'amount_minor' => 1000, 'currency' => 'SAR', 'paid_on' => $now->toDateString(), 'reference' => 'TRX-1', 'file_path' => $t->receipt, 'file_name' => 'transfer.jpg',
        'mime' => 'image/jpeg', 'size' => 20, 'created_at' => $now, 'updated_at' => $now]);

    // recordings: the guardian agreed for the student.
    DB::table('recording_consents')->insert(['id' => $id(), 'person_id' => $s->id, 'role' => 'student', 'via' => 'guardian', 'given_by' => $g->id, 'version' => 1, 'granted_at' => $now, 'created_at' => $now]);

    // messages: each one's inbox (with where it went), and the student's phone.
    foreach ([$s, $g, $tr] as $person) {
        $note = $id();
        DB::table('notifications')->insert(['id' => $note, 'user_id' => $person->id, 'template_key' => 'test.hello', 'category' => 'lessons', 'title' => 'Hello', 'body' => 'Your lesson moved.', 'created_at' => $now]);
        DB::table('notification_deliveries')->insert(['id' => $id(), 'notification_id' => $note, 'channel' => 'email', 'status' => 'sent', 'address' => $person->email, 'created_at' => $now, 'updated_at' => $now]);
    }
    DB::table('push_devices')->insert(['id' => $id(), 'user_id' => $s->id, 'platform' => 'android', 'token' => 'push-zubaida', 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

    // enquiries: the guardian asked about the student before they joined.
    $lead = $id();
    DB::table('leads')->insert(['id' => $lead, 'contact_name' => 'Marwan Qasr', 'phone_e164' => '+966511122244', 'email' => 'marwan.qasr@noor.example', 'timezone' => 'Asia/Riyadh', 'source' => 'website',
        'learner_name' => 'Zubaida Qasr', 'learner_id' => $s->id, 'contact_id' => $g->id, 'notes' => 'Prefers evenings', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('lead_activities')->insert(['id' => $id(), 'lead_id' => $lead, 'kind' => 'note', 'body' => 'Called Marwan on +966511122244']);

    // site_requests: the guardian asked for a free trial from the academy's page, with the number her account has now.
    DB::table('site_requests')->insert(['id' => $id(), 'kind' => 'trial', 'name' => 'Marwan Qasr', 'phone' => '+966511122244', 'for_child' => true, 'child_name' => 'Zubaida',
        'message' => 'Evenings, please', 'created_at' => $now, 'updated_at' => $now]);

    // ratings: the student rated a lesson, with words.
    DB::table('lesson_ratings')->insert(['id' => $id(), 'session_id' => $lesson, 'rater_id' => $s->id, 'student_id' => $s->id, 'teacher_id' => $tr->id, 'stars' => 4, 'tags' => '["patient"]', 'comment' => 'Fadwa is kind', 'created_at' => $now, 'updated_at' => $now]);

    // games: a round, a creature with its name, the guardian's limits.
    DB::table('game_plays')->insert(['id' => $id(), 'student_id' => $s->id, 'played_by' => $s->id, 'game_id' => $id(), 'game_key' => 'shell-sort', 'version_id' => $id(), 'focus' => 'free',
        'items' => '[]', 'started_at' => $now, 'total' => 3]);
    DB::table('creature_finds')->insert(['id' => $id(), 'student_id' => $s->id, 'creature_key' => 'owl', 'source' => 'stars', 'egg' => 1, 'nickname' => 'Hoot', 'found_at' => $now]);
    DB::table('game_limits')->insert(['student_id' => $s->id, 'updated_by' => $g->id, 'daily_minutes' => 30, 'created_at' => $now, 'updated_at' => $now]);

    // imports: the spreadsheet row the student came from.
    $run = $id();
    DB::table('import_runs')->insert(['id' => $run, 'kind' => 'students', 'file_name' => 'students.csv', 'headers' => '["name","email"]', 'created_by' => $t->admin->id, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('import_rows')->insert(['id' => $id(), 'run_id' => $run, 'line' => 2, 'cells' => '["Zubaida Qasr","zubaida.qasr@noor.example"]', 'refs' => json_encode(['student' => $s->id]), 'created_at' => $now, 'updated_at' => $now]);

    // learning: homework handed in with a file, a challenge, practice, the guardian's cheer, and a weekly note.
    $homework = $id();
    DB::table('homework')->insert(['id' => $homework, 'teacher_id' => $tr->id, 'student_id' => $s->id, 'enrollment_id' => $t->booked->enrollmentId, 'title' => 'Read Al-Fatiha', 'due_at' => $now->addDays(3), 'created_at' => $now, 'updated_at' => $now]);
    $t->handedIn = $file("homework/{$homework}", 'reading.m4a');
    DB::table('homework_submissions')->insert(['id' => $id(), 'homework_id' => $homework, 'body' => 'I read it twice', 'file_path' => $t->handedIn, 'file_name' => 'reading.m4a', 'submitted_by' => $s->id, 'submitted_at' => $now]);
    DB::table('challenge_claims')->insert(['id' => $id(), 'student_id' => $s->id, 'challenge' => 'chest', 'period' => $now->toDateString(), 'points' => 5, 'claimed_at' => $now]);
    DB::table('practice_rounds')->insert(['id' => $id(), 'student_id' => $s->id, 'game' => 'shell-sort', 'focus' => 'free', 'correct' => 2, 'total' => 3, 'stars' => 2, 'seconds' => 40, 'played_by' => $s->id, 'played_at' => $now]);
    DB::table('cheers')->insert(['id' => $id(), 'student_id' => $s->id, 'from_id' => $g->id, 'kind' => 'clap', 'created_at' => $now]);
    DB::table('weekly_digests')->insert(['id' => $id(), 'student_id' => $s->id, 'week_start' => $now->toDateString(), 'status' => 'sent', 'source' => 'facts',
        'text_ar' => 'أسبوع {child} في الأكاديمية: الحصص: 2 حضور.', 'text_en' => "{child}'s week at the academy: Lessons: 2 attended.",
        'facts' => json_encode(['notes' => ['{child} read it twice']]), 'sent_to' => 1, 'created_at' => $now, 'sent_at' => $now]);
    DB::table('hifz_pages')->insert(['id' => $id(), 'student_id' => $s->id, 'page' => 582, 'state' => 'memorized', 'stability' => 10, 'difficulty' => 5,
        'due_at' => $now->addDays(10), 'last_reviewed_at' => $now, 'reviews' => 1, 'lapses' => 0, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('hifz_reviews')->insert(['id' => $id(), 'student_id' => $s->id, 'page' => 582, 'grade' => 3, 'source' => 'teacher', 'source_id' => $id(), 'reviewed_at' => $now, 'created_at' => $now]);
    DB::table('hifz_recitations')->insert(['id' => $id(), 'student_id' => $s->id, 'page' => 582, 'status' => 'ready', 'grade' => 3, 'recited_by' => $g->id,
        'result' => json_encode(['summary' => ['words' => 10, 'ok' => 9, 'missing' => 1, 'wrong' => 0, 'extra' => 0]]), 'created_at' => $now]);
    DB::table('recitation_mistakes')->insert(['id' => $id(), 'student_id' => $s->id, 'session_id' => $lesson, 'teacher_id' => $tr->id, 'surah' => 78, 'verse' => 3, 'word' => 2,
        'kind' => 'hesitation', 'created_at' => $now, 'updated_at' => $now]);

    // messaging: the student, the guardian and the teacher talk; the student typed a number.
    test()->actingAs($s);
    $talk = app(OpenConversation::class)->handle($s->id, $tr->id, $s->id);
    app(SendMessage::class)->handle($talk, $s->id, 'Call me on 0511122233 please');
    app(SendMessage::class)->handle($talk, $g->id, 'Thank you, Fadwa');
    app(SendMessage::class)->handle($talk, $tr->id, 'See you on Tuesday');

    // earnings: the teacher's bonus.
    DB::table('teacher_earnings')->insert(['id' => $id(), 'teacher_id' => $tr->id, 'period' => '2026-10-01', 'kind' => 'bonus', 'amount_minor' => 5000, 'currency' => 'SAR', 'note' => 'Great month', 'created_at' => $now]);

    // safeguarding: the student raised a concern (still open: it holds an erasure back).
    $t->concern = app(ReportConcern::class)->handle($s->id, 'family', $s->id, 'other', 'Something in a lesson worried me');

    // lessons: a dispute in the student's words, the teacher's time off with a note, a held time.
    DB::table('session_disputes')->insert(['id' => $id(), 'session_id' => $lesson, 'raised_by' => $s->id, 'side' => 'family', 'reason' => 'other', 'note' => 'Fadwa left after ten minutes', 'outcome_before' => 'completed', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('teacher_time_off')->insert(['id' => $id(), 'teacher_id' => $tr->id, 'starts_at' => $now->addMonths(2), 'ends_at' => $now->addMonths(2)->addDays(2), 'timezone' => 'Africa/Cairo', 'reason' => 'personal', 'note' => 'My sister\'s wedding', 'requested_by' => $tr->id, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('booking_holds')->insert(['id' => $id(), 'batch_id' => $id(), 'teacher_id' => $tr->id, 'student_id' => $s->id, 'starts_at' => $now->addMonths(3), 'ends_at' => $now->addMonths(3)->addMinutes(30), 'expires_at' => $now->addMinutes(10), 'created_by' => $t->admin->id]);

    // support: the student's ticket.
    app(OpenTicket::class)->handle($s->id, 'family', 'other', 'A question', 'How do I see my homework?', $s->id);

    // announcements: the owner told the families (each one's copy of it is in their messages).
    DB::table('announcements')->insert(['id' => $id(), 'title' => 'Eid holiday', 'body' => 'Closed on Tuesday.', 'audience' => 'families', 'channels' => '["push"]',
        'recipients_count' => 2, 'sent_by' => $t->owner->id, 'sent_at' => $now]);
}

/** @return array<string, PersonalDataSource> */
function pdsSources(): array
{
    return collect(app(PersonalData::class)->sources())->keyBy(fn (PersonalDataSource $s) => $s->key())->all();
}

it('covers every registered source here', function () {
    expect(array_keys(pdsSources()))->toEqualCanonicalizing(array_keys(PRIVACY_COVERAGE));
});

it('shows each source in the copy of whoever it is about, with the files they gave', function () {
    foreach (pdsSources() as $key => $source) {
        foreach ($this->people as $who => $person) {
            $shown = ! $source->export($person->id)->isEmpty();
            expect($shown)->toBe(in_array($who, PRIVACY_COVERAGE[$key]['copy'], true), "{$key} in the {$who}'s copy");
        }
    }

    // The teacher's card on the academy's page is in her copy.
    expect(pdsSources()['profile']->export($this->teacher->id)->data['public_card']['display_name'])->toBe('Fadwa Lutfi');

    // The ZIP: every part, and the files the person gave.
    foreach (['student' => [$this->handedIn], 'guardian' => [$this->receipt], 'teacher' => [$this->checkScan]] as $who => $files) {
        $this->actingAs($this->owner);
        $copy = app(RequestDataExport::class)->handle($this->people[$who]->id, $this->owner->id, 'staff')->refresh();
        expect($copy->status)->toBe('ready');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($copy->file_path));
        $parts = array_keys(json_decode((string) $zip->getFromName('personal-data.json'), true)['parts']);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $expected = array_keys(array_filter(PRIVACY_COVERAGE, fn (array $c) => in_array($who, $c['copy'], true)));
        expect($parts)->toEqualCanonicalizing($expected, "the {$who}'s copy");
        expect(count(array_filter($names, fn (string $n) => str_starts_with($n, 'files/'))))->toBeGreaterThanOrEqual(count($files), "the {$who}'s files");
    }
});

it('holds an erasure back for each source\'s own reasons, and erases once they are gone', function () {
    $sources = pdsSources();
    // An owner alone, and a child with no way in of their own whose only guardian is this one.
    $child = User::factory()->withRole(Role::Student)->create(['is_managed_minor' => true]);
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $child->id);

    $blocks = fn (string $userId) => collect($sources)->map(fn (PersonalDataSource $s) => $s->blockers($userId) !== [])->filter()->keys()->sort()->values()->all();
    expect($blocks($this->student->id))->toBe(['courses', 'lessons', 'safeguarding'])
        ->and($blocks($this->guardian->id))->toBe(['billing', 'profile'])
        ->and($blocks($this->teacher->id))->toBe(['courses', 'lessons'])
        ->and($blocks($this->owner->id))->toBe(['account']);

    // The academy cannot approve while anything holds it back.
    $this->actingAs($this->owner);
    $asked = collect($this->people)->map(fn (User $p) => app(RequestErasure::class)->handle($p->id, $this->owner->id, 'staff', 'Leaving.'));
    foreach ($asked as $who => $request) {
        expect(fn () => app(ApproveErasure::class)->handle($request, $this->owner->id))->toThrow(ValidationException::class, null, "{$who} held back");
    }

    // What held each back is settled: the course ends, the lessons ahead are cancelled, the concern is closed,
    // the invoice is paid, and the child has another guardian.
    DB::table('enrollments')->update(['status' => 'ended', 'ended_at' => now()]);
    DB::table('sessions')->where('starts_at', '>', now())->update(['status' => 'cancelled']);
    app(AcknowledgeConcern::class)->handle($this->concern->id, $this->owner->id);
    app(CloseConcern::class)->handle($this->concern->id, 'resolved', 'Thank you for telling us.', 'Spoke to everyone.', $this->owner->id);
    $invoice = $this->booked->invoice;
    app(RecordPayment::class)->handle($invoice->id, 'bank_transfer', Money::of($invoice->total->minor, $invoice->total->currency), 'TRX-2', CarbonImmutable::now(), null, $this->owner->id);
    app(PeopleApi::class)->linkGuardian(User::factory()->withRole(Role::Guardian)->create()->id, $child->id);
    foreach ($this->people as $who => $person) {
        expect(app(ErasurePreview::class)->blockers($person->id))->toBe([], "{$who} free to go");
    }

    // Erased a day after approval, the account last.
    $before = pdsSnapshot($this->people);
    foreach ($asked as $request) {
        app(ApproveErasure::class)->handle($request->refresh(), $this->owner->id);
    }
    $this->travel(25)->hours();
    expect(app(SweepDataRequests::class)->handle()['erased'])->toBe(3);
    foreach ($asked as $who => $request) {
        $done = DataRequest::query()->find($request->id);
        expect($done->status)->toBe('done', "{$who} erased");
        foreach (array_keys(array_filter(PRIVACY_COVERAGE, fn (array $c) => ! $c['erases'])) as $kept) {
            expect($done->summary['removed'] ?? [])->not->toHaveKey($kept, "{$kept} keeps everything of the {$who}");
        }
    }

    // What each source said it removes or blanks is gone; what it keeps is still there.
    $after = pdsSnapshot($this->people);
    expect($after['account'])->toBe(['erased', 'erased', 'erased', 0, 0])
        ->and($after['profile'])->toBe([null, null, 0, 0, null, false, 0])
        ->and($after['messages'])->toBe([0, 0, 0])
        ->and($after['enquiries'])->toBe([null, null, null, null, null])
        ->and($after['ratings'])->toBe([null, '[]', 4])
        ->and($after['games'])->toBe([0, 0, 0])
        ->and($after['imports'])->toBe('{}')
        ->and($after['learning'])->toBe([0, 0, 0, 1, 1, 0, 0])
        ->and($after['lessons'])->toBe(['', null, 0])
        ->and(Storage::disk('local')->exists($this->handedIn))->toBeFalse()
        ->and(Storage::disk('local')->exists($this->checkScan))->toBeFalse();
    foreach (['billing', 'recordings', 'courses', 'messaging', 'earnings', 'safeguarding', 'support', 'announcements'] as $kept) {
        expect($after[$kept])->toBe($before[$kept], "{$kept} keeps its records");
    }
    expect(Storage::disk('local')->exists($this->receipt))->toBeTrue();

    // The lead stays in the sales figures without a number: its page still opens, and makes no account.
    $lead = Lead::query()->where('learner_id', $this->student->id)->sole();
    Filament::setCurrentPanel('academy');
    Livewire::test(LeadPage::class, ['lead' => $lead->id])->assertOk()->assertDontSee('+966511122244');
    $lead->forceFill(['learner_id' => null])->save();
    expect(fn () => app(PrepareLearner::class)->handle($lead->refresh(), $this->owner->id))->toThrow(ValidationException::class);

    // No table anywhere still carries their names, addresses or numbers: the audit log's entries stay,
    // under the erased account's name.
    expect(pdsWhereFound(['zubaida.qasr@noor.example', 'marwan.qasr@noor.example', 'fadwa.lutfi@noor.example', 'zubaida.qasr@gmail.example',
        '+966511122233', '+966511122244', '+201011122255', '0511122233', 'Zubaida Qasr', 'Marwan Qasr', 'Fadwa Lutfi']))->toBe([])
        ->and(DB::table('audit_logs')->whereIn('actor_id', collect($this->people)->pluck('id'))->exists())->toBeTrue();
});

/** What each source holds of the three, to compare before and after. */
function pdsSnapshot(array $people): array
{
    [$s, $g, $t] = [$people['student']->id, $people['guardian']->id, $people['teacher']->id];
    $ids = [$s, $g, $t];

    return [
        'account' => [...DB::table('users')->whereIn('id', $ids)->orderBy('id')->pluck('status')->all(),
            DB::table('personal_access_tokens')->whereIn('tokenable_id', $ids)->count(), DB::table('social_identities')->whereIn('user_id', $ids)->count()],
        'profile' => [DB::table('student_profiles')->where('user_id', $s)->value('birth_date'), DB::table('teacher_profiles')->where('user_id', $t)->value('gender'),
            DB::table('guardian_student')->whereIn('guardian_id', $ids)->orWhereIn('student_id', $ids)->count(),
            DB::table('teacher_checks')->where('teacher_id', $t)->whereNotNull('file_path')->count(),
            DB::table('teacher_checks')->where('teacher_id', $t)->value('reference'), (bool) DB::table('teacher_profiles')->where('user_id', $t)->value('accepts_new'),
            DB::table('teacher_public_cards')->where('teacher_id', $t)->count()],
        'billing' => [DB::table('invoices')->count(), DB::table('manual_payment_proofs')->where('payer_id', $g)->count()],
        'recordings' => DB::table('recording_consents')->where('person_id', $s)->count(),
        'messages' => [DB::table('notifications')->whereIn('user_id', $ids)->count(), DB::table('notification_deliveries')->where('address', 'zubaida.qasr@noor.example')->count(),
            DB::table('push_devices')->whereIn('user_id', $ids)->count()],
        'enquiries' => array_values((array) DB::table('leads')->where('learner_id', $s)->first(['phone_e164', 'email', 'learner_name', 'notes', DB::raw('(select body from lead_activities a where a.lead_id = leads.id limit 1) as activity')])),
        'courses' => DB::table('enrollments')->where('student_id', $s)->count(),
        'ratings' => array_values((array) DB::table('lesson_ratings')->where('rater_id', $s)->first(['comment', DB::raw('tags::text as tags'), 'stars'])),
        'games' => [DB::table('game_plays')->where('student_id', $s)->count(), DB::table('creature_finds')->where('student_id', $s)->count(), DB::table('game_limits')->where('student_id', $s)->count()],
        'imports' => (string) DB::table('import_rows')->value(DB::raw('cells::text')),
        'learning' => [DB::table('homework_submissions')->where('submitted_by', $s)->count(), DB::table('challenge_claims')->where('student_id', $s)->count(),
            DB::table('cheers')->where('student_id', $s)->orWhere('from_id', $g)->count(), DB::table('homework')->where('student_id', $s)->count(), DB::table('practice_rounds')->where('student_id', $s)->count(),
            DB::table('weekly_digests')->where('student_id', $s)->count(),
            DB::table('hifz_pages')->where('student_id', $s)->count() + DB::table('hifz_reviews')->where('student_id', $s)->count() + DB::table('hifz_recitations')->where('student_id', $s)->count()
                + DB::table('recitation_mistakes')->where('student_id', $s)->count()],
        'messaging' => DB::table('conversation_messages')->count(),
        'earnings' => DB::table('teacher_earnings')->where('teacher_id', $t)->count(),
        'safeguarding' => DB::table('safeguarding_concerns')->where('reporter_id', $s)->count(),
        'lessons' => [DB::table('session_disputes')->where('raised_by', $s)->value('note'), DB::table('teacher_time_off')->where('teacher_id', $t)->value('note'),
            DB::table('booking_holds')->where('student_id', $s)->count()],
        'support' => DB::table('ticket_messages')->count(),
        'announcements' => DB::table('announcements')->count(),
    ];
}

/**
 * Every text column of every table (across academies) that holds one of these values.
 *
 * @param  list<string>  $needles
 * @return list<string> table.column: value
 */
function pdsWhereFound(array $needles): array
{
    $db = DB::connection('pgsql_platform');
    $columns = $db->select("select table_name, column_name from information_schema.columns c join information_schema.tables t using (table_schema, table_name)
        where c.table_schema = 'public' and t.table_type = 'BASE TABLE' and c.data_type in ('text', 'character varying', 'json', 'jsonb', 'character')");
    $found = [];
    foreach ($columns as $c) {
        foreach ($needles as $needle) {
            if ($db->table($c->table_name)->whereRaw('"'.$c->column_name.'"::text ilike ?', ['%'.$needle.'%'])->exists()) {
                $found[] = "{$c->table_name}.{$c->column_name}: {$needle}";
            }
        }
    }

    return $found;
}
