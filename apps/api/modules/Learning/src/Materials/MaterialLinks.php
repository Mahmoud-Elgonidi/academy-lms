<?php

namespace Modules\Learning\Materials;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\LessonResource;
use Modules\Learning\Contracts\DTOs\MaterialLine;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A lesson's materials for someone already allowed to see the lesson (its
 * teacher, the staff, the student or a guardian on the course): a link as
 * the academy gave it, a file through a signed address on the academy's
 * own host, good for twelve hours, which opens without signing in (the
 * class page's link visitors, the apps, the portal). Teachers-only
 * materials are only ever signed for teachers and staff.
 */
final class MaterialLinks
{
    public const HOURS = 12;

    public function __construct(
        private readonly CurriculumApi $curriculum,
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  list<string>  $lessonIds
     * @return array<string, list<MaterialLine>> by lesson
     */
    public function forLessons(array $lessonIds, bool $teachers): array
    {
        return array_map(
            fn (array $list) => array_map(fn (LessonResource $r) => $this->line($r), $list),
            $this->curriculum->resources($lessonIds, $teachers),
        );
    }

    public function line(LessonResource $r): MaterialLine
    {
        return new MaterialLine($r->id, $r->type, $r->title(), $r->kind === 'link' ? (string) $r->url : $this->signed($r->id), $r->forTeachersOnly(), $r->size);
    }

    private function signed(string $resourceId): string
    {
        return rtrim($this->directory->baseUrl($this->tenants->require()), '/')
            .URL::temporarySignedRoute('learning.material', CarbonImmutable::now()->addHours(self::HOURS), ['resource' => $resourceId], absolute: false);
    }
}
