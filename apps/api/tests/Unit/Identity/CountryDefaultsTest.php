<?php

use Modules\Identity\Domain\Onboarding\CountryDefaults;
use Modules\Identity\Filament\Console\Pages\NewAcademy;
use Modules\SharedKernel\Geo\Countries;

/*
 * What a new academy's country fills in (the console's new academy): every
 * country the platform lists, a currency the billing engine handles, a real
 * clock, a language the screens have, and a calling code.
 */

it('knows every listed country, in currencies, clocks and languages the platform has', function () {
    expect(array_keys(CountryDefaults::MAP))->toEqualCanonicalizing(Countries::CODES);
    foreach (CountryDefaults::MAP as $country => [$currency, $timezone, $locale, $dial]) {
        expect($currency)->toBeIn(NewAcademy::CURRENCIES, $country)
            ->and(in_array($timezone, DateTimeZone::listIdentifiers(), true))->toBeTrue($country)
            ->and($locale)->toBeIn(['ar', 'en'])
            ->and($dial)->toMatch('/^[1-9]\d{0,3}$/');
    }
    expect(CountryDefaults::of('tr'))->toBe(['currency' => 'USD', 'timezone' => 'Europe/Istanbul', 'locale' => 'en', 'dial' => '90'])
        ->and(CountryDefaults::of('XX'))->toBeNull()
        ->and(CountryDefaults::dial(null))->toBeNull();
});

it('makes an address from a name, held to the address\'s rule', function () {
    expect(NewAcademy::slugFrom('Jeyad Academy'))->toBe('jeyad-academy')
        ->and(NewAcademy::slugFrom('  Al-Noor  Center! '))->toBe('al-noor-center')
        ->and(NewAcademy::slugFrom('A'))->toBe('')
        ->and(strlen(NewAcademy::slugFrom(str_repeat('word ', 20))))->toBeLessThanOrEqual(40)
        ->and(NewAcademy::phone('05399262811', 'TR'))->toBe('+905399262811')
        ->and(NewAcademy::phone('+201012345678', 'TR'))->toBe('+201012345678')
        ->and(NewAcademy::phone('12', 'EG'))->toBeNull();
});
