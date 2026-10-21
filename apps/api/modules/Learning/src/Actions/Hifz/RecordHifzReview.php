<?php

namespace Modules\Learning\Actions\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Hifz\HifzPages;
use Modules\Learning\Models\HifzPage;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * One review of a page on a learner's hifz map: kept once for its source and
 * id (a report, a check, a recitation, a day's own grade), and the page's
 * next review worked out from it (Domain\Hifz\Scheduler). A review older
 * than the page's last one counts from that last one.
 */
final class RecordHifzReview
{
    public const SOURCES = ['teacher', 'report', 'check', 'wird', 'self', 'mistakes'];

    public function __construct(
        private readonly HifzPages $pages,
        private readonly TenantContext $tenants,
    ) {}

    /** @return bool whether it counted (the same source and id count once) */
    public function handle(string $studentId, int $page, int $grade, string $source, string $sourceId, CarbonImmutable $at, bool $memorized = false): bool
    {
        if (! in_array($source, self::SOURCES, true) || ! $this->pages->exists($page)) {
            throw new InvalidArgumentException("No such review: {$source} of page {$page}");
        }

        return DB::transaction(function () use ($studentId, $page, $grade, $source, $sourceId, $at, $memorized): bool {
            $counted = DB::table('hifz_reviews')->insertOrIgnore([
                'id' => (string) Str::uuid7(), 'academy_id' => $this->tenants->require()->id, 'student_id' => $studentId,
                'page' => $page, 'grade' => $grade, 'source' => $source, 'source_id' => $sourceId,
                'reviewed_at' => $at, 'created_at' => CarbonImmutable::now(),
            ]);
            if ($counted !== 1) {
                return false;
            }
            $card = HifzPage::query()->where('student_id', $studentId)->where('page', $page)->lockForUpdate()->first();
            $at = $card !== null && $at->lessThan($card->last_reviewed_at) ? $card->last_reviewed_at : $at;
            $next = $card === null ? Scheduler::first($grade, $at, $memorized) : Scheduler::next($card->card(), $grade, $at, $memorized);
            ($card ?? new HifzPage(['student_id' => $studentId, 'page' => $page]))->fill($next)->save();

            return true;
        });
    }
}
