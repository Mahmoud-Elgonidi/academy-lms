<?php

namespace Modules\Communication\Contracts;

use Modules\Communication\Contracts\DTOs\ContactMessage;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;

/**
 * What other modules may ask of Communication. A module declares its
 * templates in its provider's boot() (wording in its own lang files), then
 * sends messages by template key; Communication picks the language, the
 * channels and the time for each person.
 */
interface NotificationsApi
{
    public function define(TemplateDefinition $template): void;

    /** @return array<string, TemplateDefinition> by key */
    public function templates(): array;

    /**
     * One inbox entry per recipient, plus a delivery per outside channel the
     * academy has on and the person wants. Deliveries go after the commit.
     * Never throws: a message that cannot be sent is reported, not raised.
     *
     * @return int how many people it was sent to (repeats of a dedupe key do not count)
     */
    public function send(Message $message): int;

    /**
     * A message to someone with no account (ContactMessage): written in their
     * language and on their clock, sent after the commit by the template's
     * WhatsApp and email that the academy has on. Nothing goes to an inbox
     * and nothing is kept. Never throws.
     *
     * @return int how many channels it went out by
     */
    public function sendToContact(ContactMessage $message): int;
}
