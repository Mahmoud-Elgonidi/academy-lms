<?php

namespace Modules\Platform\Branding;

use Illuminate\Support\Facades\Storage;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\DTOs\Branding;
use Modules\Platform\Models\AcademyBranding;
use Modules\SharedKernel\Tenancy\TenantContext;

/** Builds the Branding DTO once per request (scoped binding); actions call forget() after a change. */
final class BrandingService implements BrandingApi
{
    private ?Branding $current = null;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TemplateCatalog $catalog,
    ) {}

    public function current(): Branding
    {
        return $this->current ??= $this->build();
    }

    public function forget(): void
    {
        $this->current = null;
    }

    private function build(): Branding
    {
        $tenant = $this->tenants->require();
        $row = AcademyBranding::query()->first();

        $ref = $row?->template_ref ?? ($row === null ? 'builtin:'.BuiltInTemplates::DEFAULT : null);
        $tokens = $row?->brandTokens() ?? BuiltInTemplates::tokens(BuiltInTemplates::DEFAULT);
        $template = $this->catalog->find($ref);

        return new Branding(
            nameAr: $tenant->nameAr,
            nameEn: $tenant->nameEn,
            tokens: $tokens->toArray(),
            radii: $tokens->radii(),
            fontsUrl: BrandFonts::stylesheetUrl($tokens->headingFont, $tokens->bodyFont),
            logoUrl: $row?->logo_path ? Storage::disk('public')->url($row->logo_path) : null,
            faviconUrl: $row?->favicon_path ? Storage::disk('public')->url($row->favicon_path) : null,
            templateRef: $template['ref'] ?? null,
            templateName: $template['name'] ?? null,
            modified: $template !== null && ! $template['tokens']->equals($tokens),
        );
    }
}
