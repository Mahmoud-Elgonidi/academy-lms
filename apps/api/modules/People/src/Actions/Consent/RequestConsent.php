<?php

namespace Modules\People\Actions\Consent;

use Illuminate\Support\Facades\DB;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\Events\ConsentRequested;
use Modules\People\Models\GuardianStudent;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Asks a waiting child's guardians to agree, each by their own link (after
 * the commit, so an import's dry run asks nobody). Linking a guardian to such
 * a child asks that guardian; the office can ask again.
 */
final class RequestConsent
{
    public function __construct(
        private readonly ConsentRules $rules,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  ?list<string>  $guardianIds  only these (of the child's guardians); null: all of them
     * @param  bool  $again  the office asks again on purpose
     * @return int how many guardians are asked
     */
    public function handle(string $studentId, ?array $guardianIds = null, bool $again = false): int
    {
        if (! $this->rules->state($studentId)->waiting()) {
            return 0;
        }
        $guardians = GuardianStudent::query()->where('student_id', $studentId)
            ->when($guardianIds !== null, fn ($q) => $q->whereIn('guardian_id', $guardianIds))
            ->pluck('guardian_id')->all();
        if ($guardians === []) {
            return 0;
        }
        $academy = $this->tenants->require()->id;
        DB::afterCommit(fn () => event(new ConsentRequested($academy, $studentId, array_values($guardians), $again)));

        return count($guardians);
    }
}
