<?php

namespace Modules\SharedKernel\Filament;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
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
 *
 * The person may hide it from its card (hide); it stays hidden on every page,
 * the portal and the app until they show it again where their account's
 * settings are (OwnTimezone::chipComesBack), which tells the chip to look again.
 */
final class ZoneChip extends Component
{
    /** The session's note of the browser's zone, so later pages know it before their script runs. */
    public const DEVICE = 'lms.device_zone';

    /** The browser event that has the chip read again whether it shows (dispatched by whoever changes it elsewhere). */
    public const RECONSIDER = 'lms-zone-chip';

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

    /** The person hides the chip (it has already faded in the page); the notice says where it comes back. */
    public function hide(): void
    {
        $user = $this->user();
        $saver = app(OwnTimezones::class)->for($user);
        if ($user === null || $saver === null) {
            throw ValidationException::withMessages(['zone' => __('shared::chrome.zone.cannot')]);
        }
        $saver->setChipShown($user, false);
        $this->changing = false;

        Notification::make()->title(__('shared::chrome.zone.hidden'))->body($saver->chipComesBack())->success()->send();
    }

    /** Shown or hidden elsewhere (My settings, the console's account menu): the chip looks again. */
    #[On(self::RECONSIDER)]
    public function reconsider(): void {}

    public function render(): View
    {
        $saver = app(OwnTimezones::class)->for($this->user());
        if ($saver !== null && ! $saver->chipShown($this->user())) {
            return view('shared::chrome.zone-chip', ['hidden' => true]);
        }

        $viewer = app(ViewerTimezone::class);
        $zone = $viewer->profile() ?? $viewer->effective();
        $academy = $viewer->academy();
        $clock = fn (string $tz): string => CarbonImmutable::now($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('H:i'));
        $row = fn (string $tz): array => ['zone' => $tz, 'city' => Timezones::city($tz), 'label' => Timezones::label($tz), 'time' => $clock($tz)];
        $deviceDiffers = $this->device !== null && ! Timezones::sameClock($this->device, $zone);

        return view('shared::chrome.zone-chip', [
            'hidden' => false,
            'mine' => $row($zone),
            'academyRow' => $academy !== null && ! Timezones::sameClock($academy, $zone) ? $row($academy) : null,
            'deviceRow' => $deviceDiffers ? $row($this->device) : null,
            'canChange' => $saver !== null,
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
