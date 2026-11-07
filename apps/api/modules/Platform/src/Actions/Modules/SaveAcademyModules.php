<?php

namespace Modules\Platform\Actions\Modules;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Domain\Modules\ModuleDecision;
use Modules\Platform\Domain\Modules\ModuleRefusal;
use Modules\Platform\Domain\Modules\ModuleRules;
use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\ModulesInUse;

/**
 * The academy hides or shows its optional areas (Settings › Academy modules,
 * docs/architecture/02, "وحدات الأكاديمية"): the owner and admins, never
 * while the platform team is signed in as someone. Paid areas are the plan's
 * and stay the platform team's. Nothing is deleted: a hidden area's screens,
 * routes and messages stop, and showing it again brings back everything as
 * it was. Audited with the areas' names only.
 */
final class SaveAcademyModules
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly PlatformAcademyPlan $plan,
        private readonly ModulesInUse $inUse,
    ) {}

    /**
     * @param  array<string, bool>  $on  Feature value => on; areas not named stay as they are
     * @return list<Feature> the areas hidden now
     */
    public function handle(array $on, string $actorId): array
    {
        $this->authorize();

        $hidden = DB::transaction(function () use ($on, $actorId) {
            $row = AcademyModules::query()->lockForUpdate()->first() ?? new AcademyModules;
            $before = $row->hiddenFeatures();
            $decision = $this->decide($before, $on);
            if (! $decision->allowed()) {
                throw ValidationException::withMessages(['modules' => array_map(fn (ModuleRefusal $r) => $this->sentence($r), $decision->refusals)]);
            }
            $after = $decision->hidden;
            if ($row->exists && $after === $before) {
                return $after;
            }

            $row->hidden = array_map(fn (Feature $f) => $f->value, $after);
            $row->updated_by = $actorId;
            $row->save();

            $this->audit->record('academy.modules_changed', 'academy_modules', $row->academy_id, [
                'modules_hidden' => array_values(array_map(fn (Feature $f) => $f->value, array_filter($after, fn (Feature $f) => ! in_array($f, $before, true)))),
                'modules_shown' => array_values(array_map(fn (Feature $f) => $f->value, array_filter($before, fn (Feature $f) => ! in_array($f, $after, true)))),
            ]);

            return $after;
        });
        $this->plan->forget();

        return $hidden;
    }

    /**
     * What turning one area on or off would do, for the confirmation: where it
     * disappears (or comes back), what goes with it, what stays, and why not.
     *
     * @return array{feature: string, label: string, on: bool, where: list<string>, also: list<string>, stays: string, refusals: list<string>}
     */
    public function preview(Feature $feature, bool $on): array
    {
        $decision = $this->decide(AcademyModules::current()->hiddenFeatures(), [$feature->value => $on]);
        $w = 'platform::modules.';

        return [
            'feature' => $feature->value,
            'label' => $feature->label(),
            'on' => $on,
            'where' => (array) __($w.'where.'.$feature->value),
            'also' => array_map(fn (Feature $f) => $f->label(), $decision->also),
            'stays' => __($on ? $w.'preview.comes_back' : $w.'preview.stays'),
            'refusals' => array_map(fn (ModuleRefusal $r) => $this->sentence($r), $decision->refusals),
        ];
    }

    /** @param  list<Feature>  $hidden */
    private function decide(array $hidden, array $on): ModuleDecision
    {
        $inUse = [];
        foreach ($on as $value => $wanted) {
            $feature = Feature::tryFrom((string) $value);
            if ($feature !== null && $feature->isOptional() && ! $wanted && ! in_array($feature, $hidden, true)) {
                $inUse[$feature->value] = $this->inUse->count($feature);
            }
        }
        $decision = ModuleRules::decide($hidden, $on, $inUse);

        // An area the platform team took away from this academy comes back only through them.
        $refusals = $decision->refusals;
        foreach ($on as $value => $wanted) {
            $feature = Feature::tryFrom((string) $value);
            if ($feature !== null && $feature->isOptional() && $wanted && ! $this->plan->planHas($feature)) {
                $refusals[] = new ModuleRefusal($feature, 'not_in_plan');
            }
        }

        return new ModuleDecision($decision->hidden, $decision->also, $refusals);
    }

    private function sentence(ModuleRefusal $r): string
    {
        $e = 'platform::modules.errors.';

        return match ($r->reason) {
            'in_use' => trans_choice($e.'in_use.'.(in_array($r->feature, [Feature::Groups, Feature::Support], true) ? $r->feature->value : 'other'), $r->count, ['module' => $r->feature->label()]),
            'needs' => __($e.'needs', ['module' => $r->feature->label(), 'needs' => $r->needs?->label()]),
            'not_in_plan' => __($e.'not_in_plan', ['module' => $r->feature->label()]),
            default => __($e.'paid', ['module' => $r->feature->label()]),
        };
    }

    private function authorize(): void
    {
        if (! Gate::allows(Ability::ManageModules->value)) {
            throw new AuthorizationException;
        }
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['modules' => __('platform::modules.errors.impersonating')]);
        }
    }
}
