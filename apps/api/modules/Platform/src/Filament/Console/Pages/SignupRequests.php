<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Actions\Site\HandleSignupRequest;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\Site\SignupRequest;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Academies that asked to join from the platform's page: who to talk to,
 * what they asked for, and the answer: talked to them, turned it down, or
 * the academy made from it (New academy opens filled in).
 */
final class SignupRequests extends Page
{
    protected string $view = 'platform::console.signup-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'signup-requests';

    #[Url]
    public string $status = 'open';

    public static function getNavigationLabel(): string
    {
        return __('platform::site.requests.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = SignupRequest::query()->where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::site.requests.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::site.requests.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function requests(): array
    {
        $query = SignupRequest::query()->latest()->limit(200);
        match ($this->status) {
            'open' => $query->whereIn('status', ['new', 'contacted']),
            'new', 'contacted', 'created', 'declined' => $query->where('status', $this->status),
            default => null,
        };
        $plans = SaasPlan::query()->pluck('name_'.(app()->getLocale() === 'ar' ? 'ar' : 'en'), 'code');
        $tz = app(ViewerTimezone::class)->effective();
        $canMake = Route::has('filament.console.pages.new-academy');

        return $query->get()->map(fn (SignupRequest $r) => [
            'id' => $r->id,
            'academy' => $r->academy_name,
            'contact' => $r->contact_name,
            'email' => $r->email,
            'phone' => $r->phone,
            'whatsapp' => $r->phone !== null ? 'https://wa.me/'.ltrim($r->phone, '+') : null,
            'country' => $r->country,
            'students' => $r->students,
            'plan' => $r->plan_code !== null ? ($plans[$r->plan_code] ?? $r->plan_code) : null,
            'message' => $r->message,
            'utm' => trim(implode(' / ', array_filter([$r->utm_source, $r->utm_medium, $r->utm_campaign]))) ?: null,
            'status' => $r->status,
            'note' => $r->note,
            'when' => $r->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y، H:i'),
            'ago' => $r->created_at->locale(app()->getLocale())->diffForHumans(),
            'make' => $canMake && in_array($r->status, ['new', 'contacted'], true) ? route('filament.console.pages.new-academy', ['request' => $r->id]) : null,
        ])->all();
    }

    public function contactedAction(): Action
    {
        return Action::make('contacted')->label(__('platform::site.requests.contacted'))->icon(Heroicon::OutlinedPhone)->color('gray')->size('sm')
            ->action(fn (array $arguments) => $this->answer($arguments['request'], 'contacted', null));
    }

    public function declineAction(): Action
    {
        return Action::make('decline')->label(__('platform::site.requests.decline'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalHeading(__('platform::site.requests.decline'))->modalSubmitActionLabel(__('platform::site.requests.decline'))
            ->schema([Textarea::make('note')->label(__('platform::site.requests.note'))->helperText(__('platform::site.requests.note_hint'))->maxLength(500)->rows(3)])
            ->action(fn (array $data, array $arguments) => $this->answer($arguments['request'], 'declined', $data['note'] ?? null));
    }

    private function answer(string $requestId, string $status, ?string $note): void
    {
        $admin = auth('platform')->user();
        abort_unless($admin instanceof PlatformAdmin, 403);
        app(HandleSignupRequest::class)->handle($admin, $requestId, $status, $note);
        unset($this->requests);
        Notification::make()->success()->title(__('platform::site.requests.answered'))->send();
    }
}
