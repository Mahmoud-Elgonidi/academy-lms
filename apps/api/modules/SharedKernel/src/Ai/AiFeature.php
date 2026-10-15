<?php

namespace Modules\SharedKernel\Ai;

/**
 * The academy's AI helpers (docs/architecture/02, "المساعد الآلي"). Each is a
 * draft or an aid a person reads first, never a grade or a message that
 * leaves on its own, and each is off until the academy turns it on.
 */
enum AiFeature: string
{
    case PracticeItems = 'practice_items';
    case ReportDraft = 'report_draft';
    case RecitationCheck = 'recitation_check';
    case DigestWording = 'digest_wording';

    /** It writes words through the language model (Claude). */
    public function needsLanguage(): bool
    {
        return $this !== self::RecitationCheck;
    }

    /** It hears a recording (speech to text). */
    public function needsSpeech(): bool
    {
        return $this === self::ReportDraft || $this === self::RecitationCheck;
    }

    public function label(): string
    {
        return __('shared::ai.features.'.$this->value);
    }
}
