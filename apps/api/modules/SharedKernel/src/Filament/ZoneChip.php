<?php

namespace Modules\SharedKernel\Filament;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\OwnTimezones;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The zone chip in the corner of every panel (shared::chrome.zone): the city
 * of the person's own zone and its time, ticking in the page. Opened, it
 * shows the academy's zone and the device's when they keep another clock,
 * and changes the person's own zone through the module that owns their
 * account (OwnTimezones), then reloads the page so every time follows.
 *
 * The panels write times on the account's zone, never the device's: the page
 * tells the chip the browser's zone (noteDevice) only so it can say when the
 * two differ, and offer to use it.
 */
final class ZoneChip extends Component
{
    /** The session's note of the browser's zone, so later pages know it before their script runs. */
    public const DEVICE = 'lms.device_zone';

    /** The browser's zone, as its script read it (null until it has). */
    public ?string $device = null;

    /** The list of zones is open. */
    public bool $changing = false;

    public function mount(): void
    {
        $device = session(self::DEVICE);
        $this->device = Timezones::isValid($device) ? $device : null;
    }

    /** The browser's zone, sent by the chip's script once a session (and again when it changes). */
    public function noteDevice(string $timezone): void
    {
        if (! Timezones::isValid($timezone)) {
            return;
        }
        $this->device = $timezone;
        session([self::DEVICE => $timezone]);
    }

    public function useDevice(): void
    {
        if ($this->device !== null) {
            $this->choose($this->device);
        }
    }

    public function choose(string $timezone): void
    {
        if (! Timezones::isValid($timezone)) {
            throw ValidationException::withMessages(['zone' => __('shared::chrome.zone.invalid')]);
        }
        $user = $this->user();
        $saver = app(OwnTimezones::class)->for($user);
        if ($user === null || $saver === null) {
            throw ValidationException::withMessages(['zone' => __('shared::chrome.zone.cannot')]);
        }
        $saver->save($user, $timezone);

        Notification::make()->title(__('shared::chrome.zone.saved', ['zone' => Timezones::label($timezone)]))->success()->send();
        // Every time on the page was written on the old zone: the page comes again (only ever a page of this host).
        $this->changing = false;
        $back = (string) request()->header('Referer');
        $this->redirect(parse_url($back, PHP_URL_HOST) === request()->getHost() ? $back : (Filament::getCurrentPanel()?->getUrl() ?? url('/')));
    }

    public function render(): View
    {
        $viewer = app(ViewerTimezone::class);
        $user = $this->user();
        $zone = $viewer->profile() ?? $viewer->effective();
        $academy = $viewer->academy();
        $clock = fn (string $tz): string => CarbonImmutable::now($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'));
        $row = fn (string $tz): array => ['zone' => $tz, 'city' => Timezones::city($tz), 'label' => Timezones::label($tz), 'time' => $clock($tz)];
        $deviceDiffers = $this->device !== null && ! Timezones::sameClock($this->device, $zone);

        return view('shared::chrome.zone-chip', [
            'mine' => $row($zone),
            'academyRow' => $academy !== null && ! Timezones::sameClock($academy, $zone) ? $row($academy) : null,
            'deviceRow' => $deviceDiffers ? $row($this->device) : null,
            'canChange' => app(OwnTimezones::class)->for($user) !== null,
            'zones' => $this->changing ? Timezones::catalog() : [],
            'hourCycle' => Clock::hourCycle(),
            // Intl writes the time as Carbon does: the reader's language, Latin digits.
            'intlLocale' => app()->getLocale() === 'ar' ? 'ar-u-nu-latn' : app()->getLocale(),
        ]);
    }

    private function user(): ?Authenticatable
    {
        return Filament::auth()->user();
    }
}
