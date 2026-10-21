<?php

namespace Modules\Identity\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Staff\ChangeStaffRoles;
use Modules\Identity\Actions\Staff\DeactivateStaff;
use Modules\Identity\Actions\Staff\InviteStaff;
use Modules\Identity\Actions\Staff\ReactivateStaff;
use Modules\Identity\Actions\Staff\SendStaffWelcome;
use Modules\Identity\Actions\Staff\StaffWelcomeLink;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Domain\Staff\StaffRules;
use Modules\Identity\Enums\Role;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The academy's team (Settings › Team; docs/architecture/02, section 19):
 * who works in the back office, with which roles, whether their account is
 * on, whether two-step sign-in is (never its secret), and when they last
 * came in. The owner and admins invite people (a new person, or a teacher
 * already here), change roles, and turn accounts off and on, each through
 * its Action and up to their own rank (StaffRules); a new person's welcome
 * link can be copied while the academy's mail is not set up.
 */
final class Team extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'identity::team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'settings/team';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageStaff->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity::team.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::team.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('identity::team.subtitle');
    }

    /** @return array{members: int, invited: int, two_step: int, off: int} */
    public function tiles(): array
    {
        $active = fn () => self::staff()->where('status', UserStatus::Active->value);

        return [
            'members' => $active()->count(),
            'invited' => $active()->whereNull('password')->count(),
            'two_step' => $active()->whereNull('app_authentication_secret')
                ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, TwoFactor::REQUIRED_FOR)))->count(),
            'off' => self::staff()->where('status', UserStatus::Disabled->value)->count(),
        ];
    }

    /** @return Builder<User> everyone holding a back-office role, erased accounts aside */
    private static function staff(): Builder
    {
        return User::query()->where('status', '!=', UserStatus::Erased->value)
            ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, StaffRules::ROLES)));
    }

    protected function getHeaderActions(): array
    {
        $l = 'identity::team.invite.';

        return [
            Action::make('invite')->label(__($l.'action'))->icon(Heroicon::OutlinedUserPlus)
                ->modalHeading(__($l.'heading'))->modalDescription(__($l.'description'))->modalSubmitActionLabel(__($l.'submit'))
                ->schema(fn () => [
                    ToggleButtons::make('who')->label(__($l.'who'))->inline()->live()->default('new')
                        ->options(__($l.'who_options'))->visible(fn () => $this->teachers() !== []),
                    Select::make('teacher_id')->label(__($l.'teacher'))->helperText(__($l.'teacher_hint'))->searchable()->required()
                        ->options(fn () => $this->teachers())->visible(fn (Get $get) => $get('who') === 'teacher'),
                    TextInput::make('name')->label(__($l.'name'))->required()->maxLength(255)->visible(fn (Get $get) => $get('who') !== 'teacher'),
                    TextInput::make('email')->label(__($l.'email'))->email()->maxLength(190)->extraInputAttributes(['dir' => 'ltr'])
                        ->visible(fn (Get $get) => $get('who') !== 'teacher'),
                    TextInput::make('phone')->label(__($l.'phone'))->tel()->maxLength(30)->helperText(__($l.'phone_hint'))->extraInputAttributes(['dir' => 'ltr'])
                        ->visible(fn (Get $get) => $get('who') !== 'teacher'),
                    Select::make('timezone')->label(__($l.'timezone'))->searchable()->required()
                        ->options(fn () => self::timezones())->default(fn () => app(TenantContext::class)->require()->defaultTimezone)
                        ->visible(fn (Get $get) => $get('who') !== 'teacher'),
                    ToggleButtons::make('locale')->label(__($l.'locale'))->inline()->options(__($l.'locales'))
                        ->default(fn () => app(TenantContext::class)->require()->defaultLocale === 'en' ? 'en' : 'ar')
                        ->visible(fn (Get $get) => $get('who') !== 'teacher'),
                    $this->rolesField()->required(),
                    Toggle::make('welcome')->label(__($l.'welcome'))->helperText(__($l.'welcome_hint'))->default(true)
                        ->visible(fn (Get $get) => $get('who') !== 'teacher'),
                ])
                ->action(fn (array $data) => $this->invite($data)),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function invite(array $data): void
    {
        $l = 'identity::team.invite.';
        $roles = self::chosen($data);

        if (($data['who'] ?? 'new') === 'teacher') {
            $user = $this->attempt(fn () => app(ChangeStaffRoles::class)->handle((string) $data['teacher_id'], $roles, $this->myId()));
            Notification::make()->success()->title(__($l.'joined', ['name' => $user->name]))->send();

            return;
        }

        $timezone = (string) ($data['timezone'] ?? app(TenantContext::class)->require()->defaultTimezone);
        $raw = trim((string) ($data['phone'] ?? ''));
        $phone = $raw === '' ? null : Phone::normalize($raw, Phone::callingCodeFor($timezone));
        $email = trim((string) ($data['email'] ?? ''));
        $this->attempt(function () use ($raw, $phone, $email, $l) {
            if ($raw !== '' && $phone === null) {
                throw ValidationException::withMessages(['phone' => __($l.'phone_invalid', ['value' => $raw])]);
            }
            if ($email === '' && $phone === null) {
                throw ValidationException::withMessages(['email' => __($l.'contact_required')]);
            }
        });

        $welcome = (bool) ($data['welcome'] ?? true);
        $user = $this->attempt(fn () => app(InviteStaff::class)->handle(
            new UserDetails(trim((string) $data['name']), $email === '' ? null : $email, $phone, $data['locale'] ?? null, $timezone),
            $roles, $welcome, $this->myId(),
        ));
        Notification::make()->success()->title(__($l.'done', ['name' => $user->name]))->body(__($l.($welcome ? 'done_welcome' : 'done_link')))->send();

        // No welcome sent: their link, to pass on by hand, right away.
        if (! $welcome) {
            $this->replaceMountedAction('link', context: ['table' => true, 'recordKey' => $user->id]);
        }
    }

    public function table(Table $table): Table
    {
        $c = 'identity::team.columns.';
        $tz = app(ViewerTimezone::class)->effective();
        $roleName = fn (Role $role) => __('identity::users.roles.'.$role->value);

        return $table
            ->query(fn () => self::staff()->with('roleRecords')->select([
                'users.id', 'users.academy_id', 'users.name', 'users.email', 'users.phone_e164', 'users.username',
                'users.status', 'users.is_managed_minor', 'users.last_sign_in_at', 'users.created_at',
            ])
                // Whether a password and an authenticator app are set, never the columns themselves.
                ->selectRaw('(users.password is not null) as has_password')
                ->selectRaw('(users.app_authentication_secret is not null) as has_two_factor'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__($c.'name'))->weight('semibold')->sortable()
                    ->searchable(['name', 'email', 'phone_e164'])
                    ->description(fn (User $u) => self::contact($u).($u->id === $this->myId() ? ' · '.__('identity::team.you') : '')),
                TextColumn::make('roles')->label(__($c.'roles'))->badge()
                    ->state(fn (User $u) => array_map($roleName, $u->roles()))
                    ->color(fn (string $state) => match ($state) {
                        $roleName(Role::Owner) => 'primary',
                        $roleName(Role::Admin) => 'info',
                        $roleName(Role::Teacher), $roleName(Role::Student), $roleName(Role::Guardian) => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('status')->label(__($c.'status'))->badge()
                    ->state(fn (User $u) => __('identity::team.statuses.'.self::status($u)))
                    ->color(fn (User $u) => match (self::status($u)) {
                        'disabled' => 'danger',
                        'invited' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('two_factor')->label(__($c.'two_factor'))->badge()
                    ->state(fn (User $u) => __('identity::team.two_factor.'.self::twoFactor($u)))
                    ->color(fn (User $u) => match (self::twoFactor($u)) {
                        'on' => 'success',
                        'missing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_sign_in_at')->label(__($c.'last_sign_in'))->sortable()->visibleFrom('md')
                    ->state(fn (User $u) => $u->last_sign_in_at === null ? __('identity::team.never')
                        : CarbonImmutable::parse($u->last_sign_in_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'))
                    ->color(fn (User $u) => $u->last_sign_in_at === null ? 'gray' : null),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('identity::team.filters.role'))
                    ->options(collect(StaffRules::ROLES)->mapWithKeys(fn (Role $r) => [$r->value => $roleName($r)])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $role) => $q->whereHas('roleRecords', fn ($r) => $r->where('role', $role)))),
                TernaryFilter::make('active')->label(__('identity::team.filters.status'))
                    ->trueLabel(__('identity::team.filters.active'))->falseLabel(__('identity::team.filters.off'))
                    ->queries(true: fn (Builder $query) => $query->where('status', UserStatus::Active->value), false: fn (Builder $query) => $query->where('status', UserStatus::Disabled->value)),
            ])
            ->recordActions([
                $this->rolesAction(),
                ActionGroup::make([
                    $this->linkAction(),
                    $this->resendAction(),
                    $this->deactivateAction(),
                    $this->reactivateAction(),
                ]),
            ])
            ->emptyStateHeading(__('identity::team.empty'))
            ->emptyStateDescription(__('identity::team.empty_hint'))
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->paginated([25, 50, 100]);
    }

    private function rolesAction(): Action
    {
        $a = 'identity::team.actions.';

        return Action::make('roles')->label(__($a.'roles'))->icon(Heroicon::OutlinedKey)->color('gray')
            ->visible(fn (User $record) => StaffRules::whyNotTouch($this->myRoles(), $record->id === $this->myId(), $record->roles()) === null)
            ->modalHeading(fn (User $record) => __($a.'roles_heading', ['name' => $record->name]))
            ->modalDescription(__($a.'roles_hint'))
            ->fillForm(fn (User $record) => ['roles' => array_map(fn (Role $r) => $r->value, StaffRules::staffOf($record->roles()))])
            ->schema([$this->rolesField()])
            ->action(function (User $record, array $data) use ($a) {
                $this->attempt(fn () => app(ChangeStaffRoles::class)->handle($record->id, self::chosen($data), $this->myId()));
                Notification::make()->success()->title(__($a.'roles_saved'))->send();
            });
    }

    private function linkAction(): Action
    {
        $a = 'identity::team.actions.';

        return Action::make('link')->label(__($a.'link'))->icon(Heroicon::OutlinedLink)->color('gray')
            ->visible(fn (User $record) => $this->waitsForPassword($record))
            ->modalHeading(fn (User $record) => __($a.'link_heading', ['name' => $record->name]))
            ->modalDescription(__($a.'link_hint', ['days' => WelcomeController::LINK_DAYS]))
            // Made (and written in the activity log) when the window opens, never before.
            ->fillForm(fn (User $record) => ['link' => $this->attempt(fn () => app(StaffWelcomeLink::class)->handle($record->id, $this->myId()))])
            ->schema([
                TextInput::make('link')->label(__($a.'link_field'))->readOnly()->copyable(copyMessage: __($a.'copied'))->extraInputAttributes(['dir' => 'ltr']),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('shared::chrome.close'));
    }

    private function resendAction(): Action
    {
        $a = 'identity::team.actions.';

        return Action::make('resend')->label(__($a.'resend'))->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
            ->visible(fn (User $record) => $this->waitsForPassword($record) && ($record->email !== null || $record->phone_e164 !== null))
            ->action(function (User $record) use ($a) {
                $this->attempt(fn () => app(SendStaffWelcome::class)->handle($record->id, $this->myId()));
                Notification::make()->success()->title(__($a.'resent'))->send();
            });
    }

    private function deactivateAction(): Action
    {
        $a = 'identity::team.actions.';

        return Action::make('deactivate')->label(__($a.'deactivate'))->icon(Heroicon::OutlinedLockClosed)->color('danger')
            ->visible(fn (User $record) => $record->isActive()
                && StaffRules::whyNotDeactivate($this->myRoles(), $record->id === $this->myId(), $record->roles(), PHP_INT_MAX) === null)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => __($a.'deactivate_heading', ['name' => $record->name]))
            ->modalDescription(__($a.'deactivate_body'))
            ->modalSubmitActionLabel(__($a.'deactivate'))
            ->action(function (User $record) use ($a) {
                $this->attempt(fn () => app(DeactivateStaff::class)->handle($record->id, $this->myId()));
                Notification::make()->success()->title(__($a.'deactivated'))->send();
            });
    }

    private function reactivateAction(): Action
    {
        $a = 'identity::team.actions.';

        return Action::make('reactivate')->label(__($a.'reactivate'))->icon(Heroicon::OutlinedLockOpen)->color('success')
            ->visible(fn (User $record) => $record->status === UserStatus::Disabled
                && StaffRules::whyNotReactivate($this->myRoles(), $record->id === $this->myId(), $record->roles()) === null)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => __($a.'reactivate_heading', ['name' => $record->name]))
            ->modalDescription(__($a.'reactivate_body'))
            ->modalSubmitActionLabel(__($a.'reactivate'))
            ->action(function (User $record) use ($a) {
                $this->attempt(fn () => app(ReactivateStaff::class)->handle($record->id, $this->myId()));
                Notification::make()->success()->title(__($a.'reactivated'))->send();
            });
    }

    /** The back-office roles the person signed in may give, each with what it allows. */
    private function rolesField(): CheckboxList
    {
        $grantable = StaffRules::grantable($this->myRoles());

        return CheckboxList::make('roles')->label(__('identity::team.invite.roles'))->columns(2)
            ->options(collect($grantable)->mapWithKeys(fn (Role $r) => [$r->value => __('identity::users.roles.'.$r->value)])->all())
            ->descriptions(collect($grantable)->mapWithKeys(fn (Role $r) => [$r->value => __('identity::team.role_hints.'.$r->value)])->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<Role> the roles ticked in a form
     */
    private static function chosen(array $data): array
    {
        return array_values(array_filter(array_map(fn ($r) => Role::tryFrom((string) $r), (array) ($data['roles'] ?? []))));
    }

    /** @return array<string, string> active teachers not on the team yet, who may join it */
    private function teachers(): array
    {
        return User::query()->where('status', UserStatus::Active->value)->where('is_managed_minor', false)
            ->whereHas('roleRecords', fn ($q) => $q->where('role', Role::Teacher->value))
            ->whereDoesntHave('roleRecords', fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, StaffRules::ROLES)))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    private function waitsForPassword(User $user): bool
    {
        return $user->isActive() && ! $user->getAttribute('has_password')
            && StaffRules::whyNotTouch($this->myRoles(), $user->id === $this->myId(), $user->roles()) === null;
    }

    private static function status(User $user): string
    {
        return match (true) {
            ! $user->isActive() => 'disabled',
            ! $user->getAttribute('has_password') => 'invited',
            default => 'active',
        };
    }

    private static function twoFactor(User $user): string
    {
        return match (true) {
            (bool) $user->getAttribute('has_two_factor') => 'on',
            TwoFactor::requiredFor($user) => 'missing',
            default => 'off',
        };
    }

    /** Email and number as one line, each kept left to right inside Arabic text. */
    private static function contact(User $user): string
    {
        $ltr = fn (?string $s) => $s === null ? null : "\u{2066}{$s}\u{2069}";

        return implode(' · ', array_filter([$ltr($user->email), $ltr($user->phone_e164), $user->email === null && $user->phone_e164 === null ? $ltr($user->username) : null])) ?: '—';
    }

    /** @return array<string, string> */
    private static function timezones(): array
    {
        $zones = Timezones::all();

        return array_combine($zones, array_map(fn (string $z) => Timezones::city($z).' · '.$z, $zones));
    }

    private function myId(): string
    {
        return (string) auth()->id();
    }

    /** @return list<Role> */
    private function myRoles(): array
    {
        $me = auth()->user();

        return $me instanceof User ? $me->roles() : [];
    }

    /**
     * Runs a change; what it refuses is said in a notification and the
     * window stays as it was.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function attempt(Closure $change): mixed
    {
        try {
            return $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }

        throw new Halt;
    }
}
