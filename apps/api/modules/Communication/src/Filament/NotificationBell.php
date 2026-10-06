<?php

namespace Modules\Communication\Filament;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Communication\Actions\MarkNotificationsRead;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Models\Notification;
use Modules\Identity\Contracts\PersonalChannel;

/**
 * The bell in both panels' top bar: unread count, the latest messages, and
 * where each one opens. It refreshes when the inbox changes (a signal on the
 * person's own channel), not on a timer.
 */
final class NotificationBell extends Component
{
    public string $panel = 'academy';

    public function mount(): void
    {
        $this->panel = Filament::getCurrentPanel()?->getId() ?? 'academy';
    }

    #[Computed]
    public function unread(): int
    {
        return Notification::query()->where('user_id', auth()->id())->whereNull('read_at')->count();
    }

    /** @return Collection<int, Notification> */
    #[Computed]
    public function latest(): Collection
    {
        return Notification::query()->where('user_id', auth()->id())->orderByDesc('created_at')->limit(10)->get();
    }

    public function open(string $id): void
    {
        $notification = Notification::query()->where('user_id', auth()->id())->find($id);
        if ($notification === null) {
            return;
        }
        app(MarkNotificationsRead::class)->handle((string) auth()->id(), [$notification->id]);
        unset($this->unread, $this->latest);

        $url = app(NotificationLinks::class)->resolve($notification->action, $this->panel);
        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        app(MarkNotificationsRead::class)->handle((string) auth()->id());
        unset($this->unread, $this->latest);
    }

    public function render(): View
    {
        return view('communication::filament.bell', [
            'channel' => PersonalChannel::name((string) auth()->id()),
            'renderedAt' => now()->getTimestampMs(),
        ]);
    }
}
