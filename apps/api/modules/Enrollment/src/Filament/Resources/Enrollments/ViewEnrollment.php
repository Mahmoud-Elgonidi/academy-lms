<?php

namespace Modules\Enrollment\Filament\Resources\Enrollments;

use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Enrollment\Models\Enrollment;
use Modules\Enrollment\Queries\EnrollmentAccountQuery;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The student account for one enrollment: credit left in sessions and
 * minutes, what is booked, the ledger line by line, and the invoices.
 *
 * @property Enrollment $record
 */
final class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected string $view = 'enrollment::filament.account';

    public function getTitle(): string|Htmlable
    {
        return $this->account()['header']['student'] ?? __('enrollment::enrollments.model');
    }

    /** @return array<string, mixed> */
    public function account(): array
    {
        $q = app(EnrollmentAccountQuery::class);
        $h = $q->header($this->record->id);
        $tz = app(ViewerTimezone::class)->effective();
        $studentTz = $h['student_tz'] ?? $tz;
        $minutes = (int) ($h['session_minutes'] ?? 0);
        $makeup = $q->makeupOwed($h['credit_account_id']);
        $remaining = max(0, (int) $h['available_min'] + (int) $h['reserved_min'] - $makeup['minutes']);
        $fmt = fn ($at, string $pattern = 'D j M · H:i') => $at ? CarbonImmutable::parse($at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat($pattern) : null;

        return [
            'header' => $h,
            'tz' => $tz,
            'studentTz' => $studentTz,
            'subject' => app()->getLocale() === 'ar' ? $h['subject_ar'] : $h['subject_en'],
            'plan' => app()->getLocale() === 'ar' ? $h['plan_ar'] : $h['plan_en'],
            'price' => $h['amount_minor'] !== null ? Money::of((int) $h['amount_minor'], $h['currency'])->format() : null,
            'sessionsLeft' => $minutes > 0 ? intdiv($remaining, $minutes) : 0,
            'remainingMin' => $remaining,
            'reservedMin' => (int) $h['reserved_min'],
            'availableMin' => (int) $h['available_min'],
            'block' => (int) ($h['sessions_per_block'] ?? 0),
            'renewAt' => (int) ($h['renew_at_sessions'] ?? 0),
            'counts' => $q->counts($this->record->id),
            'weekly' => $q->weeklyTimes($this->record->id, $studentTz),
            'upcoming' => array_map(fn ($s) => [
                'when' => $fmt($s->starts_at),
                'status' => $s->status,
                'funding' => $s->funding_status,
            ], $q->upcoming($this->record->id)),
            'statement' => array_map(fn ($e) => [
                'when' => $fmt($e->occurred_at, 'j M · H:i'),
                'type' => $e->type,
                'mode' => json_decode($e->meta ?? '{}', true)['mode'] ?? null,
                'minutes' => (int) $e->minutes,
                'sign' => match ($e->type) {
                    'grant' => '+', 'expire', 'consume' => '−', default => ''
                },
                'available' => (int) $e->available_after,
                'reserved' => (int) $e->reserved_after,
                'ref' => $e->invoice ?? ($e->session_at ? $fmt($e->session_at, 'D j M · H:i') : null),
                'source' => $e->source,
            ], $q->statement($h['credit_account_id'])),
            'invoices' => array_map(fn ($i) => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status === 'issued' && $i->due_at && CarbonImmutable::parse($i->due_at)->isPast() ? 'overdue' : $i->status,
                'total' => Money::of((int) $i->total_minor, $i->currency)->format(),
                'issued' => $fmt($i->issued_at, 'j M Y'),
                'url' => route('filament.academy.resources.invoices.view', ['record' => $i->id]),
            ], $q->invoices($h['subscription_id'])),
            'makeup' => $makeup,
            'expiry' => $q->nextExpiry($h['credit_account_id']),
            'fmt' => $fmt,
        ];
    }
}
