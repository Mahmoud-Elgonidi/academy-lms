<?php

namespace Modules\Platform\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Models\BrandingTemplate;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/** Saves the given look as a named template of the current academy and applies it. */
final class SaveBrandTemplate
{
    public const MAX_TEMPLATES = 20;

    public function __construct(
        private readonly SaveBranding $saveBranding,
        private readonly AcademyPlan $plan,
    ) {}

    public function handle(string $name, BrandTokens $tokens, ?string $actorId = null): BrandingTemplate
    {
        // Templates of its own are an area of the academy's plan.
        $this->plan->requireFeature(Feature::CustomBranding);
        $name = trim($name);
        $this->validateName($name);

        if (BrandingTemplate::query()->count() >= self::MAX_TEMPLATES) {
            throw ValidationException::withMessages(['name' => __('platform::branding.errors.too_many', ['max' => self::MAX_TEMPLATES])]);
        }

        $template = BrandingTemplate::query()->create([
            'name' => $name,
            'tokens' => $tokens->toArray(),
            'created_by' => $actorId,
        ]);

        $this->saveBranding->handle($tokens, $template->ref(), $actorId);

        return $template;
    }

    public static function nameTaken(string $name, ?string $exceptId = null): bool
    {
        $builtIn = collect(BuiltInTemplates::all())
            ->contains(fn (array $t) => mb_strtolower($t['name_ar']) === mb_strtolower($name) || mb_strtolower($t['name_en']) === mb_strtolower($name));

        return $builtIn || BrandingTemplate::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    private function validateName(string $name): void
    {
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => __('platform::branding.errors.name_length')]);
        }

        if (self::nameTaken($name)) {
            throw ValidationException::withMessages(['name' => __('platform::branding.errors.name_taken')]);
        }
    }
}
