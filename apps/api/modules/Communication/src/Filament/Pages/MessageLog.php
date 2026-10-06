<?php

namespace Modules\Communication\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification as Toast;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Communication\Actions\ResendDelivery;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Models\Delivery;
use Modules\Communication\Models\Notification;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/** Every message to every person and what each channel did with it; failed ones can be sent again. */
final class MessageLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'communication::filament.message-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'messages';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewMessageLog->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('communication::communication.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('communication::communication.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = Delivery::query()->where('status', 'failed')->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getTitle(): string|Htmlable
    {
        return __('communication::communication.log.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('communication::communication.log.subtitle');
    }

    public function table(Table $table): Table
    {
        $tz = app(ViewerTimezone::class)->effective();
        $l = 'communication::communication.';

        return $table
            ->query(Notification::query()->with('deliveries')
                ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'notifications.academy_id')->on('u.id', '=', 'notifications.user_id'))
                ->select('notifications.*', 'u.name as recipient'))
            ->defaultSort('notifications.created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__($l.'log.when'))
                    ->state(fn (Notification $n) => $n->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('D j M · H:i'))
                    ->description(fn (Notification $n) => $n->read_at ? __($l.'log.read', ['time' => $n->read_at->locale(app()->getLocale())->diffForHumans()]) : __($l.'log.unread')),
                TextColumn::make('recipient')->label(__($l.'log.to'))->searchable('u.name')->weight('semibold'),
                TextColumn::make('title')->label(__($l.'log.message'))->wrap()->searchable('notifications.title')
                    ->description(fn (Notification $n) => __($l.'categories.'.$n->category)),
                TextColumn::make('channels')->label(__($l.'log.channels'))->badge()
                    // "channel:status" per delivery; the label is translated, the colour follows the status.
                    ->state(fn (Notification $n) => $n->deliveries->isEmpty() ? ['inbox:sent'] : $n->deliveries->map(fn (Delivery $d) => "{$d->channel}:{$d->status}")->all())
                    ->formatStateUsing(function (string $state) use ($l) {
                        [$channel, $status] = explode(':', $state);

                        return $channel === 'inbox' ? __($l.'log.inbox_only') : __($l.'channels.'.$channel).' · '.__($l.'statuses.'.$status);
                    })
                    ->color(fn (string $state) => match (explode(':', $state)[1]) {
                        'failed' => 'danger',
                        'held', 'queued' => 'warning',
                        'sent' => explode(':', $state)[0] === 'inbox' ? 'gray' : 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('category')->label(__($l.'log.filters.category'))
                    ->options(collect(TemplateDefinition::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __($l.'categories.'.$c)]))
                    ->query(fn ($query, array $data) => $data['value'] ? $query->where('notifications.category', $data['value']) : $query),
                SelectFilter::make('problem')->label(__($l.'log.filters.problem'))
                    ->options(collect(['failed', 'held', 'skipped'])->mapWithKeys(fn ($s) => [$s => __($l.'log.problems.'.$s)]))
                    ->query(fn ($query, array $data) => $data['value'] ? $query->whereHas('deliveries', fn ($d) => $d->where('status', $data['value'])) : $query),
                SelectFilter::make('channel')->label(__($l.'log.filters.channel'))
                    ->options(collect(TemplateDefinition::CHANNELS)->mapWithKeys(fn ($c) => [$c => __($l.'channels.'.$c)]))
                    ->query(fn ($query, array $data) => $data['value'] ? $query->whereHas('deliveries', fn ($d) => $d->where('channel', $data['value'])) : $query),
            ])
            ->recordActions([
                Action::make('details')->label(__($l.'log.details'))->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalHeading(fn (Notification $n) => $n->title)
                    ->modalContent(fn (Notification $n) => view('communication::filament.message-details', ['n' => $n, 'tz' => $tz]))
                    ->modalSubmitAction(false),
                Action::make('resend')->label(__($l.'log.resend'))->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Notification $n) => $n->deliveries->contains('status', 'failed'))
                    ->requiresConfirmation()
                    ->action(function (Notification $n) use ($l) {
                        foreach ($n->deliveries->where('status', 'failed') as $delivery) {
                            app(ResendDelivery::class)->handle($delivery);
                        }
                        Toast::make()->success()->title(__($l.'log.resent'))->send();
                    }),
            ])
            ->paginated([25, 50, 100]);
    }
}
