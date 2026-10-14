<?php

namespace Modules\Announcements\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Announcements\Domain\AnnouncementRules;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Events\AnnouncementSent;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Queries\AudienceMembers;
use Modules\Announcements\Queries\TodaysAnnouncements;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's office sends an announcement (announcements.send: the owner
 * and admins; docs/architecture/02, section 20). The words are cleaned and
 * checked (AnnouncementRules), the audience is resolved to people at this
 * moment (AudienceMembers, the same reckoning as the count on the screen),
 * and the announcement is written once with how many it reached; a few a
 * day on the academy's clock, one at a time per academy. The messages go
 * out after the commit (AnnouncementSent), each person's in their language.
 * Audited with the audience and the count, never the words. Not while the
 * platform team is signed in as someone: the academy speaks for itself.
 */
final class SendAnnouncement
{
    public function __construct(
        private readonly AudienceMembers $members,
        private readonly TodaysAnnouncements $today,
        private readonly CatalogApi $catalog,
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    /**
     * @param  list<string>  $channels  besides the inbox: push, email
     *
     * @throws AuthorizationException without announcements.send
     * @throws ValidationException the words, the channels, the subject, nobody to reach, or today's announcements used up
     */
    public function handle(string $senderId, string $title, string $body, Audience $audience, array $channels = []): Announcement
    {
        if (! Gate::allows(Ability::SendAnnouncements->value)) {
            throw new AuthorizationException;
        }
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['announcement' => __('announcements::announcements.errors.impersonating')]);
        }
        $title = AnnouncementRules::title($title);
        $body = AnnouncementRules::body($body);
        $problems = AnnouncementRules::problems($title, $body, array_values($channels));
        if ($problems !== []) {
            $max = ['title' => AnnouncementRules::TITLE_MAX, 'body' => AnnouncementRules::BODY_MAX];
            foreach ($problems as $field => $problem) {
                $problems[$field] = __("announcements::announcements.errors.{$field}_{$problem}", ['max' => $max[$field] ?? 0]);
            }
            throw ValidationException::withMessages($problems);
        }
        if ($audience->kind === Audience::SUBJECT && ! array_key_exists((string) $audience->subjectId, $this->catalog->subjectNames())) {
            throw ValidationException::withMessages(['subject_id' => __('announcements::announcements.errors.subject')]);
        }
        $channels = AnnouncementRules::channels($channels);

        return DB::transaction(function () use ($senderId, $title, $body, $audience, $channels) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['announcements:'.$this->tenants->require()->id]);
            if ($this->today->left() === 0) {
                throw ValidationException::withMessages(['announcement' => __('announcements::announcements.errors.daily_cap', ['n' => AnnouncementRules::PER_DAY])]);
            }
            $recipients = $this->members->resolve($audience);
            if ($recipients === []) {
                throw ValidationException::withMessages(['audience' => __('announcements::announcements.errors.nobody')]);
            }

            $announcement = Announcement::query()->create([
                'title' => $title,
                'body' => $body,
                'audience' => $audience->kind,
                'stage' => $audience->stage,
                'subject_id' => $audience->subjectId,
                'channels' => $channels,
                'recipients_count' => count($recipients),
                'sent_by' => $senderId,
                'sent_at' => CarbonImmutable::now(),
            ]);
            $this->audit->record('announcements.sent', 'announcement', $announcement->id, ['audience' => $audience->kind, 'count' => count($recipients)]);
            DB::afterCommit(fn () => event(new AnnouncementSent($announcement->academy_id, $announcement->id, $recipients)));

            return $announcement;
        });
    }
}
