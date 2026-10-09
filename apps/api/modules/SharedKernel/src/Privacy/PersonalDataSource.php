<?php

namespace Modules\SharedKernel\Privacy;

/**
 * What one module holds about a person, for their rights over it
 * (docs/architecture/03, section 11): a copy of it (access, portability)
 * and erasure. Each module that keeps personal data registers one with
 * PersonalData in its provider; the Privacy module asks them all, so no
 * module depends on it. Erasure removes or blanks what the module may let
 * go of; what a law or the academy's records need (invoices, lessons, pay,
 * consents, what was said in a complaint) stays, without the person's name
 * once their account is anonymised.
 */
interface PersonalDataSource
{
    /** This part's name in a copy ("lessons", "billing"), and its heading in shared::privacy.parts. */
    public function key(): string;

    /** What the module holds about the person: plain values, never a secret, and the files they gave. */
    public function export(string $userId): PersonalDataPart;

    /** @return list<string> why the person cannot be erased yet, in words for the academy (lessons still to come, money still due) */
    public function blockers(string $userId): array;

    /** @return array<string, int> what went, by item, inside the erasure's transaction (files leave after commit) */
    public function erase(string $userId): array;
}
