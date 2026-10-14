<?php

namespace Modules\Announcements\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Announcements\Events\AnnouncementSent;
use Modules\Announcements\Listeners\SendAnnouncementNotifications;
use Modules\Announcements\Privacy\AnnouncementsData;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Privacy\PersonalData;

/**
 * The academy's announcements to families or teachers (docs/architecture/02,
 * section 20). Sits on top: nothing depends on it. It needs People (who is a
 * guardian, a student, of which stage, waiting for consent) and the courses
 * of a subject, which Communication may not know, so it is a module of its
 * own; it sends through Communication's contracts like every other module.
 */
final class AnnouncementsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'announcements');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'announcements');
        // What a sender sent, for their copy (Privacy); kept on erasure, without the name.
        $this->app->make(PersonalData::class)->add(AnnouncementsData::class);

        SendAnnouncementNotifications::templates($this->app->make(NotificationsApi::class));
        // In the back office it opens the announcements for who sends them; anywhere else it is read in full where it is (the bell, the inbox).
        $this->app->make(NotificationLinks::class)->register('announcement', fn (array $a, string $panel) => $panel === 'academy' && Gate::allows(Ability::SendAnnouncements->value)
            ? route('filament.academy.pages.announcements', ['announcement' => $a['id'] ?? null])
            : null);

        Event::listen(AnnouncementSent::class, [SendAnnouncementNotifications::class, 'handle']);
    }
}
