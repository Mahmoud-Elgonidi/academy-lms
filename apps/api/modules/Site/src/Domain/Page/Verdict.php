<?php

namespace Modules\Site\Domain\Page;

/**
 * Whether a page may be published (PublishRules): blockers stop it,
 * warnings are said in the confirmation. Each item names its code (worded in
 * site::builder.rules.{code}), the block it is about (id and type, or none
 * for the whole page), the field inside it, and the code's values.
 */
final readonly class Verdict
{
    /**
     * @param  list<array{code: string, block: ?string, type: ?string, field: ?string, params: array<string, scalar>}>  $blockers
     * @param  list<array{code: string, block: ?string, type: ?string, field: ?string, params: array<string, scalar>}>  $warnings
     */
    public function __construct(public array $blockers, public array $warnings) {}

    public function ok(): bool
    {
        return $this->blockers === [];
    }

    /** @return list<string> the codes, blockers first (tests and the audit) */
    public function codes(): array
    {
        $out = [];
        foreach ([...$this->blockers, ...$this->warnings] as $item) {
            $out[] = $item['code'];
        }

        return $out;
    }
}
