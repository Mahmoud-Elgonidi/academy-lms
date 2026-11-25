<?php

namespace Modules\Curriculum\Contracts;

/**
 * What learners left on a curriculum's levels, units and lessons: reports,
 * progress, homework, practice played. A published version's node may be
 * deleted only while nothing of it is here (DeleteNode asks, inside its
 * transaction). Each module that keeps such rows tags its own
 * implementation SOURCES (Learning: reports, progress, homework, practice
 * stats; Games: rounds and creatures), and the curriculum asks them all.
 */
interface NodeUses
{
    /** The container tag every module's own uses go under. */
    public const SOURCES = 'curriculum.node-uses';

    /** What a use may be, each with its sentence in curriculum::curriculum.errors.in_use. */
    public const KINDS = ['reports', 'progress', 'homework', 'practice'];

    /**
     * @param  list<string>  $nodeIds
     * @return list<string> the kinds of use found (KINDS), none when nothing points at them
     */
    public function uses(array $nodeIds): array;
}
