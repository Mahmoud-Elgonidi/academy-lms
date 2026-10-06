<?php

namespace Modules\Communication\Contracts\DTOs;

use InvalidArgumentException;

/**
 * A kind of message a module sends. The default wording lives in the
 * module's lang files (title and body keys, with :placeholders); the academy
 * may reword it per language on the Templates screen.
 */
final readonly class TemplateDefinition
{
    public const CATEGORIES = ['lessons', 'learning', 'billing', 'staff'];

    public const CHANNELS = ['email', 'whatsapp', 'push'];

    /**
     * @param  string  $key  dotted, owned by one module: "learning.report_submitted"
     * @param  string  $category  the preference group the recipient controls
     * @param  string  $title  translation key
     * @param  string  $body  translation key
     * @param  string  $audience  translation key: who receives it ("the student and guardians")
     * @param  list<string>  $channels  outside channels besides the inbox
     * @param  array<string, string>  $sample  placeholder => example value, for the preview
     */
    public function __construct(
        public string $key,
        public string $category,
        public string $title,
        public string $body,
        public string $audience,
        public array $channels = ['push', 'whatsapp'],
        public array $sample = [],
    ) {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("Unknown category [{$category}] for [{$key}].");
        }
        if (array_diff($channels, self::CHANNELS) !== []) {
            throw new InvalidArgumentException("Unknown channel for [{$key}].");
        }
    }

    /** @return list<string> the placeholders, from the sample */
    public function placeholders(): array
    {
        return array_keys($this->sample);
    }
}
