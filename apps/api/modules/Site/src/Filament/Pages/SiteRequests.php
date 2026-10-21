<?php

namespace Modules\Site\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\Site\Actions\HandleJoinRequest;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Support\RequestAccess;
use UnitEnum;

/**
 * Requests from the academy's page, for whoever answers them: free trial
 * lessons for the sales team (each is a lead in the CRM too, opened from
 * here), teachers asking to teach for who runs the teachers. Talked to them,
 * accepted or turned down, with a note for the academy.
 */
final class SiteRequests extends Page
{
    protected string $view = 'site::filament.site-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'site/requests';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $kind = 'all';

    public static function canAccess(): bool
    {
        return app(RequestAccess::class)->kinds() !== [];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('site::site.requests.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('site::site.requests.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $kinds = app(RequestAccess::class)->kinds();
        $new = $kinds === [] ? 0 : JoinRequest::query()->where('status', 'new')->whereIn('kind', $kinds)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('site::site.requests.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('site::site.requests.subtitle');
    }

    /** @return list<string> the kinds this person answers, for the filter */
    #[Computed]
    public function kinds(): array
    {
        return app(RequestAccess::class)->kinds();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function requests(): array
    {
        $kinds = $this->kinds;
        $query = JoinRequest::query()->whereIn('kind', in_array($this->kind, $kinds, true) ? [$this->kind] : $kinds)->latest()->limit(200);
        match ($this->status) {
            'open' => $query->whereIn('status', ['new', 'contacted']),
            'new', 'contacted', 'accepted', 'declined' => $query->where('status', $this->status),
            default => null,
        };
        $rows = $query->get();
        $subjects = app(CatalogApi::class)->subjectNames();
        $locale = app()->getLocale();
        $tz = app(ViewerTimezone::class)->effective();
        // The CRM's lead, by its page's route name: only where the CRM is (it sits above, and nothing here knows it).
        $leads = Gate::allows(Ability::ManageLeads->value) && Route::has('filament.academy.pages.leads.{lead}');

        $out = [];
        foreach ($rows as $r) {
            $names = [];
            foreach ($r->subject_ids as $id) {
                if (isset($subjects[$id])) {
                    $names[] = $subjects[$id];
                }
            }
            $out[] = [
                'id' => $r->id,
                'kind' => $r->kind,
                'name' => $r->name,
                'phone' => $r->phone,
                'whatsapp' => 'https://wa.me/'.ltrim($r->phone, '+'),
                'email' => $r->email,
                'country' => $r->country,
                'subject' => $r->subject_id !== null ? ($subjects[$r->subject_id] ?? null) : null,
                'subjects' => $names,
                'child' => $r->for_child ? $r->child_name : null,
                'born' => $r->for_child ? $r->child_birth_year : null,
                'years' => $r->years_teaching,
                'message' => $r->message,
                'utm' => trim(implode(' / ', array_filter([$r->utm_source, $r->utm_medium, $r->utm_campaign]))) ?: null,
                'status' => $r->status,
                'note' => $r->note,
                'when' => $r->created_at->setTimezone($tz)->locale($locale)->translatedFormat('j M Y، H:i'),
                'ago' => $r->created_at->locale($locale)->diffForHumans(),
                'lead' => $leads && $r->lead_id !== null ? route('filament.academy.pages.leads.{lead}', ['lead' => $r->lead_id]) : null,
            ];
        }

        return $out;
    }

    public function contactedAction(): Action
    {
        return Action::make('contacted')->label(__('site::site.requests.contacted'))->icon(Heroicon::OutlinedPhone)->color('gray')->size('sm')
            ->action(fn (array $arguments) => $this->answer((string) ($arguments['request'] ?? ''), 'contacted', null));
    }

    public function acceptAction(): Action
    {
        return Action::make('accept')->label(__('site::site.requests.accept'))->icon(Heroicon::OutlinedCheck)->color('success')->size('sm')
            ->modalHeading(__('site::site.requests.accept'))->modalSubmitActionLabel(__('site::site.requests.accept'))
            ->schema([Textarea::make('note')->label(__('site::site.requests.note'))->helperText(__('site::site.requests.note_hint'))->maxLength(500)->rows(3)])
            ->action(fn (array $data, array $arguments) => $this->answer((string) ($arguments['request'] ?? ''), 'accepted', $data['note'] ?? null));
    }

    public function declineAction(): Action
    {
        return Action::make('decline')->label(__('site::site.requests.decline'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalHeading(__('site::site.requests.decline'))->modalSubmitActionLabel(__('site::site.requests.decline'))
            ->schema([Textarea::make('note')->label(__('site::site.requests.note'))->helperText(__('site::site.requests.note_hint'))->maxLength(500)->rows(3)])
            ->action(fn (array $data, array $arguments) => $this->answer((string) ($arguments['request'] ?? ''), 'declined', $data['note'] ?? null));
    }

    private function answer(string $requestId, string $status, ?string $note): void
    {
        abort_unless(self::canAccess(), 403);
        app(HandleJoinRequest::class)->handle($requestId, $status, $note);
        unset($this->requests);
        Notification::make()->success()->title(__('site::site.requests.answered'))->send();
    }
}
