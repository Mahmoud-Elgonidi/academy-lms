<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Filament\Resources\Plans\ManagePlans;
use Modules\Billing\Models\PriceRegion;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * A price region names the countries its families live in (ISO codes, each
 * country in one region at most), so the academy's page shows a visitor the
 * prices of theirs; other modules read them from BillingApi::regions().
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->actingAs(User::factory()->withRole(Role::Accountant)->create());
});

it('keeps each region\'s countries, cleaned, and gives them to other modules', function () {
    app(SavePriceRegions::class)->handle([
        ['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'countries' => ['sa', ' KW ', 'SA']],
        ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'countries' => ['EG']],
        ['name_ar' => 'أوروبا', 'name_en' => 'Europe', 'currency' => 'EUR'],
    ]);

    $regions = app(BillingApi::class)->regions();
    expect(array_map(fn ($r) => [$r->currency, $r->countries], $regions))->toBe([['SAR', ['SA', 'KW']], ['EGP', ['EG']], ['EUR', []]]);

    // A row that does not say its countries keeps them; one that does replaces them.
    $ids = PriceRegion::query()->orderBy('sort')->pluck('id')->all();
    app(SavePriceRegions::class)->handle([
        ['id' => $ids[0], 'name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true],
        ['id' => $ids[1], 'name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'countries' => ['EG', 'SD']],
        ['id' => $ids[2], 'name_ar' => 'أوروبا', 'name_en' => 'Europe', 'currency' => 'EUR', 'countries' => ['DE', 'FR']],
    ]);
    expect(PriceRegion::query()->orderBy('sort')->pluck('countries')->all())->toBe([['SA', 'KW'], ['EG', 'SD'], ['DE', 'FR']]);
});

it('puts a country in one region only, and only countries it knows', function () {
    $twice = [
        ['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'countries' => ['SA', 'EG']],
        ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'countries' => ['EG']],
    ];
    expect(fn () => app(SavePriceRegions::class)->handle($twice))->toThrow(ValidationException::class)
        ->and(fn () => app(SavePriceRegions::class)->handle([['name_ar' => 'س', 'name_en' => 'X', 'currency' => 'SAR', 'countries' => ['XX']]]))->toThrow(ValidationException::class)
        ->and(PriceRegion::query()->count())->toBe(0);
});

it('chooses the countries in the regions dialog', function () {
    Filament::setCurrentPanel('academy');
    Repeater::fake();
    PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'countries' => ['SA']]);

    $page = Livewire::test(ManagePlans::class)->mountAction('regions');
    $row = array_key_first($page->get('mountedActions.0.data.regions'));
    expect($page->get("mountedActions.0.data.regions.{$row}.countries"))->toBe(['SA']);
    $page->set("mountedActions.0.data.regions.{$row}.countries", ['SA', 'QA'])->callMountedAction()->assertHasNoActionErrors();

    expect(PriceRegion::query()->value('countries'))->toBe(['SA', 'QA']);
});
