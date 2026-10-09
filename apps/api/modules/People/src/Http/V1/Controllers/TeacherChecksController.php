<?php

namespace Modules\People\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\People\Actions\Checks\AddTeacherCheck;
use Modules\People\Checks\Clearances;
use Modules\People\Contracts\CheckKind;
use Modules\People\Models\TeacherCheck;
use Modules\People\Models\TeacherProfile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A teacher's own vetting documents in the apps (the same as "My documents"
 * in /teach): what they sent and where each stands, whether the academy asks
 * for a criminal record check before they teach under-18s and whether theirs
 * is in order, and sending a new one. Never why the academy paused them.
 */
final class TeacherChecksController
{
    /**
     * My documents.
     *
     * Whether the academy asks for a valid criminal record check to teach students under 18 (`required`), where
     * the teacher's own stands (`state`: verified, pending, expired, missing, not_required) with the day it runs
     * out, the kinds of document one may send, and the documents sent, newest first: a turned-down one with the
     * academy's reason.
     */
    public function index(Request $request, Clearances $clearances): JsonResponse
    {
        $me = $this->teacher($request);
        // A pause is the academy's to explain: the teacher sees only where their own documents stand.
        $clearance = $clearances->of($me, withPause: false);
        $checks = [];
        foreach (TeacherCheck::query()->where('teacher_id', $me)->orderByDesc('created_at')->get() as $c) {
            $checks[] = $this->check($c);
        }
        $kinds = [];
        foreach (CheckKind::cases() as $k) {
            $kinds[] = ['key' => $k->value, 'label' => $k->label()];
        }
        $state = $clearance->state;

        return response()->json(['data' => [
            'required' => $clearance->required,
            /** verified | pending | expired | missing | not_required */
            'state' => $state,
            /** What the state means for the teacher, in words; empty when nothing is asked of them. */
            'state_label' => (string) __('people::checks.mine.state.'.$state, ['day' => $clearance->expiresOn?->locale(app()->getLocale())->translatedFormat('j M Y') ?? __('people::checks.no_end')]),
            /** The day the verified check runs out (none: it does not). */
            'valid_until' => $state === 'verified' ? $clearance->expiresOn?->toDateString() : null,
            'kinds' => $kinds,
            'checks' => $checks,
        ]]);
    }

    /**
     * Send a document.
     *
     * Multipart: `kind`, `file` (a picture or PDF, 10 MB), and optionally `reference` (the certificate's number),
     * `issued_on` and `expires_on` (dates). One of a kind waits at a time. Answers `201` with the document. Send an
     * `Idempotency-Key`.
     */
    public function store(Request $request, AddTeacherCheck $add): JsonResponse
    {
        $request->validate([
            'kind' => ['required', 'string', 'in:criminal_record,identity,qualification,other'],
            'file' => ['required', 'file', 'max:'.TeacherCheck::MAX_KB],
            'reference' => ['nullable', 'string', 'max:120'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $me = $this->teacher($request);
        $check = $add->handle(
            $me,
            (string) $request->input('kind'),
            $request->input('reference'),
            $request->filled('issued_on') ? CarbonImmutable::parse((string) $request->input('issued_on')) : null,
            $request->filled('expires_on') ? CarbonImmutable::parse((string) $request->input('expires_on')) : null,
            $request->file('file'),
            $me,
        );

        return response()->json(['data' => $this->check($check->refresh())], 201);
    }

    /**
     * A document's scan.
     *
     * The picture or PDF the teacher sent, inline; only their own.
     */
    public function file(Request $request, string $check): StreamedResponse
    {
        $me = $this->teacher($request);
        $found = TeacherCheck::query()->where('teacher_id', $me)->find($check);
        abort_if($found === null || $found->file_path === null, 404);

        return Storage::disk(TeacherCheck::DISK)->response($found->file_path, $found->file_name, [
            'Content-Type' => $found->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function teacher(Request $request): string
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        abort_unless(TeacherProfile::query()->whereKey($me)->exists(), 403);

        return $me;
    }

    /** @return array<string, mixed> */
    private function check(TeacherCheck $c): array
    {
        return [
            'id' => $c->id,
            /** criminal_record | identity | qualification | other */
            'kind' => ['key' => $c->kind->value, 'label' => $c->kind->label()],
            'reference' => $c->reference,
            'issued_on' => $c->issued_on?->toDateString(),
            'expires_on' => $c->expires_on?->toDateString(),
            /** pending | verified | rejected */
            'status' => ['key' => $c->status, 'label' => (string) __('people::checks.statuses.'.$c->status)],
            /** Why the academy turned it down (only then). */
            'reason' => $c->status === 'rejected' ? $c->decision_note : null,
            'sent_at' => $c->created_at?->toIso8601ZuluString(),
            'file' => $c->file_path !== null ? ['name' => (string) $c->file_name, 'url' => route('teacher.checks.file', ['check' => $c->id])] : null,
        ];
    }
}
