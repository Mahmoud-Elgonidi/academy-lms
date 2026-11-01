<?php

namespace Modules\Site\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Site\Contracts\DTOs\JoinRequestData;
use Modules\Site\Contracts\DTOs\TrialSettings;

/** What other modules may ask of the academy's page (the CRM, which makes leads of its trial requests and books a family's own trial). */
interface SiteApi
{
    /** A request from the page in the current academy; null when there is none (swept, or another academy's). */
    public function joinRequest(string $id): ?JoinRequestData;

    /** The lead the CRM made of a request (or the open lead it went to), so the requests screen opens it. */
    public function linkLead(string $requestId, string $leadId): void;

    /** How the academy takes free trials: the page on, who books, how soon it answers, the subject for "help me choose". */
    public function trialSettings(): TrialSettings;

    /**
     * Keeps the free trial request of a family that proved its number with a
     * code (the same checks as the page's form; the same number's request of
     * the week is that one) and returns its id.
     *
     * @param  array<string, mixed>  $data  as the page's form: name, phone, email, country, timezone, subject_id, help_choose, for_child,
     *                                      child_name, child_birth_year, preferred_days, preferred_from, preferred_to, teacher_gender, message, utm_*
     *
     * @throws ValidationException
     */
    public function recordTrialRequest(array $data, string $locale): string;

    /** The trial the family booked from a request: the page's "we will answer you" message is not sent for it. */
    public function trialBooked(string $requestId, string $sessionId): void;

    /** The trial request a family's "pick a time" link names, while it waits for a time; null otherwise. */
    public function requestForPick(string $token): ?JoinRequestData;

    /**
     * The lead a request went to moved on: the request's status follows it
     * (new; contacted while sales talk to them or a trial is on; accepted
     * once enrolled; declined once lost). Staff may still answer it by hand.
     */
    public function leadMoved(string $leadId, string $stage): void;

    /** The CRM could not make a lead of a request ("invalid": its details; "error": anything else); the requests screen says so. */
    public function leadFailed(string $requestId, string $reason): void;
}
