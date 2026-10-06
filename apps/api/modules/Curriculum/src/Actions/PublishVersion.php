<?php

namespace Modules\Curriculum\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;

/**
 * Publishes a draft so enrollments can follow it. It needs at least one
 * lesson. One published version per program in the MVP; a later version and
 * moving students onto it (version_node_mappings) come with P2.
 */
final class PublishVersion
{
    public function handle(CurriculumVersion $version, ?string $actorId): CurriculumVersion
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages(['version' => __('curriculum::curriculum.errors.not_draft')]);
        }
        if (! Node::query()->where('version_id', $version->id)->where('kind', 'lesson')->exists()) {
            throw ValidationException::withMessages(['version' => __('curriculum::curriculum.errors.no_lessons')]);
        }

        return DB::transaction(function () use ($version, $actorId) {
            CurriculumVersion::query()->where('program_id', $version->program_id)->where('status', 'published')->update(['status' => 'retired']);
            $version->fill(['status' => 'published', 'published_at' => CarbonImmutable::now(), 'published_by' => $actorId])->save();

            return $version;
        });
    }
}
