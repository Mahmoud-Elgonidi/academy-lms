<?php

namespace Modules\Platform\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Models\AcademyBranding;
use Modules\Platform\Models\BrandingTemplate;

/**
 * Overwrites a saved template with new tokens (and optionally a new name).
 * If the academy is using that template, the new look applies right away.
 */
final class UpdateBrandTemplate
{
    public function __construct(private readonly BrandingService $branding) {}

    public function handle(string $templateId, BrandTokens $tokens, ?string $name = null, ?string $actorId = null): BrandingTemplate
    {
        $template = BrandingTemplate::query()->findOrFail($templateId);

        if ($name !== null) {
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > 60) {
                throw ValidationException::withMessages(['name' => __('platform::branding.errors.name_length')]);
            }
            if (SaveBrandTemplate::nameTaken($name, $template->id)) {
                throw ValidationException::withMessages(['name' => __('platform::branding.errors.name_taken')]);
            }
            $template->name = $name;
        }

        $template->tokens = $tokens->toArray();
        $template->save();

        $active = AcademyBranding::query()->first();
        if ($active !== null && $active->template_ref === $template->ref()) {
            $active->fill(['tokens' => $tokens->toArray(), 'updated_by' => $actorId])->save();
        }

        $this->branding->forget();

        return $template;
    }
}
