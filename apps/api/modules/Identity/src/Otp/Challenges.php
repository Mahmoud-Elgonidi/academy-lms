<?php

namespace Modules\Identity\Otp;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\OtpChallenge;
use Throwable;

use function Illuminate\Support\defer;

/**
 * The mechanics every code shares: limits, the code itself, sending it after
 * the response, and checking it. Opening a challenge answers the same way
 * whether a message goes out or not, and the message leaves after the
 * response, so neither the answer nor its timing says if an account exists.
 */
final class Challenges
{
    public function __construct(private readonly OtpSender $sender) {}

    /** The channel to use: the one asked for if it fits the contact, else the first that works. */
    public function channelFor(Contact $contact, ?string $asked): string
    {
        $usable = $this->usable($contact);
        if ($asked !== null && $asked !== '') {
            return in_array($asked, $usable, true)
                ? $asked
                : throw ValidationException::withMessages(['channel' => __('identity::otp.channel_unavailable')]);
        }

        return $usable[0] ?? throw ValidationException::withMessages(['contact' => __('identity::otp.no_channel')]);
    }

    /**
     * Opens a challenge for the contact. $compose builds the message once the
     * code is known; null when there is no one to send it to (it is then
     * opened all the same, and no code can ever match it).
     *
     * @param  (Closure(string): OtpMessage)|null  $compose
     */
    public function open(OtpPurpose $purpose, ?string $academyId, Contact $contact, string $channel, ?string $userId, ?string $ip, ?Closure $compose): SentCode
    {
        $config = (array) config('identity.otp');
        $hash = $contact->hash();
        $this->ensureNotTooOften($purpose, $academyId, $hash, $ip, $config);

        $digits = (int) $config['digits'];
        $code = str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
        $id = (string) Str::uuid7();
        $expiresAt = now()->toImmutable()->addMinutes((int) $config['minutes']);

        DB::transaction(function () use ($id, $purpose, $academyId, $userId, $channel, $hash, $contact, $code, $expiresAt, $ip): void {
            // Only the newest code to a contact works.
            OtpChallenge::query()
                ->where('destination_hash', $hash)->where('purpose', $purpose->value)
                ->where('academy_id', $academyId)
                ->whereNull('verified_at')->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpChallenge::query()->create([
                'id' => $id,
                'purpose' => $purpose,
                'academy_id' => $academyId,
                'user_id' => $userId,
                'channel' => $channel,
                'destination_hash' => $hash,
                'destination' => $contact->value,
                'code_hash' => self::codeHash($id, $code),
                'expires_at' => $expiresAt,
                'ip' => $ip,
            ]);
        });

        if ($compose !== null) {
            $message = $compose($code);
            defer(function () use ($message): void {
                try {
                    $this->sender->send($message);
                } catch (Throwable $e) {
                    // The person can ask again, by another channel; the code never goes to the log.
                    Log::warning("A sign-in code by {$message->channel} was not delivered: {$e->getMessage()}");
                }
            });
        }

        return new SentCode($id, $channel, $contact->masked(), $expiresAt, (int) $config['resend_seconds'], $this->usable($contact));
    }

    /**
     * The challenge, once its code is right (marked verified, and used when
     * signing in). Otherwise a ValidationException on "code"; a wrong code
     * counts, and the fifth closes the challenge.
     */
    public function verify(string $challengeId, string $code, OtpPurpose $purpose, ?string $academyId): OtpChallenge
    {
        $code = (string) preg_replace('/\D/', '', strtr($code, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        $max = (int) config('identity.otp.max_attempts');

        // Decided in the transaction, thrown after it: a wrong code must stay counted.
        [$outcome, $challenge] = ! Str::isUuid($challengeId) ? ['expired', null] : DB::transaction(function () use ($challengeId, $code, $purpose, $academyId, $max): array {
            $challenge = OtpChallenge::query()
                ->whereKey($challengeId)->where('purpose', $purpose->value)->where('academy_id', $academyId)
                ->lockForUpdate()->first();

            if ($challenge === null || $challenge->verified_at !== null || $challenge->consumed_at !== null || $challenge->expires_at->isPast()) {
                return ['expired', null];
            }
            if ($challenge->attempts >= $max) {
                return ['locked', null];
            }
            // A challenge with no account behind it was never sent: no code is right.
            $right = hash_equals($challenge->code_hash, self::codeHash($challenge->id, $code))
                && ($purpose === OtpPurpose::Discover || $challenge->user_id !== null);
            if (! $right) {
                $challenge->increment('attempts');

                return [$challenge->attempts >= $max ? 'locked' : 'wrong', $challenge];
            }

            // A sign-in or a new address is done with it at once; discovery's code goes on to the account picked.
            $challenge->forceFill(['verified_at' => now(), 'consumed_at' => $purpose === OtpPurpose::Discover ? null : now()])->save();

            return ['ok', $challenge];
        });

        return match ($outcome) {
            'ok' => $challenge,
            'wrong' => throw ValidationException::withMessages(['code' => trans_choice('identity::otp.wrong', $max - $challenge->attempts, ['left' => $max - $challenge->attempts])]),
            'locked' => throw ValidationException::withMessages(['code' => __('identity::otp.locked')]),
            default => throw ValidationException::withMessages(['code' => __('identity::otp.expired')]),
        };
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function codeHash(string $id, string $code): string
    {
        return hash_hmac('sha256', $id.':'.$code, 'otp-code|'.config('app.key'));
    }

    /** @return list<string> */
    private function usable(Contact $contact): array
    {
        return array_values(array_filter($contact->channels(), fn (string $c) => $this->sender->supports($c)));
    }

    /** @param  array<string, mixed>  $config */
    private function ensureNotTooOften(OtpPurpose $purpose, ?string $academyId, string $hash, ?string $ip, array $config): void
    {
        $hourAgo = now()->subHour();

        $last = OtpChallenge::query()
            ->where('destination_hash', $hash)->where('purpose', $purpose->value)->where('academy_id', $academyId)
            ->latest('created_at')->value('created_at');
        $wait = $last === null ? 0 : (int) ceil((int) $config['resend_seconds'] - abs(now()->diffInSeconds($last)));
        if ($wait > 0) {
            $this->tooMany($wait);
        }

        // Per contact across every academy and purpose, and per address: no one is flooded with codes.
        $perContact = OtpChallenge::query()->where('destination_hash', $hash)->where('created_at', '>', $hourAgo);
        if ($perContact->count() >= (int) $config['per_contact_per_hour']) {
            $this->tooMany($this->secondsUntilHourFrees($perContact->min('created_at')));
        }
        if ($ip !== null) {
            $perIp = OtpChallenge::query()->where('ip', $ip)->where('created_at', '>', $hourAgo);
            if ($perIp->count() >= (int) $config['per_ip_per_hour']) {
                $this->tooMany($this->secondsUntilHourFrees($perIp->min('created_at')));
            }
        }
    }

    private function secondsUntilHourFrees(mixed $oldest): int
    {
        return max(1, (int) ceil(3600 - abs(now()->diffInSeconds($oldest))));
    }

    private function tooMany(int $seconds): never
    {
        throw new ThrottleRequestsException(__('identity::otp.too_soon', ['seconds' => $seconds]), null, ['Retry-After' => $seconds]);
    }
}
