<?php

namespace Modules\People\Filament\Family;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Actions\Consent\WithdrawConsent;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Models\GuardianStudent;
use Modules\People\Queries\FamilyConsents;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * A guardian's consent to each child's account: what the academy keeps and
 * why, "I agree" for a child who waits, and taking it back (the child is
 * signed out at once). Guardians only; it follows the consents live.
 */
final class FamilyPrivacy extends Page
{
    protected string $view = 'people::filament.family.privacy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'privacy';

    /** @var array<string, bool> child id => the box "I am their guardian and I agree" */
    public array $agreed = [];

    public static function canAccess(): bool
    {
        return auth()->check() && GuardianStudent::query()->where('guardian_id', auth()->id())->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess() && app(FamilyView::class)->stage((string) auth()->id()) === LearnerStage::Classic;
    }

    public static function getNavigationLabel(): string
    {
        return __('people::consent.family.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = count(app(FamilyConsents::class)->waiting((string) auth()->id()));

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('people::consent.family.title');
    }

    /** @return array{channel: string, age: int, children: list<array<string, mixed>>} */
    #[Computed]
    public function privacy(): array
    {
        $viewer = (string) auth()->id();
        $consents = app(FamilyConsents::class);
        $academy = app(BrandingApi::class)->current()->name();
        $tz = app(ViewerTimezone::class)->effective();
        $children = [];
        foreach ($consents->children($viewer) as $child) {
            $state = $child['state'];
            if (! $state->needed && ! $state->granted) {
                continue;
            }
            $children[] = [
                'id' => $child['student']['id'],
                'name' => $child['student']['name'],
                'waiting' => $state->waiting(),
                'granted' => $state->granted,
                'by' => $state->granted ? FamilyConsents::who($state, $viewer, $state->grantedAt?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y')) : null,
                'via' => $state->via,
                'text' => $consents->text($child['student']['name'], $academy),
            ];
        }

        return ['channel' => PersonalChannel::name($viewer), 'age' => $consents->text('', $academy)['age'], 'children' => $children];
    }

    public function agree(string $studentId): void
    {
        $this->resetErrorBag();
        if (! ($this->agreed[$studentId] ?? false)) {
            throw ValidationException::withMessages(["agreed.{$studentId}" => __('people::consent.link.tick')]);
        }
        app(GrantConsent::class)->handle($studentId, (string) auth()->id(), 'family', request()->ip(), request()->userAgent());
        $name = collect($this->privacy['children'])->firstWhere('id', $studentId)['name'] ?? '';
        unset($this->privacy);
        Notification::make()->success()->title(__('people::consent.family.agreed', ['student' => $name]))->send();
    }

    public function withdraw(string $studentId): void
    {
        $name = collect($this->privacy['children'])->firstWhere('id', $studentId)['name'] ?? '';
        app(WithdrawConsent::class)->handle($studentId, (string) auth()->id(), true);
        $this->agreed[$studentId] = false;
        unset($this->privacy);
        Notification::make()->success()->title(__('people::consent.family.withdrawn', ['student' => $name]))->send();
    }
}
