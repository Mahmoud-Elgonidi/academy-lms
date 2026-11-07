<?php

namespace Modules\Platform\Domain\Modules;

use Modules\SharedKernel\Plans\Feature;

/**
 * Hiding and showing the academy's optional areas (docs/architecture/02,
 * "وحدات الأكاديمية"), pure: from what is hidden now, what the academy asks
 * for and what still holds each area on, what will be hidden and why not.
 *
 *  - only optional areas are the academy's to hide; paid ones are the plan's;
 *  - an area that still holds something (groups with students in running
 *    courses, open tickets) stays until that is done;
 *  - hiding the curriculum hides the games too (they play on its lessons),
 *    and the games are not shown while the curriculum is hidden.
 */
final class ModuleRules
{
    /**
     * @param  list<Feature>  $hidden  hidden now
     * @param  array<string, bool>  $wanted  Feature value => on; areas not named stay as they are
     * @param  array<string, int>  $inUse  Feature value => what holds it on (0 or missing: nothing)
     */
    public static function decide(array $hidden, array $wanted, array $inUse): ModuleDecision
    {
        $refusals = [];
        $after = array_map(fn (Feature $f) => $f->value, $hidden);

        foreach ($wanted as $value => $on) {
            $feature = Feature::tryFrom((string) $value);
            if ($feature === null) {
                continue;
            }
            if (! $feature->isOptional()) {
                $refusals[] = new ModuleRefusal($feature, 'paid');

                continue;
            }
            $after = $on ? array_values(array_diff($after, [$feature->value])) : array_values(array_unique([...$after, $feature->value]));
        }

        // What goes off with an area (the games with the curriculum), unless it was asked to come on.
        $also = [];
        foreach (Feature::optional() as $feature) {
            if (! in_array($feature->value, $after, true)) {
                continue;
            }
            foreach ($feature->dependents() as $dependent) {
                if (in_array($dependent->value, $after, true)) {
                    continue;
                }
                if (($wanted[$dependent->value] ?? null) === true) {
                    $refusals[] = new ModuleRefusal($dependent, 'needs', needs: $feature);

                    continue;
                }
                $after[] = $dependent->value;
                $also[] = $dependent;
            }
        }

        $was = array_map(fn (Feature $f) => $f->value, $hidden);
        foreach (Feature::optional() as $feature) {
            if (in_array($feature->value, $after, true) && ! in_array($feature->value, $was, true) && ($inUse[$feature->value] ?? 0) > 0) {
                $refusals[] = new ModuleRefusal($feature, 'in_use', count: $inUse[$feature->value]);
            }
        }

        $ordered = array_values(array_filter(Feature::optional(), fn (Feature $f) => in_array($f->value, $after, true)));

        return new ModuleDecision($ordered, $also, $refusals);
    }
}
