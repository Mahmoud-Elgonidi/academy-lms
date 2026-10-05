<?php

use Illuminate\Validation\ValidationException;
use Modules\Platform\Actions\DeleteBrandTemplate;
use Modules\Platform\Actions\SaveBranding;
use Modules\Platform\Actions\SaveBrandTemplate;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\Platform\Actions\UpdateBrandTemplate;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\DTOs\Branding;
use Modules\Platform\Models\BrandingTemplate;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_ar' => 'أكاديمية النور', 'name_en' => 'Noor Academy']));
});

function tokens(array $change = []): BrandTokens
{
    return BrandTokens::fromArray(array_merge(BuiltInTemplates::tokens('noor')->toArray(), $change));
}

function branding(): Branding
{
    $service = app(BrandingApi::class);
    $service->forget();

    return $service->current();
}

it('starts every academy on the default template', function () {
    expect(branding()->templateRef)->toBe('builtin:noor')
        ->and(branding()->tokens['primary'])->toBe('#0D735E')
        ->and(branding()->modified)->toBeFalse();
});

it('applies a built-in template, and notices when its colours are edited', function () {
    app(SaveBranding::class)->handle(BuiltInTemplates::tokens('violet'), 'builtin:violet');
    expect(branding()->templateName)->toBe('Violet')->and(branding()->modified)->toBeFalse();

    app(SaveBranding::class)->handle(tokens(['primary' => '#5A2D82', 'heading_font' => 'El Messiri', 'body_font' => 'Cairo']), 'builtin:violet');
    expect(branding()->templateRef)->toBe('builtin:violet')
        ->and(branding()->modified)->toBeTrue()
        ->and(branding()->tokens['primary'])->toBe('#5A2D82');
});

it('saves edits as a named template and applies it', function () {
    $template = app(SaveBrandTemplate::class)->handle('أخضر الأكاديمية', tokens(['primary' => '#14532D']));

    expect(branding()->templateRef)->toBe('saved:'.$template->id)
        ->and(branding()->templateName)->toBe('أخضر الأكاديمية')
        ->and(branding()->tokens['primary'])->toBe('#14532D');
});

it('refuses a template name that is taken, by the academy or a built-in one', function (string $name) {
    app(SaveBrandTemplate::class)->handle('Ours', tokens());

    app(SaveBrandTemplate::class)->handle($name, tokens());
})->throws(ValidationException::class)->with(['ours', 'Violet', 'النور', '  ']);

it('updates a saved template and the look of an academy using it', function () {
    $template = app(SaveBrandTemplate::class)->handle('Ours', tokens());

    app(UpdateBrandTemplate::class)->handle($template->id, tokens(['primary' => '#7C2D12']), 'Ours, warmer');

    expect($template->refresh()->name)->toBe('Ours, warmer')
        ->and(branding()->tokens['primary'])->toBe('#7C2D12')
        ->and(branding()->modified)->toBeFalse();
});

it('keeps the look when the template in use is deleted, as custom', function () {
    $template = app(SaveBrandTemplate::class)->handle('Ours', tokens(['primary' => '#7C2D12']));

    app(DeleteBrandTemplate::class)->handle($template->id);

    expect(branding()->templateRef)->toBeNull()
        ->and(branding()->tokens['primary'])->toBe('#7C2D12');
});

it('renames the academy and refuses logo paths outside its own folder', function () {
    $own = UpdateAcademyIdentity::directory($this->academy->id).'/logo.png';

    app(UpdateAcademyIdentity::class)->handle('أكاديمية الفجر', 'Fajr Academy', $own, null);
    expect(branding()->nameEn)->toBe('Fajr Academy')
        ->and(branding()->logoUrl)->toEndWith($own);

    expect(fn () => app(UpdateAcademyIdentity::class)->handle('أ', 'A', 'branding/someone-else/logo.png', null))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateAcademyIdentity::class)->handle('أ', 'A', '../../.env', null))
        ->toThrow(ValidationException::class);
});

it('keeps each academy\'s templates to itself', function () {
    app(SaveBrandTemplate::class)->handle('Ours', tokens());
    $this->actAsTenant($this->createAcademy(['slug' => 'fajr']));

    expect(BrandingTemplate::query()->count())->toBe(0)
        ->and(branding()->templateRef)->toBe('builtin:noor');
});

it('serves the look to the apps before sign-in', function () {
    app(SaveBranding::class)->handle(BuiltInTemplates::tokens('sky'), 'builtin:sky');
    $this->clearTenant();

    $this->withHeader('X-Academy', 'noor')->getJson('/api/v1/branding')
        ->assertOk()
        ->assertJsonPath('data.name.en', 'Noor Academy')
        ->assertJsonPath('data.tokens.primary', '#1D5FB8')
        ->assertJsonPath('data.template.ref', 'builtin:sky')
        ->assertJsonPath('data.fonts_url', 'https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap');
});
