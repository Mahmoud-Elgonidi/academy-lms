<?php

namespace Modules\Classroom\Video;

use Carbon\CarbonImmutable;

/** What a browser or the app needs to enter: a LiveKit server and token, or an outside meeting link. */
final readonly class JoinTicket
{
    public function __construct(
        public string $provider,
        public string $url,
        public ?string $token,
        public ?string $room,
        public Seat $seat,
        public CarbonImmutable $expiresAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'url' => $this->url,
            'token' => $this->token,
            'room' => $this->room,
            'identity' => $this->seat->identity,
            'name' => $this->seat->name,
            'role' => $this->seat->role,
            'can_publish' => $this->seat->publishes(),
            'expires_at' => $this->expiresAt->utc()->toIso8601ZuluString(),
        ];
    }
}
