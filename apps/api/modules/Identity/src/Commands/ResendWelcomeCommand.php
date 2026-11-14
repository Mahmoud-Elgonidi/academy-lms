<?php

namespace Modules\Identity\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Tenancy\TenantContext;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

/**
 * An academy owner's welcome sent again by the platform team from the server
 * (infra/deploy/console.sh welcome, .github/workflows/academy-welcome.yml),
 * for when the first one did not arrive: one email with a fresh link to
 * choose the password, to the owner, with copies to the addresses given
 * (whoever opens the link first sets the password; the team chose that).
 * Only for an owner or admin who has no password yet. The mail goes out
 * here, at once, so the run says whether it left or why not (the mailer in
 * use, never its words); the link is never printed. Audited with the reason
 * and the number of copies, no addresses.
 */
final class ResendWelcomeCommand extends Command
{
    protected $signature = 'platform:welcome';

    protected $description = "Send an academy owner's welcome again, with copies, from one JSON object on stdin (infra/deploy/console.sh welcome)";

    private const MAX_BYTES = 8192;

    private const MAX_COPIES = 5;

    public function handle(TenantDirectory $academies, TenantContext $tenants, IdentityApi $identity): int
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
            ])->validate();
        } catch (ValidationException $e) {
            return $this->refused($e->errors());
        }

        $mailer = (string) config('mail.default');
        $this->line("Mailer: {$mailer}; from address ".(filled(config('mail.from.address')) ? 'set' : 'NOT set'));
        if (in_array($mailer, ['log', 'array', ''], true) || blank(config('mail.from.address'))) {
            $this->error('No email can leave this server until mail is set up (RUNBOOKS.md, «Email»; console › Platform settings › Email). Nothing was sent.');

            return self::FAILURE;
        }

        $academy = $academies->bySlug(strtolower(trim((string) $d['slug'])));
        if ($academy === null) {
            return $this->refused(['slug' => ['No academy has that address.']]);
        }

        $email = mb_strtolower(trim((string) $d['email']));
        $copies = array_values(array_diff(array_map(fn ($c) => mb_strtolower(trim((string) $c)), $d['cc'] ?? []), [$email]));

        return $tenants->run($academy, function () use ($academy, $email, $copies, $d, $identity) {
            $owner = User::query()->where('email', $email)
                ->whereHas('roleRecords', fn ($q) => $q->whereIn('role', [Role::Owner->value, Role::Admin->value]))
                ->first();
            if ($owner === null) {
                return $this->refused(['email' => ["No owner or admin of {$academy->slug} has that email."]]);
            }
            if (filled($owner->password)) {
                return $this->refused(['email' => ['This account already chose its password: it signs in with it, or uses "Forgot password".']]);
            }

            $locale = in_array($academy->defaultLocale, ['ar', 'en'], true) ? $academy->defaultLocale : 'ar';
            $link = $identity->welcomeLink($owner->id);
            $name = $locale === 'ar' ? $academy->nameAr : $academy->nameEn;
            $admin = preg_replace('#^(https?://[^/?]+).*$#', '$1', $link).'/admin';
            $html = $this->body($locale, $name, (string) $owner->name, $link, $admin);
            $subject = __('identity::onboarding.welcome_mail.subject', ['academy' => $name], $locale);

            try {
                Mail::html($html, function ($m) use ($email, $copies, $subject) {
                    $m->to($email)->subject($subject);
                    if ($copies !== []) {
                        $m->cc($copies);
                    }
                });
                MailHealth::sent();
            } catch (Throwable $e) {
                MailHealth::failed($e);
                $this->error('The mailer did not take the email ('.class_basename($e).'). Check the mail settings (RUNBOOKS.md, «Email»); `php artisan platform:mail-test` tries them.');

                return self::FAILURE;
            }

            app(AuditTrail::class)->record('academy.welcome_resent', 'user', $owner->id, ['reason' => trim((string) $d['reason']), 'copies' => count($copies)]);
            $this->info("Welcome sent to {$email}".($copies === [] ? '' : ' with '.count($copies).' '.(count($copies) === 1 ? 'copy' : 'copies')).' (a fresh link, '.WelcomeController::LINK_DAYS.' days).');

            return self::SUCCESS;
        });
    }

    private function body(string $locale, string $academy, string $owner, string $link, string $admin): string
    {
        $t = fn (string $key, array $r = []) => e(__("identity::onboarding.welcome_mail.{$key}", $r, $locale));
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $url = e($link);

        return <<<HTML
<!doctype html><html lang="{$locale}" dir="{$dir}"><body style="font-family:Tahoma,Arial,sans-serif;line-height:1.7;color:#1f2937;max-width:560px;margin:auto;padding:24px">
<p>{$t('hello', ['name' => $owner])}</p>
<p>{$t('intro', ['academy' => $academy])}</p>
<p style="margin:28px 0"><a href="{$url}" style="background:#0f766e;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block">{$t('button')}</a></p>
<p>{$t('lasts', ['days' => WelcomeController::LINK_DAYS])}</p>
<p>{$t('two_factor')}</p>
<p style="color:#6b7280">{$t('address', ['url' => $admin])}</p>
</body></html>
HTML;
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
