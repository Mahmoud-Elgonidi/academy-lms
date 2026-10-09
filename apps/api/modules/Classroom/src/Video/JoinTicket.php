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
        public bool $camera = true,   // come in with the camera on (a student may always switch it)
    ) {}

    public function withCamera(bool $camera): self
    {
        return new self($this->provider, $this->url, $this->token, $this->room, $this->seat, $this->expiresAt, $camera);
    }

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
            /** Come in with the camera on; false: the academy has this student start with it off (they may switch it on). */
            'camera' => (bool) ($this->seat->publishes() && $this->camera),
            'expires_at' => $this->expiresAt->utc()->toIso8601ZuluString(),
        ];
    }
}
