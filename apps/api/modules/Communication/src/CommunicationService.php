<?php

namespace Modules\Communication;

use Modules\Communication\Actions\SendMessage;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Templates\TemplateRegistry;
use Throwable;

final class CommunicationService implements NotificationsApi
{
    public function __construct(private readonly TemplateRegistry $templates) {}

    public function define(TemplateDefinition $template): void
    {
        $this->templates->define($template);
    }

    public function templates(): array
    {
        return $this->templates->all();
    }

    /**
     * A message that cannot be written or queued never breaks what caused it
     * (an enrolment, a report): the error is reported and nothing is sent.
     */
    public function send(Message $message): int
    {
        try {
            return app(SendMessage::class)->handle($message);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }
}
