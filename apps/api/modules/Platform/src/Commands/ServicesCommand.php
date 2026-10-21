<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Health\OutsideServices;
use Modules\Platform\Settings\CheckLine;
use Modules\Platform\Settings\LastChecks;
use Modules\Platform\Settings\SettingChecks;

/**
 * The daily check of the outside services this server is set to use (the
 * scheduler, each morning): each section's "Try it" with the settings in
 * force, written to ServiceHealth (so a credit run out or a key refused shows
 * on the health page and reaches the platform team through
 * `platform:health --alert` even on a quiet day) and kept as the section's
 * last try on the settings page. Email is left out: its try sends a mail.
 */
final class ServicesCommand extends Command
{
    protected $signature = 'platform:services {--group=* : only these Platform settings sections (ai, designer, speech, whatsapp, sms, push, classroom, stripe)}';

    protected $description = 'Try each outside service set up here (AI, speech, WhatsApp, texts, push, LiveKit, Stripe) and note how it answered';

    public function handle(SettingChecks $checks): int
    {
        $services = OutsideServices::configured();
        $groups = array_values(array_intersect(SettingChecks::GROUPS, array_merge(...array_map(fn (string $s) => OutsideServices::sections($s), $services))));
        if ($this->option('group') !== []) {
            $groups = array_values(array_intersect($groups, (array) $this->option('group')));
        }
        if ($groups === []) {
            $this->line('no outside services are set up here');

            return self::SUCCESS;
        }

        $worst = 'ok';
        $rows = [];
        $tried = [];
        foreach ($groups as $group) {
            // The AI cards are tried together: once for all three.
            if (isset($tried[$group])) {
                continue;
            }
            foreach ($checks->runParts($group) as $part => $result) {
                $tried[$part] = true;
                LastChecks::put($part, $result, null);
                $worst = CheckLine::LEVELS[$result->level()] > CheckLine::LEVELS[$worst] ? $result->level() : $worst;
                foreach ($result->lines as $line) {
                    $rows[] = [$part, $line->level, $line->text().($line->todoText() !== null ? ' '.$line->todoText() : '')];
                }
            }
        }
        $this->table(['section', 'status', 'what it found'], $rows);
        $this->line('overall: '.$worst);

        return $worst === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
