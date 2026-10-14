<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Channels\ChannelManager;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Domain\ChannelChoice;
use Modules\Communication\Jobs\SendDelivery;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Communication\NotificationPreferences;
use Modules\Communication\Queries\Recipients;
use Modules\Communication\Templates\Renderer;
use Modules\Communication\Templates\TemplateRegistry;

/**
 * For each recipient: the message written in their language and time zone,
 * an inbox entry, and a delivery per outside channel that the academy has on
 * and the person wants. WhatsApp and push wait out the person's quiet hours
 * unless the message is urgent; email and the inbox never wait. A message
 * may keep to some of its template's channels (an announcement). Deliveries
 * are queued after the commit. A dedupe key sends at most once per person.
 * A template with :link gets each person's own link (NotificationLinks).
 */
final class SendMessage
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly Renderer $renderer,
        private readonly Recipients $recipients,
        private readonly NotificationPreferences $preferences,
        private readonly ChannelManager $channels,
        private readonly NotificationLinks $links,
    ) {}

    public function handle(Message $message): int
    {
        $template = $this->templates->get($message->template);
        $people = $this->recipients->find($message->recipients);
        if ($people === []) {
            return 0;
        }
        $settings = CommunicationSettings::current();
        $now = CarbonImmutable::now();
        // A message may narrow the template's channels (never add to them).
        $offered = $message->channels === null ? $template->channels : array_values(array_intersect($template->channels, $message->channels));

        return DB::transaction(function () use ($message, $template, $people, $settings, $now, $offered) {
            $sent = 0;
            foreach ($people as $person) {
                if ($message->dedupeKey !== null && Notification::query()->where('user_id', $person->id)->where('dedupe_key', $message->dedupeKey)->exists()) {
                    continue;
                }
                $data = $message->data;
                if (in_array('link', $template->placeholders(), true) && ! array_key_exists('link', $data)) {
                    $data['link'] = $this->links->personal($message->action, $person->id) ?? '';
                }
                $text = $this->renderer->render($template, $person->locale, $data, $person->timezone);

                try {
                    $notification = DB::transaction(fn () => Notification::query()->create([
                        'user_id' => $person->id,
                        'template_key' => $template->key,
                        'category' => $template->category,
                        'title' => $text->title,
                        'body' => $text->body,
                        'action' => $message->action,
                        'dedupe_key' => $message->dedupeKey,
                        'urgent' => $message->urgent,
                    ]));
                } catch (UniqueConstraintViolationException) {
                    continue;   // sent by a run alongside this one
                }
                $sent++;

                $wanted = ChannelChoice::channels($this->preferences->choice($person->id, $template->category), $offered);
                foreach ($wanted as $channel) {
                    if (! $settings->allows($channel)) {
                        continue;
                    }
                    $address = $this->channels->get($channel)->address($person);
                    $hold = $address !== null && ! $message->urgent && $channel !== 'email'
                        ? $this->preferences->quietHours($person->id)->releaseAt($now, $person->timezone)
                        : null;

                    $delivery = $notification->deliveries()->create([
                        'channel' => $channel,
                        'address' => $address,
                        'status' => match (true) {
                            $address === null => 'skipped',
                            $hold !== null => 'held',
                            default => 'queued',
                        },
                        'error' => $address === null ? 'no_address' : null,
                        'not_before' => $hold,
                    ]);
                    if ($delivery->status === 'queued') {
                        DB::afterCommit(fn () => SendDelivery::dispatch($delivery->id));
                    }
                }
            }

            return $sent;
        });
    }
}
