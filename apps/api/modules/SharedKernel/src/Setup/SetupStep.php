<?php

namespace Modules\SharedKernel\Setup;

use Modules\SharedKernel\Authorization\Ability;

/**
 * One thing a new academy gets ready before its first families come, on
 * the back office's "Start here" checklist (docs/architecture/02, "قايمة
 * تجهيز الأكاديمية"). Each module registers its own with SetupChecklist in
 * its provider and answers done() from its own data each time: nothing
 * about the checklist is ever stored.
 */
interface SetupStep
{
    /** A stable name, unique across modules ("catalog.subjects"). */
    public function key(): string;

    /** The part of the checklist it shows in. */
    public function group(): SetupGroup;

    /** Its place in its group, smaller first. */
    public function order(): int;

    /** Essential before families come (counted in the progress), or optional. */
    public function required(): bool;

    /** What to do, in the reader's language (the module's own lang). */
    public function title(): string;

    /** Why it matters or where it is done, in a sentence. */
    public function hint(): string;

    /** Whether it is done, read from the module's own data each time: one cheap exists() query at most. */
    public function done(): bool;

    /** The screen to do it, by route name; null when the viewer cannot open it. */
    public function url(): ?string;

    /** Who may do it (the checklist greys it for everyone else); null for anyone who sees the checklist. */
    public function ability(): ?Ability;
}
