<?php

namespace Modules\Curriculum\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Domain\ReadinessItem;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Queries\VersionReadiness;

/**
 * Publishes a draft so enrollments can follow it. It is refused while the
 * version has a blocker (Domain\PublishReadiness: no lesson, or an empty level
 * or unit), the same reading the builder's readiness bar shows; warnings do not
 * stop it. One published version per program in the MVP; a later version and
 * moving students onto it (version_node_mappings) come with P2.
 */
final class PublishVersion
{
    public function __construct(private readonly VersionReadiness $readiness) {}

    public function handle(CurriculumVersion $version, ?string $actorId): CurriculumVersion
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => __('curriculum::curriculum.errors.not_draft')]);
        }
        [$readiness, $tree] = $this->readiness->read($version);
        if (! $readiness->canPublish()) {
            throw ValidationException::withMessages(['version' => array_map(
                fn (ReadinessItem $item) => VersionReadiness::say($item, $tree),
                $readiness->blockers(),
            )]);
        }

        return DB::transaction(function () use ($version, $actorId) {
            CurriculumVersion::query()->where('program_id', $version->program_id)->where('status', 'published')->update(['status' => 'retired']);
            $version->fill(['status' => 'published', 'published_at' => CarbonImmutable::now(), 'published_by' => $actorId])->save();

            return $version;
        });
    }
}
