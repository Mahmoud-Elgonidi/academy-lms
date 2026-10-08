<?php

namespace Modules\Classroom\Video\LiveKit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\JoinTicket;
use Modules\Classroom\Video\Recorder;
use Modules\Classroom\Video\RecordingOutput;
use Modules\Classroom\Video\Seat;
use Modules\Classroom\Video\VideoProvider;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use RuntimeException;
use Throwable;

/**
 * LiveKit (Cloud, or our own servers later). A ticket is an access token for
 * one room: the teacher and the student publish and subscribe; a supervisor
 * is hidden and only watches. The room allows few people (child safety) and
 * the token lasts until half an hour after the lesson. Recordings go through
 * Egress (the room laid out at low quality, or sound only) straight to the
 * academy's storage, and the room's metadata tells everyone in it.
 */
final class LiveKitProvider implements Recorder, VideoProvider
{
    /** @param  array{url: string, api_url: string, egress_url?: ?string, key: string, secret: string, max_participants: int, video?: array<string, int>, audio?: array<string, int>}  $config */
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

    public function startRecording(Room $room, RecordingOutput $output): string
    {
        $file = array_filter([
            'file_type' => $output->audioOnly() ? 'OGG' : 'MP4',
            'filepath' => $output->filepath,
            'disable_manifest' => true,
            's3' => $output->s3 === null ? null : array_filter([
                'access_key' => $output->s3['key'],
                'secret' => $output->s3['secret'],
                'region' => $output->s3['region'],
                'bucket' => $output->s3['bucket'],
                'endpoint' => $output->s3['endpoint'],
                'force_path_style' => $output->s3['path_style'],
            ], fn ($v) => $v !== null && $v !== ''),
        ], fn ($v) => $v !== null);
        $response = $this->egress('StartRoomCompositeEgress', [
            'room_name' => $room->name,
            'layout' => 'speaker',
            'audio_only' => $output->audioOnly(),
            'file_outputs' => [$file],
            'advanced' => $output->audioOnly()
                ? ['audio_codec' => 'OPUS'] + ($this->config['audio'] ?? ['audio_bitrate' => 32])
                : ($this->config['video'] ?? ['width' => 640, 'height' => 360, 'framerate' => 15, 'video_bitrate' => 500, 'audio_bitrate' => 48]),
        ]);
        $id = (string) ($response->json('egress_id') ?? $response->json('egressId') ?? '');
        if ($response->failed() || $id === '') {
            throw new RuntimeException('LiveKit Egress refused: '.$response->status().' '.($response->json('msg') ?? ''));
        }

        return $id;
    }

    public function stopRecording(string $jobId): void
    {
        $response = $this->egress('StopEgress', ['egress_id' => $jobId]);
        // Already over (the room closed, or it failed): nothing left to stop.
        if ($response->failed() && ! in_array($response->json('code'), ['not_found', 'failed_precondition'], true)) {
            throw new RuntimeException('LiveKit Egress could not stop: '.$response->status());
        }
    }

    public function announceRecording(Room $room, bool $recording): void
    {
        try {
            $this->roomService('UpdateRoomMetadata', ['room' => $room->name, 'metadata' => (string) json_encode(['recording' => $recording])],
                ['room' => $room->name, 'roomAdmin' => true]);
        } catch (Throwable $e) {
            // The pages also learn it from LiveKit itself (a room being recorded says so); this is the second voice.
            Log::warning("LiveKit UpdateRoomMetadata failed for {$room->name}: {$e->getMessage()}");
        }
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

    /** @param  array<string, mixed>  $body */
    private function egress(string $method, array $body): Response
    {
        $jwt = $this->token('academy-lms', 'Academy LMS', CarbonImmutable::now()->addMinutes(5), ['roomRecord' => true]);

        return Http::withToken($jwt)->acceptJson()->timeout(10)
            ->post(rtrim($this->config['egress_url'] ?? '' ?: $this->config['api_url'], '/')."/twirp/livekit.Egress/{$method}", $body);
    }
}
