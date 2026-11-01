<?php

namespace Modules\Communication\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\ContactMessage;
use Modules\Communication\Jobs\SendContactDelivery;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Templates\Renderer;
use Modules\Communication\Templates\TemplateRegistry;

/**
 * A message to someone with no account yet: the template written in their
 * language and on their clock (the academy's way of reading one), then one
 * delivery per channel among the template's WhatsApp and email that the
 * academy has on and they gave an address for, queued after the commit.
 * No inbox, no quiet hours (they asked a moment ago), nothing kept.
 */
final class SendToContact
{
    public const CHANNELS = ['whatsapp', 'email'];

    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly Renderer $renderer,
    ) {}

    public function handle(ContactMessage $message): int
    {
        $template = $this->templates->get($message->template);
        $settings = CommunicationSettings::current();
        $locale = $message->locale === 'en' ? 'en' : 'ar';
        $text = $this->renderer->render($template, $locale, $message->data, $message->timezone);

        $sent = 0;
        foreach (array_intersect($template->channels, self::CHANNELS) as $channel) {
            $address = $channel === 'email' ? $message->email : $message->phone;
            if ($address === null || $address === '' || ! $settings->allows($channel)) {
                continue;
            }
            $job = new SendContactDelivery($channel, $address, $message->name, $locale, $text->title, $text->body);
            DB::afterCommit(fn () => dispatch($job));
            $sent++;
        }

        return $sent;
    }
}
