<?php

namespace Modules\Support\Actions;

use Illuminate\Support\Facades\Validator;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Models\SupportSettings;

/**
 * The academy's answer targets by priority, in hours from when a ticket
 * comes in: the first answer (up to a month) and the solution (no sooner than
 * the first answer, up to three months). New tickets take them; open ones keep theirs.
 */
final class SaveSupportTargets
{
    public function __construct(private readonly AuditTrail $audit) {}

    /** @param  array<string, array{respond: mixed, resolve: mixed}>  $targets */
    public function handle(array $targets): void
    {
        $rules = [];
        $names = [];
        foreach (TicketPriority::cases() as $p) {
            $rules["{$p->value}.respond"] = ['required', 'integer', 'min:1', 'max:720'];
            $rules["{$p->value}.resolve"] = ['required', 'integer', 'min:1', 'max:2160', "gte:{$p->value}.respond"];
            $names["{$p->value}.respond"] = __('support::tickets.targets.respond').' · '.$p->label();
            $names["{$p->value}.resolve"] = __('support::tickets.targets.resolve').' · '.$p->label();
        }
        Validator::make($targets, $rules, [], $names)->validate();

        $clean = [];
        foreach (TicketPriority::cases() as $p) {
            $clean[$p->value] = ['respond' => (int) $targets[$p->value]['respond'], 'resolve' => (int) $targets[$p->value]['resolve']];
        }
        $before = SupportSettings::targets();
        $settings = SupportSettings::query()->first() ?? new SupportSettings;
        $settings->fill(['targets' => $clean])->save();
        $this->audit->record('support.targets_saved', 'support_settings', null, ['from' => $before, 'to' => $clean]);
    }
}
