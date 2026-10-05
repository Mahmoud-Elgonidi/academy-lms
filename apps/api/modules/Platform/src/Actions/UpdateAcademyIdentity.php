<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\AcademyBranding;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The current academy's names, logo and favicon. File paths are on the public
 * disk; a replaced or removed file is deleted.
 */
final class UpdateAcademyIdentity
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly BrandingService $branding,
    ) {}

    public function handle(string $nameAr, string $nameEn, ?string $logoPath, ?string $faviconPath, ?string $actorId = null): void
    {
        $nameAr = trim($nameAr);
        $nameEn = trim($nameEn);
        if ($nameAr === '' || $nameEn === '' || mb_strlen($nameAr) > 120 || mb_strlen($nameEn) > 120) {
            throw ValidationException::withMessages(['name_ar' => __('platform::branding.errors.academy_name')]);
        }

        $tenant = $this->tenants->require();

        // Uploads live under this academy's own folder; anything else is refused,
        // so a crafted path can neither point at nor delete another academy's file.
        foreach (['logo' => $logoPath, 'favicon' => $faviconPath] as $field => $path) {
            if ($path !== null && ! str_starts_with($path, self::directory($tenant->id).'/')) {
                throw ValidationException::withMessages([$field => __('platform::branding.errors.file')]);
            }
        }

        $academy = Academy::query()->findOrFail($tenant->id);
        $academy->fill(['name_ar' => $nameAr, 'name_en' => $nameEn])->save();

        $row = AcademyBranding::query()->firstOrNew();
        if (! $row->exists) {
            $row->fill([
                'template_ref' => 'builtin:'.BuiltInTemplates::DEFAULT,
                'tokens' => BuiltInTemplates::tokens(BuiltInTemplates::DEFAULT)->toArray(),
            ]);
        }

        foreach (['logo_path' => $logoPath, 'favicon_path' => $faviconPath] as $column => $path) {
            $old = $row->getAttribute($column);
            if ($old !== null && $old !== $path) {
                Storage::disk('public')->delete($old);
            }
            $row->setAttribute($column, $path);
        }

        $row->updated_by = $actorId;
        $row->save();

        // The rest of this request (and the page that re-renders) sees the new names.
        $this->tenants->set($academy->toTenantRef());
        $this->branding->forget();
    }

    public static function directory(string $academyId): string
    {
        return "branding/{$academyId}";
    }
}
