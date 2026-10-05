<?php

namespace Modules\Platform\Actions;

use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Models\AcademyBranding;
use Modules\Platform\Models\BrandingTemplate;

/** Deletes a saved template. An academy using it keeps the same look, now as "custom". */
final class DeleteBrandTemplate
{
    public function __construct(private readonly BrandingService $branding) {}

    public function handle(string $templateId): void
    {
        $template = BrandingTemplate::query()->findOrFail($templateId);

        AcademyBranding::query()->where('template_ref', $template->ref())->update(['template_ref' => null]);
        $template->delete();

        $this->branding->forget();
    }
}
