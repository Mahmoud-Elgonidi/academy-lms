<?php

namespace Modules\Identity\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\Support\ResetAccess;
use Modules\Identity\Enums\Role;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Models\User;
use Modules\Identity\Onboarding\AccessMail;
use Modules\Identity\Onboarding\AccessMailFailed;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * An academy owner's welcome sent again by the platform team from the server
 * (infra/deploy/console.sh welcome, .github/workflows/academy-welcome.yml),
 * for when the first one did not arrive: one email with a fresh link to
 * choose the password, to the owner, with copies to the addresses given
 * (whoever opens the link first sets the password; the team chose that).
 * Only for an owner or admin who has no password yet. With `"reset": true`
 * the account's access is reset first (ResetAccess: any back-office account,
 * its password and two-step sign-in cleared, its sessions ended, a reason of
 * StartImpersonation::REASON_MIN), for someone who forgot their password.
 * The mail goes out here, at once (AccessMail), so the run says whether it
 * left or why not (the mailer in use, never its words); the link is never
 * printed. Audited with the reason and the number of copies, no addresses.
 */
final class ResendWelcomeCommand extends Command
{
    protected $signature = 'platform:welcome';

    protected $description = "Send an academy owner's welcome again (or reset an account's access), with copies, from one JSON object on stdin (infra/deploy/console.sh welcome)";

    private const MAX_BYTES = 8192;

    private const MAX_COPIES = 5;

    public function handle(TenantDirectory $academies, TenantContext $tenants, AccessMail $mail, ResetAccess $reset): int
    {
        app()->setLocale('en');
        $input = json_decode($this->stdin(), true);
        if (! is_array($input) || array_is_list($input)) {
            $this->error('One JSON object on stdin, please.');

            return self::FAILURE;
        }

        try {
            $d = Validator::make($input, [
                'slug' => ['required', 'string', 'max:63'],
                'email' => ['required', 'email'],
                'cc' => ['array', 'max:'.self::MAX_COPIES],
                'cc.*' => ['email', 'distinct'],
                'reason' => ['required', 'string', 'min:5', 'max:300'],
                'reset' => ['boolean'],
            ])->validate();
        } catch (ValidationException $e) {
            return $this->refused($e->errors());
        }

        $this->line('Mailer: '.config('mail.default').'; from address '.(filled(config('mail.from.address')) ? 'set' : 'NOT set'));
        if (! AccessMail::ready()) {
            $this->error(AccessMailFailed::notSetUp()->getMessage());

            return self::FAILURE;
        }

        $academy = $academies->bySlug(strtolower(trim((string) $d['slug'])));
        if ($academy === null) {
            return $this->refused(['slug' => ['No academy has that address.']]);
        }

        $email = mb_strtolower(trim((string) $d['email']));
        $copies = array_values(array_diff(array_map(fn ($c) => mb_strtolower(trim((string) $c)), $d['cc'] ?? []), [$email]));
        $said = $copies === [] ? '' : ' with '.count($copies).' '.(count($copies) === 1 ? 'copy' : 'copies');

        if ((bool) ($d['reset'] ?? false)) {
            $user = $tenants->run($academy, fn () => User::query()->where('email', $email)->first());
            if ($user === null) {
                return $this->refused(['email' => ["Nobody in {$academy->slug} has that email."]]);
            }
            try {
                $done = $reset->handle($academy, $user->id, null, (string) $d['reason'], $copies);
            } catch (ValidationException $e) {
                return $this->refused($e->errors());
            }
            $this->info('Access reset (password'.($done['had_two_factor'] ? ' and two-step sign-in' : '').' cleared, sessions ended); a new link sent to '.$email.$said.' ('.WelcomeController::LINK_DAYS.' days).');

            return self::SUCCESS;
        }

        return $tenants->run($academy, function () use ($academy, $email, $copies, $d, $mail, $said) {
            $owner = User::query()->where('email', $email)
                ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', [Role::Owner->value, Role::Admin->value]))
                ->first();
            if ($owner === null) {
                return $this->refused(['email' => ["No owner or admin of {$academy->slug} has that email."]]);
            }
            if (filled($owner->password)) {
                return $this->refused(['email' => ['This account already chose its password: it signs in with it, or uses "Forgot password". To reset it, run again with "reset": true.']]);
            }

            try {
                $mail->send($academy, $owner, $copies, AccessMail::WELCOME);
            } catch (AccessMailFailed $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            app(AuditTrail::class)->record('academy.welcome_resent', 'user', $owner->id, ['reason' => trim((string) $d['reason']), 'copies' => count($copies)]);
            $this->info("Welcome sent to {$email}{$said} (a fresh link, ".WelcomeController::LINK_DAYS.' days).');

            return self::SUCCESS;
        });
    }

    /** @param  array<string, list<string>>  $errors */
    private function refused(array $errors): int
    {
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->error("{$field}: {$message}");
            }
        }

        return self::FAILURE;
    }

    private function stdin(): string
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
        $stream ??= STDIN;
        $raw = stream_get_contents($stream, self::MAX_BYTES + 1);

        return $raw === false || strlen($raw) > self::MAX_BYTES ? '' : $raw;
    }
}
