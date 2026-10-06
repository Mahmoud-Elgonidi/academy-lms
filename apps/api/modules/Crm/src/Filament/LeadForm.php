<?php

namespace Modules\Crm\Filament;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Support\Countries;
use Modules\SharedKernel\Time\Timezones;

/** A lead's form, for adding one and for correcting it: contact, learner, what and when, and where they came from. */
final class LeadForm
{
    /** @return list<mixed> */
    public static function fields(): array
    {
        $l = 'crm::leads.';
        $child = fn (Get $get) => (bool) $get('for_child');
        $year = CarbonImmutable::now()->year;
        $days = app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
        $hours = collect(range(0, 24))->mapWithKeys(fn (int $h) => [$h => sprintf('%02d:00', $h)])->all();

        return [
            Fieldset::make(__($l.'sections.contact'))->columns(2)->schema([
                TextInput::make('contact_name')->label(__($l.'fields.contact_name'))->helperText(__($l.'hints.contact_name'))->required()->maxLength(120),
                TextInput::make('phone_e164')->label(__($l.'fields.phone_e164'))->helperText(__($l.'hints.phone'))->tel()->required()->extraInputAttributes(['dir' => 'ltr']),
                Select::make('country')->label(__($l.'fields.country'))->options(fn () => Countries::options())->searchable()->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $state && isset(Countries::ZONES[$state]) ? $set('timezone', Countries::ZONES[$state]) : null),
                Select::make('timezone')->label(__($l.'fields.timezone'))->options(fn () => array_combine(Timezones::all(), Timezones::all()))->searchable()->required(),
                TextInput::make('email')->label(__($l.'fields.email'))->email()->maxLength(190)->extraInputAttributes(['dir' => 'ltr']),
                ToggleButtons::make('locale')->label(__($l.'fields.locale'))->options(__($l.'locales'))->inline()->required()->default('ar'),
            ]),
            Fieldset::make(__($l.'sections.learner'))->columns(2)->schema([
                Toggle::make('for_child')->label(__($l.'fields.for_child'))->live()->columnSpanFull(),
                TextInput::make('learner_name')->label(__($l.'fields.learner_name'))->maxLength(120)->visible($child)->required($child),
                Select::make('learner_birth_year')->label(__($l.'fields.learner_birth_year'))->visible($child)
                    ->options(array_combine(range($year - 3, $year - 25), range($year - 3, $year - 25))),
            ]),
            Fieldset::make(__($l.'sections.wants'))->columns(2)->schema([
                Select::make('subject_id')->label(__($l.'fields.subject_id'))->columnSpanFull()
                    ->options(fn () => DB::table('subjects')->orderBy('name_ar')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id')),
                CheckboxList::make('preferred_days')->label(__($l.'fields.preferred_days'))->columnSpanFull()->columns(4)
                    ->options(collect($days)->mapWithKeys(fn (int $d) => [$d => self::dayName($d)])->all()),
                Select::make('preferred_from')->label(__($l.'fields.preferred_from'))->options($hours)->helperText(__($l.'hints.times')),
                Select::make('preferred_to')->label(__($l.'fields.preferred_to'))->options($hours),
            ]),
            Fieldset::make(__($l.'sections.source'))->columns(2)->schema([
                Select::make('source')->label(__($l.'fields.source'))->options(LeadSource::options())->required()->default(LeadSource::WhatsApp->value),
                Textarea::make('notes')->label(__($l.'fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
                Section::make(__($l.'hints.utm'))->collapsed()->compact()->columnSpanFull()->schema([
                    Grid::make(3)->schema([
                        TextInput::make('utm_source')->label(__($l.'fields.utm_source'))->maxLength(120),
                        TextInput::make('utm_medium')->label(__($l.'fields.utm_medium'))->maxLength(120),
                        TextInput::make('utm_campaign')->label(__($l.'fields.utm_campaign'))->maxLength(120),
                    ]),
                ]),
            ]),
        ];
    }

    /** An ISO weekday's name in this locale. */
    public static function dayName(int $weekday): string
    {
        return CarbonImmutable::parse('2026-01-05')->addDays($weekday - 1)->locale(app()->getLocale())->dayName;
    }
}
