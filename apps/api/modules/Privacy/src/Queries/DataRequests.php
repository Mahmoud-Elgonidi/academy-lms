<?php

namespace Modules\Privacy\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Data requests as people see them: the academy's (erasures waiting
 * first, oldest first, with what holds each back; then the last 90 days),
 * and a person's own (what they asked, for themselves or a child, and what
 * was asked about them). A ready copy carries a signed address on the
 * academy's host, good for an hour, that opens without signing in (the
 * apps and the portal download it).
 */
final class DataRequests
{
    public const LINK_MINUTES = 60;

    public function __construct(
        private readonly ErasurePreview $preview,
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    /** @return array{open: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
    public function forAcademy(): array
    {
        $open = DataRequest::query()->where('kind', DataRequest::ERASURE)->whereIn('status', DataRequest::OPEN)->orderBy('created_at')->get();
        $recent = DataRequest::query()->where(fn ($q) => $q->where('kind', DataRequest::EXPORT)->orWhereNotIn('status', DataRequest::OPEN))
            ->where('created_at', '>=', now()->subDays(90))->orderByDesc('created_at')->limit(100)->get();
        $names = Subjects::names([...$open->pluck('subject_id'), ...$open->pluck('requested_by'), ...$recent->pluck('subject_id'), ...$recent->pluck('requested_by'), ...$recent->pluck('decided_by')->filter()]);

        return [
            'open' => $open->map(fn (DataRequest $r) => $this->row($r, $names) + ['blockers' => $r->status === 'pending' ? $this->preview->blockers($r->subject_id) : []])->all(),
            'recent' => $recent->map(fn (DataRequest $r) => $this->row($r, $names))->all(),
        ];
    }

    /** @return list<array<string, mixed>> what a person asked, and what was asked about them, newest first */
    public function mine(string $viewerId): array
    {
        $requests = DataRequest::query()->where(fn ($q) => $q->where('requested_by', $viewerId)->orWhere('subject_id', $viewerId))
            ->orderByDesc('created_at')->limit(30)->get();
        $names = Subjects::names([...$requests->pluck('subject_id'), ...$requests->pluck('requested_by')]);

        return $requests->map(fn (DataRequest $r) => $this->row($r, $names) + ['self' => $r->subject_id === $viewerId])->all();
    }

    /** Whether a copy of someone's data can be asked for now: none being prepared, and none ready from the last day (one a day). */
    public function canCopy(string $subjectId): bool
    {
        return ! DataRequest::query()->where('subject_id', $subjectId)->where('kind', DataRequest::EXPORT)
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($q) => $q->where('status', 'ready')->where('created_at', '>', now()->subHours(DataRequest::EXPORT_EVERY_HOURS))))
            ->exists();
    }

    public function find(string $id): ?DataRequest
    {
        return preg_match('/^[0-9a-f-]{36}$/i', $id) ? DataRequest::query()->find($id) : null;
    }

    /** @param  array<string, string>  $names  @return array<string, mixed> */
    public function row(DataRequest $r, array $names = []): array
    {
        return [
            'id' => $r->id,
            'kind' => $r->kind,
            'status' => $r->status,
            'subject_id' => $r->subject_id,
            'subject' => $names[$r->subject_id] ?? Subjects::name($r->subject_id),
            'requested_by' => $r->requested_by,
            'requester' => $names[$r->requested_by] ?? Subjects::name($r->requested_by),
            'reason' => $r->reason,
            'answer' => $r->answer,
            'created_at' => $r->created_at,
            'due_at' => $r->due_at,
            'erase_after' => $r->erase_after,
            'decided_by' => $r->decided_by === null ? null : ($names[$r->decided_by] ?? Subjects::name($r->decided_by)),
            'decided_at' => $r->decided_at,
            'done_at' => $r->done_at,
            'expires_at' => $r->expires_at,
            'size' => $r->file_size,
            'overdue' => $r->isOverdue(),
            'download_url' => $r->isDownloadable() ? $this->link($r) : null,
            'held_back' => (array) ($r->summary['held_back'] ?? []),
        ];
    }

    /** @return array{privacy: string, terms: string, deletion: string} the academy's public pages, on its host */
    public function legalLinks(): array
    {
        $base = rtrim($this->directory->baseUrl($this->tenants->require()), '/');

        return [
            'privacy' => $base.route('privacy.notice', [], false),
            'terms' => $base.route('privacy.terms', [], false),
            'deletion' => $base.route('privacy.deletion', [], false),
        ];
    }

    public function link(DataRequest $r): string
    {
        return rtrim($this->directory->baseUrl($this->tenants->require()), '/')
            .URL::temporarySignedRoute('privacy.copy', CarbonImmutable::now()->addMinutes(self::LINK_MINUTES), ['request' => $r->id], absolute: false);
    }
}
