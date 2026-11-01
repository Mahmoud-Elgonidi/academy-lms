<?php

namespace Modules\SharedKernel\Setup;

/**
 * A step that belongs on the checklist only while something holds, such as
 * an area the academy's plan may leave out: asked each time the steps are
 * read, and left off the checklist (and its count) while it does not apply.
 */
interface ConditionalSetupStep extends SetupStep
{
    /** Whether the step is on this academy's checklist now: cheap, read each time. */
    public function applies(): bool;
}
