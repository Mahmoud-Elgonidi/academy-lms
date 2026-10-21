<?php

namespace Modules\Learning\Http\Web;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Models\HomeworkSubmission;
use Modules\Learning\Models\WeeklyDigest;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The recording of the week, behind the weekly note's link (each guardian's
 * own, signed for two weeks; no sign-in): a page that plays it, in the
 * academy's look and the guardian's language. Only a recording a weekly note
 * chose, and only while the guardian still receives the child's reports;
 * played in the page, never offered as a download.
 */
final class WeeklyVoiceController
{
    public const LINK_DAYS = 14;

    /** How long the page's own address for the sound lasts. */
    public const FILE_MINUTES = 60;

    public function show(Request $request, string $submission, PeopleApi $people, BrandingApi $branding, TenantContext $tenants): View
    {
        [$guardian, $row] = $this->allowed($request, $submission, $people, $tenants);
        $tz = $guardian->timezone ?: $tenants->require()->defaultTimezone;
        $name = (string) DB::table('users')->where('id', $row->student_id)->value('name');

        return view('learning::voice', [
            'student' => strtok(trim($name), ' ') ?: $name,
            'homework' => $row->title,
            'when' => CarbonImmutable::parse($row->submitted_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('l j F · H:i'),
            'src' => URL::temporarySignedRoute('learning.voice.file', now()->addMinutes(self::FILE_MINUTES), ['submission' => $submission, 'u' => $guardian->id], absolute: false),
            'branding' => $branding->current(),
        ]);
    }

    public function file(Request $request, string $submission, PeopleApi $people, TenantContext $tenants): StreamedResponse
    {
        [, $row] = $this->allowed($request, $submission, $people, $tenants);
        abort_unless(Storage::disk(SubmitHomework::DISK)->exists($row->file_path), 404);

        return Storage::disk(SubmitHomework::DISK)->response($row->file_path, 'recording', [
            'Content-Type' => $row->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * The guardian the link names, and the recording: one a weekly note chose, of a child whose reports they receive.
     *
     * @return array{0: object, 1: object}
     */
    private function allowed(Request $request, string $submission, PeopleApi $people, TenantContext $tenants): array
    {
        $guardianId = (string) $request->query('u');
        abort_unless(Str::isUuid($guardianId) && Str::isUuid($submission), 404);
        $guardian = DB::table('users')->where('id', $guardianId)->where('status', 'active')->first(['id', 'locale', 'timezone']);
        abort_if($guardian === null, 404);
        app()->setLocale($guardian->locale ?: $tenants->require()->defaultLocale);

        $row = DB::table('homework_submissions as s')
            ->join('homework as h', fn ($j) => $j->on('h.academy_id', '=', 's.academy_id')->on('h.id', '=', 's.homework_id'))
            ->where('s.id', $submission)->whereNotNull('s.file_path')->where(fn ($q) => HomeworkSubmission::whereRecording($q, 's.mime'))
            ->first(['s.file_path', 's.mime', 's.submitted_at', 'h.student_id', 'h.title']);
        abort_if($row === null || ! WeeklyDigest::query()->where('student_id', $row->student_id)->where('voice_submission_id', $submission)->exists(), 404);
        abort_unless(in_array($guardianId, $people->guardiansOf($row->student_id, reportsOnly: true), true), 403);

        return [$guardian, $row];
    }
}
