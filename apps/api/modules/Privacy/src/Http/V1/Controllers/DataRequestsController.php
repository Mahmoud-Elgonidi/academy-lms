<?php

namespace Modules\Privacy\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\RequestCorrection;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Queries\DataRequests;
use Modules\Privacy\Queries\EditableDetails;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A person's own data, for the apps and the portal (the same as "My data"
 * in /my and /teach): whom they may ask about (themselves; a guardian also
 * the learners they follow; nobody for a younger student, whose guardian
 * asks), the details they may put right on the spot, their requests (a copy
 * of their data, a correction the academy makes, erasure of an account), and
 * taking a correction or an erasure back before it is done.
 */
final class DataRequestsController
{
    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly DataRequests $requests,
        private readonly LocalTimePresenter $time,
        private readonly EditableDetails $editable,
        private readonly PeopleApi $people,
    ) {}

    /**
     * My data.
     *
     * Whom this person may ask about and what they may put right for each on the spot (PATCH me/details), what
     * they asked (and what was asked about them), newest first, what an erasure removes and what the academy
     * keeps, and the academy's privacy notice, terms and account-deletion page.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $id = (string) $me?->getAuthIdentifier();
        $side = $this->rules->ownSide();
        $rows = $this->requests->mine($id);
        $choices = $this->people->studentDetailChoices();
        $details = [];
        foreach ($this->editable->forViewer($id, (string) $me?->name, $side) as $d) {
            $details[$d['id']] = $d;
        }
        $subjects = [];
        foreach ($this->rules->subjectsFor($id, (string) $me?->name, $side) as $s) {
            $correction = null;
            foreach ($rows as $row) {
                if ($row['subject_id'] === $s['id'] && $row['kind'] === DataRequest::CORRECTION && $row['status'] !== 'cancelled') {
                    $correction = $this->present($row);
                    break;
                }
            }
            $subjects[] = [
                'id' => (string) $s['id'],
                'name' => (string) $s['name'],
                'self' => (bool) $s['self'],
                /** Whether a copy can be asked for now: none being prepared, and none ready from the last day. */
                'can_copy' => $this->requests->canCopy((string) $s['id']),
                /** What can be put right here, with what is held now (`shown` is ready to display). Null: nothing (an erased account). */
                'details' => isset($details[$s['id']]) ? self::details($details[$s['id']], $choices) : null,
                /** The latest correction asked of the academy about this person (not taken back), or null. */
                'correction' => $correction,
            ];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($row);
        }
        $goes = [];
        foreach (ErasurePreview::GOES as $key) {
            $goes[] = __("privacy::requests.goes.{$key}");
        }
        $stays = [];
        foreach (ErasurePreview::STAYS as $key) {
            $stays[] = __("privacy::requests.stays.{$key}");
        }

        return response()->json(['data' => [
            /** Whom this person may ask about: themselves first, then the learners they follow. Empty: a younger student, whose guardian asks. */
            'subjects' => $subjects,
            'requests' => $out,
            /** What an erasure removes. */
            'goes' => $goes,
            /** What the academy keeps after an erasure, without the name. */
            'stays' => $stays,
            'stays_note' => __('privacy::requests.stays_note'),
            /** Hours between the academy's approval and the erasure (it can be taken back until then). */
            'grace_hours' => DataRequest::GRACE_HOURS,
            /** Days the academy has to answer an erasure or a correction. */
            'answer_days' => DataRequest::ANSWER_DAYS,
            /** The values each detail can take, for the correction form: gender, country, school_grade, teacher_gender (an empty value: no preference). */
            'choices' => self::choices($choices),
            /** Shortest and longest correction request. */
            'says_min' => RequestCorrection::SAYS_MIN,
            'says_max' => RequestCorrection::SAYS_MAX,
            /** The academy's public pages, on its own address. */
            'links' => $this->requests->legalLinks(),
        ]]);
    }

    /**
     * Ask for a copy, a correction, or for an account to be erased.
     *
     * `kind` export (a ZIP of everything the academy holds about the person, built in a few minutes and kept a
     * week; one a day), correction (`says` what is wrong and what it should be, for what cannot be put right with
     * PATCH me/details; one open at a time; the academy answers within 30 days) or erasure (the academy answers
     * within 30 days, then the account is erased a day after its approval). `subject_id` is one of "subjects"
     * (themselves when left out); `reason` optionally says why (erasure). An erasure asked again while one is open
     * is the same request. Answers `201`. Send an `Idempotency-Key`.
     */
    public function store(Request $request, RequestDataExport $export, RequestErasure $erasure, RequestCorrection $correction): JsonResponse
    {
        $request->validate([
            'kind' => ['required', 'string', 'in:export,erasure,correction'],
            /** One of "subjects"; the person themselves when left out. */
            'subject_id' => ['nullable', 'string', 'uuid'],
            'reason' => ['nullable', 'string', 'max:'.RequestErasure::REASON_MAX],
            /** A correction: what is wrong and what it should be. */
            'says' => ['required_if:kind,correction', 'nullable', 'string', 'min:'.RequestCorrection::SAYS_MIN, 'max:'.RequestCorrection::SAYS_MAX],
        ]);
        $me = (string) $request->user()?->getAuthIdentifier();
        $subject = (string) ($request->input('subject_id') ?: $me);
        $side = $this->rules->ownSide();

        $made = match ($request->input('kind')) {
            DataRequest::EXPORT => $export->handle($subject, $me, $side),
            DataRequest::CORRECTION => $correction->handle($subject, $me, $side, trim((string) $request->input('says'))),
            default => $erasure->handle($subject, $me, $side, $request->input('reason')),
        };

        return response()->json(['data' => $this->present($this->requests->row($made->refresh()) + ['self' => $made->subject_id === $me])], 201);
    }

    /**
     * Take a correction or an erasure back.
     *
     * A correction while the academy has not answered; an erasure until then, or in the day after it approved; by
     * whoever asked or the person themselves.
     */
    public function cancel(Request $request, string $id, CancelDataRequest $cancel): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $found = $this->requests->find($id);
        abort_if($found === null || ! in_array($me, [$found->requested_by, $found->subject_id], true), 404);
        $done = $cancel->handle($found, $me);

        return response()->json(['data' => $this->present($this->requests->row($done) + ['self' => $done->subject_id === $me])]);
    }

    /** @param  array<string, mixed>  $row  @return array<string, mixed> */
    private function present(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            /** export | erasure | correction */
            'kind' => ['key' => $row['kind'], 'label' => __("privacy::requests.kinds.{$row['kind']}")],
            /** export: pending (being built) | ready | expired | failed; erasure: pending (the academy's turn) | approved (erased at erase_after) | done | refused | cancelled; correction: pending | done (corrected; `answer` may say what changed) | refused | cancelled */
            'status' => ['key' => $row['status'], 'label' => (string) ($row['status_label'] ?? __("privacy::requests.statuses.{$row['status']}"))],
            /** Whom it is about. */
            'subject' => ['id' => (string) $row['subject_id'], 'name' => (string) $row['subject'], 'self' => (bool) ($row['self'] ?? false)],
            'requester' => (string) $row['requester'],
            /** Why (erasure), or what should be corrected (correction). */
            'reason' => is_string($row['reason']) ? $row['reason'] : null,
            /** The academy's reason when it refused; for a correction it made, what it changed (optional). */
            'answer' => is_string($row['answer']) ? $row['answer'] : null,
            'created_at' => $this->time->present($row['created_at']),
            /** When the academy answers by (erasure, correction). */
            'due_at' => $this->time->present($row['due_at']),
            /** When the account is erased, once approved. */
            'erase_after' => $this->time->present($row['erase_after']),
            'done_at' => $this->time->present($row['done_at']),
            /** Until when a ready copy can be downloaded. */
            'expires_at' => $this->time->present($row['expires_at']),
            /** Bytes of a ready copy. */
            'size' => isset($row['size']) ? (int) $row['size'] : null,
            /** A ready copy's address: opens without signing in, for an hour; ask again for a fresh one. */
            'download_url' => is_string($row['download_url']) ? $row['download_url'] : null,
            /** Why an approved erasure went back to the academy (lessons or money that came up meanwhile). */
            'held_back' => $this->lines((array) $row['held_back']),
            /** Whether it can still be taken back. */
            'can_cancel' => (bool) (in_array($row['kind'], DataRequest::ANSWERED, true) && in_array($row['status'], DataRequest::OPEN, true)
                && ($row['kind'] === DataRequest::ERASURE || $row['status'] === 'pending')),
        ];
    }

    /**
     * @param  array{fields: list<string>, values: array<string, ?string>}  $editable
     * @param  array<string, array<string, string>>  $choices
     * @return list<array{key: string, label: string, value: ?string, shown: string}>
     */
    public static function details(array $editable, array $choices): array
    {
        $out = [];
        foreach ($editable['fields'] as $field) {
            $value = $editable['values'][$field] ?? null;
            $shown = $field === 'name' ? $value : ($choices[$field][$value ?? ''] ?? null);
            $out[] = [
                /** name | gender | country | school_grade | teacher_gender */
                'key' => $field,
                'label' => __("privacy::screens.mine.fix.fields.{$field}"),
                /** What is held (a choice's value; null: not set, or no preference for teacher_gender). */
                'value' => $value,
                /** Ready to display. */
                'shown' => (string) ($shown ?? __('privacy::screens.mine.fix.unset')),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, string>>  $choices
     * @return array<string, list<array{value: string, label: string}>>
     */
    private static function choices(array $choices): array
    {
        $out = [];
        foreach ($choices as $field => $options) {
            $out[$field] = [];
            foreach ($options as $value => $label) {
                $out[$field][] = ['value' => (string) $value, 'label' => (string) $label];
            }
        }

        return $out;
    }

    /** @param  array<mixed>  $lines  @return list<string> */
    private function lines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $out[] = (string) $line;
        }

        return $out;
    }
}
