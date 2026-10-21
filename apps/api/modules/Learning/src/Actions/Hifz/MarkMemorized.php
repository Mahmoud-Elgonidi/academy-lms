<?php

namespace Modules\Learning\Actions\Hifz;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Learning\Domain\Hifz\Scheduler;
use Modules\Learning\Models\HifzPage;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The teacher says which pages a learner already knows by heart, and how
 * well (strong, good or weak): "knows part 30 well". Each page starts (or
 * starts again) as memorized, due when that strength says, and the
 * statement is kept as one review of each page.
 */
final class MarkMemorized
{
    /** The teacher's word as a grade, for the review kept. */
    private const GRADES = ['strong' => Scheduler::EASY, 'good' => Scheduler::GOOD, 'weak' => Scheduler::HARD];

    public function __construct(private readonly TenantContext $tenants) {}

    /** @param  list<int>  $pages  HifzPages::chosen() */
    public function handle(string $studentId, array $pages, string $strength, ?CarbonImmutable $at = null): int
    {
        Validator::make(['strength' => $strength, 'pages' => $pages], [
            'strength' => ['required', Rule::in(array_keys(Scheduler::MARKED))],
            'pages' => ['required', 'array', 'max:604'],
        ], [], trans('learning::hifz.attributes'))->validate();
        $at ??= CarbonImmutable::now();
        $statement = (string) Str::uuid7();

        return DB::transaction(function () use ($studentId, $pages, $strength, $at, $statement): int {
            foreach ($pages as $page) {
                HifzPage::query()->updateOrCreate(['student_id' => $studentId, 'page' => $page], Scheduler::marked($strength, $at));
                DB::table('hifz_reviews')->insert([
                    'id' => (string) Str::uuid7(), 'academy_id' => $this->tenants->require()->id, 'student_id' => $studentId, 'page' => $page,
                    'grade' => self::GRADES[$strength], 'source' => 'teacher', 'source_id' => $statement, 'reviewed_at' => $at, 'created_at' => $at,
                ]);
            }

            return count($pages);
        });
    }
}
