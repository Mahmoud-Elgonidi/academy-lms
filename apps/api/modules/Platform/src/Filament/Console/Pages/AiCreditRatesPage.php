<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Metering\SaveAiCreditRates;
use Modules\Platform\Metering\AiCreditRates;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Console › AI credit rates (owners only): what each AI capability costs an
 * academy in credits, a base for each request and so much for each minute of
 * audio heard, beside the capability's own rate. The next request is charged
 * at the new rate; the academies read the rates on their usage page.
 */
final class AiCreditRatesPage extends Page
{
    protected string $view = 'platform::console.ai-credit-rates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 33;

    protected static ?string $slug = 'ai/credit-rates';

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::metering.rates.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::metering.rates.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::metering.rates.subtitle');
    }

    /** "2 credits + 1 credit a minute of audio". */
    public static function rateText(int $base, int $perMinute): string
    {
        $text = trans_choice('platform::metering.units.ai_credits', $base, ['n' => number_format($base)]);
        if ($perMinute > 0) {
            $minute = trans_choice('platform::metering.rates.per_minute', $perMinute, ['n' => number_format($perMinute)]);
            $text = $base > 0 ? __('platform::metering.rates.and', ['base' => $text, 'minute' => $minute]) : $minute;
        }

        return $text;
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function rows(): array
    {
        return array_values(array_map(fn (array $r) => [
            'key' => $r['capability']->key,
            'label' => $r['capability']->label(),
            'hears' => $r['capability']->needsSpeech(),
            'rate' => self::rateText($r['base'], $r['per_minute']),
            'default' => self::rateText($r['default_base'], $r['default_per_minute']),
            'own' => $r['own'],
        ], app(AiCreditRates::class)->all()));
    }

    public function editAction(): Action
    {
        $l = 'platform::metering.rates.';

        return Action::make('edit')->label(__($l.'edit'))->icon(Heroicon::OutlinedPencilSquare)->size('sm')->color('gray')
            ->modalHeading(fn (array $arguments) => __($l.'heading', ['capability' => collect($this->rows)->firstWhere('key', $arguments['capability'] ?? '')['label'] ?? '']))
            ->modalSubmitActionLabel(__($l.'save'))
            ->fillForm(function (array $arguments): array {
                $rate = app(AiCreditRates::class)->for((string) ($arguments['capability'] ?? ''));

                return ['base' => $rate['base'], 'per_minute' => $rate['per_minute']];
            })
            ->schema(fn (array $arguments) => [
                TextInput::make('base')->label(__($l.'base'))->helperText(__($l.'base_hint'))->numeric()->integer()->minValue(0)->maxValue(SaveAiCreditRates::MAX)->required(),
                TextInput::make('per_minute')->label(__($l.'minute'))->helperText(__($l.'minute_hint'))->numeric()->integer()->minValue(0)->maxValue(SaveAiCreditRates::MAX)
                    ->visible(fn () => (bool) (collect($this->rows)->firstWhere('key', $arguments['capability'] ?? '')['hears'] ?? false)),
            ])
            ->action(function (array $data, array $arguments): void {
                $admin = auth('platform')->user();
                abort_unless($admin instanceof PlatformAdmin, 403);
                app(SaveAiCreditRates::class)->handle($admin, [(string) $arguments['capability'] => ['base' => $data['base'], 'per_minute' => $data['per_minute'] ?? 0]]);
                Notification::make()->success()->title(__('platform::metering.rates.saved'))->send();
                unset($this->rows);
            });
    }
}
