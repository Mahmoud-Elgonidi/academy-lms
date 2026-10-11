<?php

namespace Modules\Platform\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Health\AlertRecipients;
use Modules\Platform\Health\TestMail;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Health\TestAddresses;
use Throwable;

/**
 * Does the server's mail work? A short test message to the platform team
 * (or to whoever is named), sent now, not queued; the health page's mail
 * check shows how it went (RUNBOOKS.md, "Email").
 */
final class MailTestCommand extends Command
{
    protected $signature = 'platform:mail-test {to?* : addresses (default: the platform team and HEALTH_ALERT_TO)}';

    protected $description = "Send a test email through the server's mail settings";

    public function handle(): int
    {
        $to = $this->argument('to') ?: AlertRecipients::all();
        if ($to === []) {
            $this->error('Nobody to send it to: no active console account, and HEALTH_ALERT_TO is empty.');

            return self::FAILURE;
        }
        $mailer = (string) config('mail.default');
        // Demo addresses (.test) are never handed to the mail server (TestAddresses).
        $demo = array_values(array_filter($to, fn (string $address) => TestAddresses::skips($address)));
        if ($demo !== [] && count($demo) === count($to)) {
            $this->error('Not sent: '.__('platform::health.mail.test_demo', ['email' => implode(', ', $demo)]));

            return self::FAILURE;
        }
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is {$mailer}: the message goes to the log, not out. Set MAIL_MAILER=smtp and its settings in .env (RUNBOOKS.md, \"Email\").");
        }

        try {
            Mail::to($to)->send(new TestMail);
        } catch (Throwable $e) {
            MailHealth::failed($e);
            $this->error('Not sent: '.MailHealth::last()['error']);

            return self::FAILURE;
        }
        $this->info('Sent through '.$mailer.' to '.(count($to) - count($demo)).' address(es)'.($demo === [] ? '' : ' (not to the demo addresses '.implode(', ', $demo).')').'. Check the inbox (and the spam folder).');

        return self::SUCCESS;
    }
}
