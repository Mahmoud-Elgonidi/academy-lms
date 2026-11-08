<?php

namespace Modules\SharedKernel\Ai;

use InvalidArgumentException;

/**
 * One AI capability of the system (docs/architecture/02, section 22): what
 * a module asks a model for, under a stable key. It says what it needs (the
 * language model, speech to text, or both, in the order it uses them), the
 * tier it is written for, the credits a use will cost (kept for the credits
 * phase; shown now), its name and description in the owning module's
 * wording, and what the demo driver answers when the prompt carries nothing.
 * Registered by its module with Capabilities::add(); the gateway routes,
 * meters and switches it with no change to Platform.
 */
final readonly class Capability
{
    public const KINDS = ['text', 'speech'];

    /**
     * @param  list<'text'|'speech'>  $kinds
     * @param  ?string  $label  a lang key of the owning module (or plain words)
     * @param  array<string, mixed>  $demo  a text answer when the prompt has none, or ['heard' => '…'] for speech
     */
    public function __construct(
        public string $key,
        public array $kinds,
        public string $tier = 'fast',
        public int $credits = 1,
        public ?string $label = null,
        public ?string $description = null,
        public array $demo = [],
        public string $module = '',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key) !== 1) {
            throw new InvalidArgumentException("An AI capability's key is a short snake_case name: {$key}");
        }
        if ($kinds === [] || array_diff($kinds, self::KINDS) !== []) {
            throw new InvalidArgumentException("An AI capability needs text, speech or both: {$key}");
        }
        if (! in_array($tier, AiPrompt::TIERS, true) || $credits < 0) {
            throw new InvalidArgumentException("An AI capability's tier is fast or best, its credits zero or more: {$key}");
        }
    }

    /**
     * One of the five helpers that came before the registry, as its enum describes it.
     *
     * @param  array<string, mixed>  $demo
     */
    public static function of(AiFeature $feature, string $tier = 'fast', int $credits = 1, string $module = '', array $demo = []): self
    {
        return new self(
            key: $feature->value,
            kinds: array_values(array_filter([$feature->needsSpeech() ? 'speech' : null, $feature->needsLanguage() ? 'text' : null])),
            tier: $tier,
            credits: $credits,
            label: 'shared::ai.features.'.$feature->value,
            description: 'shared::ai.about.'.$feature->value,
            demo: $demo,
            module: $module,
        );
    }

    public function needsLanguage(): bool
    {
        return in_array('text', $this->kinds, true);
    }

    public function needsSpeech(): bool
    {
        return in_array('speech', $this->kinds, true);
    }

    public function label(): string
    {
        return match (true) {
            $this->label === null || $this->label === '' => $this->key,
            trans()->has($this->label) => (string) __($this->label),
            default => $this->label,     // a module may name it in plain words
        };
    }

    public function description(): string
    {
        return match (true) {
            $this->description === null || $this->description === '' => '',
            trans()->has($this->description) => (string) __($this->description),
            default => str_contains($this->description, '::') ? '' : $this->description,
        };
    }

    /** The helper that came before the registry, if this is one of them. */
    public function feature(): ?AiFeature
    {
        return AiFeature::tryFrom($this->key);
    }
}
