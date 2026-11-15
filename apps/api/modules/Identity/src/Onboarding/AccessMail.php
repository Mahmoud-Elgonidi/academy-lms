<?php

namespace Modules\Identity\Onboarding;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Health\MailHealth;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use Throwable;

/**
 * The one email with a fresh link to choose a password (`identity.welcome`),
 * sent by the platform team at once, to the account and copied to the
 * addresses given: a welcome sent again (`welcome`) or after the team reset
 * the account's access (`reset`). In the academy's language; the link is
 * never printed or logged. It leaves here, so the caller knows whether the
 * mailer took it: AccessMailFailed says why not (mail not set up, or the
 * mailer's refusal by its class, never its words), and MailHealth hears it.
 */
final class AccessMail
{
    public const WELCOME = 'welcome';

    public const RESET = 'reset';

    public function __construct(private readonly TenantContext $tenants, private readonly IdentityApi $identity) {}

    /** Can an email leave this server at all? (A log or array mailer, or no sender's address: no.) */
    public static function ready(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array', ''], true) && filled(config('mail.from.address'));
    }

    /** @throws AccessMailFailed when no email can leave this server; nothing is tried */
    public static function ensureReady(): void
    {
        if (! self::ready()) {
            throw AccessMailFailed::notSetUp();
        }
    }

    /**
     * @param  list<string>  $cc
     *
     * @throws AccessMailFailed
     */
    public function send(TenantRef $academy, User $user, array $cc, string $kind): void
    {
        if (! in_array($kind, [self::WELCOME, self::RESET], true)) {
            throw new InvalidArgumentException("Unknown kind of access mail: {$kind}");
        }
        self::ensureReady();
        $email = mb_strtolower(trim((string) $user->email));
        if ($email === '') {
            throw new InvalidArgumentException('The account has no email.');
        }
        $copies = array_values(array_diff($cc, [$email]));

        $this->tenants->run($academy, function () use ($academy, $user, $email, $copies, $kind) {
            $locale = in_array($academy->defaultLocale, ['ar', 'en'], true) ? $academy->defaultLocale : 'ar';
            $link = $this->identity->welcomeLink($user->id);
            $name = $locale === 'ar' ? $academy->nameAr : $academy->nameEn;
            $admin = preg_replace('#^(https?://[^/?]+).*$#', '$1', $link).'/admin';
            $html = $this->body($kind, $locale, $name, (string) $user->name, $link, $admin);
            $subject = __("identity::onboarding.{$kind}_mail.subject", ['academy' => $name], $locale);

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

                throw AccessMailFailed::refused(class_basename($e));
            }
        });
    }

    private function body(string $kind, string $locale, string $academy, string $name, string $link, string $admin): string
    {
        $t = fn (string $key, array $r = []) => e(__("identity::onboarding.{$kind}_mail.{$key}", $r, $locale));
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $url = e($link);

        return <<<HTML
<!doctype html><html lang="{$locale}" dir="{$dir}"><body style="font-family:Tahoma,Arial,sans-serif;line-height:1.7;color:#1f2937;max-width:560px;margin:auto;padding:24px">
<p>{$t('hello', ['name' => $name])}</p>
<p>{$t('intro', ['academy' => $academy])}</p>
<p style="margin:28px 0"><a href="{$url}" style="background:#0f766e;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block">{$t('button')}</a></p>
<p>{$t('lasts', ['days' => WelcomeController::LINK_DAYS])}</p>
<p>{$t('two_factor')}</p>
<p style="color:#6b7280">{$t('address', ['url' => $admin])}</p>
</body></html>
HTML;
    }
}
