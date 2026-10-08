<?php

namespace Modules\Platform\Actions;

use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\TemplateCatalog;
use Modules\Platform\Models\AcademyBranding;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Applies a look to the current academy: a template as is, or edited tokens.
 * $templateRef is the template the tokens started from; it is kept even when
 * the tokens were edited, so the screen can say "Noor, edited". A look of its
 * own (edited, or a template it saved) is an area of the academy's plan; a
 * built-in template, or the look it already has, is always allowed.
 */
final class SaveBranding
{
    public function __construct(
        private readonly TemplateCatalog $catalog,
        private readonly BrandingService $branding,
        private readonly AcademyPlan $plan,
    ) {}

    public function handle(BrandTokens $tokens, ?string $templateRef, ?string $actorId = null): AcademyBranding
    {
        $template = $this->catalog->find($templateRef);
        $ref = $template !== null ? $templateRef : null;

        $row = AcademyBranding::query()->firstOrNew();
        $builtIn = $template !== null && $template['builtIn'] && $template['tokens']->equals($tokens);
        $unchanged = $row->exists && is_array($row->tokens) && self::same($row->tokens, $tokens);
        if (! $builtIn && ! $unchanged) {
            $this->plan->requireFeature(Feature::CustomBranding);
        }

        $row->fill([
            'template_ref' => $ref,
            'tokens' => $tokens->toArray(),
            'updated_by' => $actorId,
        ])->save();

        $this->branding->forget();

        return $row;
    }

    /** @param  array<string, mixed>  $stored */
    private static function same(array $stored, BrandTokens $tokens): bool
    {
        try {
            return BrandTokens::fromArray($stored)->equals($tokens);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
