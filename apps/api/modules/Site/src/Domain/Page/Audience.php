<?php

namespace Modules\Site\Domain\Page;

/**
 * What a page may say about ages for its audience (site-builder.md, 9.2).
 * A page for adults never names a child's age group: a teacher's card on it
 * shows "adults" alone when the teacher teaches adults, and nothing about
 * ages otherwise, whatever the teachers block's `show_ages` says. A page for
 * families shows every group, as before. Pure.
 */
final class Audience
{
    public const FAMILIES = 'families';

    public const ADULTS = 'adults';

    /** The age groups an adults page may name. */
    public const ADULT_GROUPS = ['adults'];

    /**
     * @param  list<string>  $groups  the teacher's age groups (kids, middle, teen, adults)
     * @return list<string>
     */
    public static function ageGroups(array $groups, string $audience): array
    {
        $kept = $groups;
        if ($audience === self::ADULTS) {
            foreach ($kept as $i => $group) {
                if (! in_array($group, self::ADULT_GROUPS, true)) {
                    unset($kept[$i]);
                }
            }
        }

        return array_values($kept);
    }
}
