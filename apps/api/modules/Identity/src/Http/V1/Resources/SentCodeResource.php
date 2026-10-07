<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Otp\SentCode;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** @property SentCode $resource */
final class SentCodeResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** Send it back with the code. */
            'challenge_id' => $this->resource->challengeId,
            /** whatsapp, sms or email. */
            'channel' => $this->resource->channel,
            /** The contact, masked: "+966•••••••01". */
            'sent_to' => $this->resource->sentTo,
            'expires_at' => app(LocalTimePresenter::class)->present($this->resource->expiresAt),
            /** Seconds before another code can be asked for. */
            'resend_after' => $this->resource->resendAfter,
            /** @var list<string> Channels a new code can go by (to offer "send by SMS instead"). */
            'channels' => $this->resource->channels,
        ];
    }

    public function withResponse(Request $request, $response): void
    {
        $response->setStatusCode(202);
    }
}
