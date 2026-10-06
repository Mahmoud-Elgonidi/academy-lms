<?php

namespace Modules\Communication\Contracts;

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
}
