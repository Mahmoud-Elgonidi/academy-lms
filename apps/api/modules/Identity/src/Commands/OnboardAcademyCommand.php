<?php

namespace Modules\Identity\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\OnboardAcademy;
use Modules\Identity\Domain\Onboarding\CountryDefaults;
use Modules\Identity\Filament\Console\Pages\NewAcademy as NewAcademyPage;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Geo\Countries;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Throwable;

/**
 * A client academy opened by the platform team from the server (infra/deploy/
 * console.sh academy, .github/workflows/academy-new.yml), the way the
 * console's "New academy" does it, for when nobody can sign in to the
 * console: one JSON object on stdin with the academy, its owner, its plan
 * (its code), the plan's areas taken away (features_off) and the optional
 * areas the academy starts with hidden (hidden), and the reason. Everything
 * is checked before anything is made; then the academy and its owner
 * (OnboardAcademy), the plan's terms (ChangeAcademyPlan), the hidden areas
 * (SaveAcademyModules, as the platform), and last the owner's welcome by
 * email with their own link to choose a password (People's, in the staff's
 * words). The link is never printed.
 */
final class OnboardAcademyCommand extends Command
{
    protected $signature = 'platform:onboard';

    protected $description = 'Open a client academy with its owner from one JSON object on stdin (infra/deploy/console.sh academy); the owner gets the welcome by email';

    /** The largest JSON read from stdin. */
    private const MAX_BYTES = 16384;

    public function handle(OnboardAcademy $onboard, PlatformApi $platform, ConsoleApi $console, TenantContext $tenants): int
    {
        app()->setLocale('en');

        $raw = $this->stdin();
        $input = json_decode($raw, true);
        if (! is_array($input) || array_is_list($input)) {
            $this->error('One JSON object on stdin, please.');

            return self::FAILURE;
        }

        try {
            $d = $this->check($input, $console);
        } catch (ValidationException $e) {
            return $this->refused($e);
        }

        // a. The academy and its owner (no password yet), as the console's wizard makes them.
        try {
            $result = $onboard->handle(new NewAcademy(
                slug: $d['slug'],
                nameAr: $d['name_ar'],
                nameEn: $d['name_en'],
                baseCurrency: $d['currency'],
                defaultTimezone: $d['timezone'],
                defaultLocale: $d['locale'],
                country: $d['country'],
                plan: $d['plan'],
            ), $d['owner_name'], $d['owner_email'], $d['owner_phone']);
        } catch (ValidationException $e) {
            return $this->refused($e);
        }
        /** @var TenantRef $academy */
        $academy = $result['academy'];
        $host = $academy->slug.'.'.config('platform.domain');

        $step = 'the plan';
        try {
            // b. The plan's areas taken away.
            if ($d['features_off'] !== []) {
                $platform->changeAcademyPlan($academy->id, $d['plan'], $d['reason'], $d['features_off']);
            }
            // c. The optional areas it starts with hidden.
            $step = 'the hidden modules';
            $hidden = $d['hidden'] === [] ? [] : $tenants->run($academy, fn () => $platform->hideAcademyModules($d['hidden']));
            // d. Last, once all is set: the owner's welcome with their link, by email.
            $step = 'the owner\'s welcome';
            $onboard->welcome($academy, $result['owner_id']);
        } catch (Throwable $e) {
            $this->error("The academy {$host} ({$academy->id}) and its owner {$d['owner_email']} were made, but {$step} did not go through:");
            if ($e instanceof ValidationException) {
                $this->refused($e);
            } else {
                $this->error($e::class);
            }
            $this->error('Finish it in the console (Academies › '.$academy->slug.'); do not run this again for the same address.');

            return self::FAILURE;
        }

        $this->info("Academy opened: https://{$host}");
        $this->line("  id:            {$academy->id}");
        $this->line("  plan:          {$d['plan']}");
        $this->line('  features off:  '.($d['features_off'] === [] ? '-' : implode(', ', $d['features_off'])));
        $this->line('  hidden:        '.($hidden === [] ? '-' : implode(', ', $hidden)));
        $this->line("  welcome sent to {$d['owner_email']} (a link to choose a password)");

        return self::SUCCESS;
    }

    /**
     * What the JSON says, checked as the console's wizard checks it, and before anything is made.
     *
     * @param  array<mixed>  $input
     * @return array{slug: string, name_ar: string, name_en: string, country: string, currency: string, timezone: string, locale: string, owner_name: string, owner_email: string, owner_phone: ?string, plan: string, features_off: list<string>, hidden: list<string>, reason: string}
     */
    private function check(array $input, ConsoleApi $console): array
    {
        $text = fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $list = fn (string $key): mixed => $input[$key] ?? [];
        $d = [
            'slug' => strtolower($text('slug')),
            'name_ar' => $text('name_ar'),
            'name_en' => $text('name_en'),
            'country' => strtoupper($text('country')),
            'currency' => strtoupper($text('currency')),
            'timezone' => $text('timezone'),
            'locale' => $text('locale'),
            'owner_name' => $text('owner_name'),
            'owner_email' => mb_strtolower($text('owner_email')),
            'owner_phone' => $text('owner_phone'),
            'plan' => $text('plan'),
            'features_off' => $list('features_off'),
            'hidden' => $list('hidden'),
            'reason' => $text('reason'),
        ];

        $optional = array_map(fn (Feature $f) => $f->value, Feature::optional());
        Validator::make($d, [
            'slug' => ['required', 'regex:'.NewAcademyPage::SLUG, function (string $attribute, mixed $value, Closure $fail) use ($console): void {
                if (is_string($value) && $console->slugTaken($value)) {
                    $fail(__('identity::onboarding.slug.taken'));
                }
            }],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'country' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Countries::isKnown($value)) {
                    $fail('The country is not an ISO 3166 code we know.');
                }
            }],
            'currency' => ['required', Rule::in(NewAcademyPage::CURRENCIES)],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email'],
            'plan' => ['required', Rule::in(array_keys($console->offeredPlans()))],
            'features_off' => ['array', 'list'],
            'features_off.*' => ['string', Rule::in(array_column(Feature::cases(), 'value'))],
            'hidden' => ['array', 'list'],
            'hidden.*' => ['string', Rule::in($optional)],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ], [
            'owner_email.*' => __('identity::onboarding.errors.owner_email'),
            'hidden.*.in' => 'Only optional modules can be hidden: '.implode(', ', $optional).'.',
        ])->validate();

        // The owner's mobile with the country's code when it has none ("05…" in Saudi Arabia).
        $phone = null;
        if ($d['owner_phone'] !== '') {
            $phone = Phone::normalize($d['owner_phone'], CountryDefaults::dial($d['country']));
            if ($phone === null) {
                throw ValidationException::withMessages(['owner_phone' => __('identity::onboarding.errors.owner_phone')]);
            }
        }
        $d['owner_phone'] = $phone;
        $d['features_off'] = array_values(array_unique($d['features_off']));
        $d['hidden'] = array_values(array_unique($d['hidden']));

        return $d;
    }

    private function stdin(): string
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
        $stream ??= STDIN;
        $raw = stream_get_contents($stream, self::MAX_BYTES + 1);

        return $raw === false || strlen($raw) > self::MAX_BYTES ? '' : $raw;
    }

    private function refused(ValidationException $e): int
    {
        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->error("{$field}: {$message}");
            }
        }

        return self::FAILURE;
    }
}
