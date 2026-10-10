<?php

namespace Modules\Messaging\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\Listeners\SendMessagingNotifications;
use Modules\Messaging\Live\ConversationsChanged;
use Modules\Messaging\Live\MyMessagesChanged;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Privacy\MessagingData;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Messages between a teacher and a student on the platform, with the
 * student's guardians in every conversation and contacts hidden
 * (docs/architecture/02, section 17). Sits on top: nothing depends on it;
 * it reaches lessons, courses and people through their contracts and tables
 * it only reads.
 */
final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MessagingRules::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'messaging');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'messaging');
        // A person's conversations, for their copy (Privacy); what was said to a child is kept.
        $this->app->make(PersonalData::class)->add(MessagingData::class);

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        SendMessagingNotifications::templates($this->app->make(NotificationsApi::class));
        $links = $this->app->make(NotificationLinks::class);
        $links->register('conversation', fn (array $a, string $panel) => match ($panel) {
            'teach' => route('filament.teach.pages.messages', ['conversation' => $a['id'] ?? null]),
            'family' => route('filament.family.pages.messages', ['conversation' => $a['id'] ?? null]),
            default => null,
        });
        $links->register('messaging_conversation', fn (array $a, string $panel) => $panel === 'academy' ? route('filament.academy.pages.conversations', ['conversation' => $a['id'] ?? null]) : null);

        Event::listen(MessageSent::class, [SendMessagingNotifications::class, 'sent']);

        // Live screens: each person's conversations on their own channel, oversight on the academy's.
        $live = $this->app->make(LiveSignals::class);
        $live->on('messages', fn (string $userId) => new MyMessagesChanged($userId));
        $live->on('conversations', fn (string $academyId) => new ConversationsChanged($academyId));
        Broadcast::channel(ConversationsChanged::CHANNEL, fn (Authenticatable $user, string $academyId) => $academyId === $this->app->make(TenantContext::class)->id()
            && Gate::forUser($user)->allows(Ability::OverseeConversations->value));
    }
}
