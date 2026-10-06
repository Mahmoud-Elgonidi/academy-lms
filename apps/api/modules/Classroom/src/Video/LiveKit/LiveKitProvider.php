<?php

namespace Modules\Classroom\Video\LiveKit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\JoinTicket;
use Modules\Classroom\Video\Seat;
use Modules\Classroom\Video\VideoProvider;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use RuntimeException;
use Throwable;

/**
 * LiveKit (Cloud, or our own servers later). A ticket is an access token for
 * one room: the teacher and the student publish and subscribe; a supervisor
 * is hidden and only watches. The room allows few people (child safety) and
 * the token lasts until half an hour after the lesson.
 */
final class LiveKitProvider implements VideoProvider
{
    /** @param  array{url: string, api_url: string, key: string, secret: string, max_participants: int}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'livekit';
    }

    public function ticket(Room $room, Seat $seat, LessonCard $lesson): JoinTicket
    {
        $this->createRoom($room);
        $expires = $lesson->endsAt->addMinutes(30)->max(CarbonImmutable::now()->addMinutes(5));

        $token = $this->token($seat->identity, $seat->name, $expires, [
            'room' => $room->name,
            'roomJoin' => true,
            'canSubscribe' => true,
            'canPublish' => $seat->publishes(),
            'canPublishData' => $seat->publishes(),
            'hidden' => $seat->hidden(),
        ], ['role' => $seat->role, 'user_id' => $seat->userId]);

        return new JoinTicket('livekit', $this->config['url'], $token, $room->name, $seat, $expires);
    }

    public function participants(Room $room): ?array
    {
        $response = $this->roomService('ListParticipants', ['room' => $room->name], ['room' => $room->name, 'roomAdmin' => true]);
        if ($response->status() === 404 || $response->json('code') === 'not_found') {
            return [];      // no room yet, or it finished
        }
        if ($response->failed()) {
            throw new RuntimeException('LiveKit room API refused: '.$response->status());
        }

        return array_values(array_map(fn (array $p) => (string) $p['identity'], $response->json('participants') ?? []));
    }

    /**
     * @param  array<string, mixed>  $video
     * @param  array<string, string>  $metadata
     */
    public function token(string $identity, string $name, CarbonImmutable $expires, array $video, array $metadata = []): string
    {
        return Jwt::encode([
            'iss' => $this->config['key'],
            'sub' => $identity,
            'name' => $name,
            'nbf' => CarbonImmutable::now()->getTimestamp() - 10,
            'exp' => $expires->getTimestamp(),
            'video' => $video,
            'metadata' => (string) json_encode($metadata),
        ], $this->config['secret']);
    }

    private function createRoom(Room $room): void
    {
        try {
            $this->roomService('CreateRoom', [
                'name' => $room->name,
                'empty_timeout' => 10 * 60,
                'max_participants' => $this->config['max_participants'],
            ], ['roomCreate' => true]);
        } catch (Throwable $e) {
            // The room is also made on the first join; a failure here only loses the limits.
            Log::warning("LiveKit CreateRoom failed for {$room->name}: {$e->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $grant
     */
    private function roomService(string $method, array $body, array $grant): Response
    {
        $jwt = $this->token('academy-lms', 'Academy LMS', CarbonImmutable::now()->addMinutes(5), $grant);

        return Http::withToken($jwt)->acceptJson()->timeout(5)
            ->post(rtrim($this->config['api_url'], '/')."/twirp/livekit.RoomService/{$method}", $body);
    }
}
