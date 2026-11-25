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

    /**
     * Who may ask for a trial in the form block: adults and children (the
     * "for whom?" choice, as before), adults only (the learner asks for
     * themself), children only (a guardian asks for a child).
     */
    public const BOOKERS = ['both', 'adults', 'children'];

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

    /**
     * The page as the academy may show it: an academy that trains adults only
     * (academies.audience, docs/architecture/02 section 29) speaks to adults
     * on every page it has, whatever a page kept says (an older draft, a
     * template, a page from before the academy changed), so nothing on it
     * offers a trial for a child or names a child's age group. Other
     * academies' pages say whom they speak to themselves.
     *
     * @param  array<string, mixed>  $doc  a normalized PageDoc
     * @return array<string, mixed>
     */
    public static function forAcademy(array $doc, bool $adultsOnly): array
    {
        if ($adultsOnly) {
            $doc['settings'] = (is_array($doc['settings'] ?? null) ? $doc['settings'] : []);
            $doc['settings']['audience'] = self::ADULTS;
        }

        return $doc;
    }

    /**
     * Whom a page speaks to (its settings.audience; families when unset).
     *
     * @param  array<string, mixed>  $doc  a normalized PageDoc
     */
    public static function of(array $doc): string
    {
        $audience = is_array($doc['settings'] ?? null) ? ($doc['settings']['audience'] ?? null) : null;

        return $audience === self::ADULTS ? self::ADULTS : self::FAMILIES;
    }

    /**
     * Who the form takes on a page for this audience: what the block says,
     * but a page for adults takes adults alone, whatever the block says
     * (no child, no guardian anywhere on it).
     */
    public static function bookers(mixed $who, string $audience): string
    {
        if ($audience === self::ADULTS) {
            return 'adults';
        }

        return is_string($who) && in_array($who, self::BOOKERS, true) ? $who : self::BOOKERS[0];
    }

    /**
     * Who a page's trial form takes: its visible form block's choice for the
     * page's audience; with no form shown, anyone the audience allows.
     *
     * @param  array<string, mixed>  $doc  a normalized PageDoc
     */
    public static function pageBookers(array $doc): string
    {
        $audience = self::of($doc);
        foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'form' && ! ($block['hidden'] ?? false)) {
                return self::bookers(is_array($block['data'] ?? null) ? ($block['data']['who'] ?? null) : null, $audience);
            }
        }

        return self::bookers(null, $audience);
    }
}
