<?php

namespace Modules\Platform\Actions\Plans;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * The platform team adds a plan or edits one: names, limits (null: none),
 * areas, the monthly price shown in the console, and which plan new
 * academies start on. Academies on a plan follow its edits (each keeps its
 * own limits over it); archiving takes it off the list for new ones.
 */
final class SavePlan
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  code, name_ar, name_en, max_teachers, max_active_students, video_minutes, storage_mb, features, price_minor, currency, is_default, sort, archived */
    public function handle(?string $planId, array $data): SaasPlan
    {
        $limit = ['nullable', 'integer', 'min:0', 'max:10000000'];
        $valid = Validator::make($data, [
            'code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{1,30}$/', Rule::unique('pgsql_platform.saas_plans', 'code')->ignore($planId)],
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'max_teachers' => $limit,
            'max_active_students' => $limit,
            'video_minutes' => $limit,
            'storage_mb' => $limit,
            'features' => ['array'],
            'features.*' => [Rule::in(array_column(Feature::cases(), 'value'))],
            'price_minor' => ['nullable', 'integer', 'min:0', 'required_with:currency'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/', 'required_with:price_minor'],
            'is_default' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'archived' => ['boolean'],
        ], [], ['code' => __('platform::plans.attributes.code')])->validate();

        return DB::connection('pgsql_platform')->transaction(function () use ($planId, $valid) {
            $plan = $planId ? SaasPlan::query()->lockForUpdate()->findOrFail($planId) : new SaasPlan;
            $archived = (bool) ($valid['archived'] ?? false);
            $default = (bool) ($valid['is_default'] ?? false) && ! $archived;
            if ($default) {
                SaasPlan::query()->where('is_default', true)->when($plan->exists, fn ($q) => $q->whereKeyNot($plan->id))->update(['is_default' => false]);
            }
            $plan->fill([
                'code' => $valid['code'],
                'name_ar' => $valid['name_ar'],
                'name_en' => $valid['name_en'],
                'max_teachers' => $valid['max_teachers'] ?? null,
                'max_active_students' => $valid['max_active_students'] ?? null,
                'video_minutes' => $valid['video_minutes'] ?? null,
                'storage_mb' => $valid['storage_mb'] ?? null,
                'features' => array_values(array_unique($valid['features'] ?? [])),
                'price_minor' => $valid['price_minor'] ?? null,
                'currency' => $valid['currency'] ?? null,
                'is_default' => $default,
                'sort' => (int) ($valid['sort'] ?? $plan->sort ?? 0),
                'archived_at' => $archived ? ($plan->archived_at ?? now()) : null,
            ])->save();
            $this->audit->record(null, 'platform.plan_saved', 'saas_plan', $plan->id, ['plan' => $plan->name_ar.' / '.$plan->name_en, 'code' => $plan->code]);

            return $plan;
        });
    }
}
