<?php

use Carbon\CarbonImmutable;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\LiveKit\Jwt;
use Modules\Classroom\Video\LiveKit\LiveKitProvider;
use Modules\Classroom\Video\LiveKit\WebhookReceiver;

it('signs access tokens LiveKit accepts, with the grants of the seat', function () {
    $provider = new LiveKitProvider(['url' => 'ws://lk', 'api_url' => 'http://lk', 'key' => 'devkey', 'secret' => 'secret', 'max_participants' => 4]);
    $claims = Jwt::decode($provider->token('observer:u-1', 'Hala', CarbonImmutable::now()->addHour(), ['room' => 'r', 'roomJoin' => true, 'canPublish' => false, 'hidden' => true]), 'secret');

    expect($claims['iss'])->toBe('devkey')->and($claims['sub'])->toBe('observer:u-1')
        ->and($claims['video'])->toMatchArray(['room' => 'r', 'roomJoin' => true, 'canPublish' => false, 'hidden' => true]);
    expect(fn () => Jwt::decode($provider->token('x', 'x', CarbonImmutable::now()->addHour(), []), 'another-secret'))->toThrow(RuntimeException::class);
    expect(fn () => Jwt::decode($provider->token('x', 'x', CarbonImmutable::now()->subHours(2), []), 'secret'))->toThrow(RuntimeException::class);
});

it('accepts only webhooks signed for our key with the body\'s hash', function () {
    $body = json_encode(['event' => 'participant_joined', 'id' => 'EV_1', 'room' => ['name' => 'r']]);
    $sign = fn (string $content, string $key = 'devkey', string $secret = 'secret') => Jwt::encode(['iss' => $key, 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', $content, true))], $secret);
    $receiver = new WebhookReceiver('devkey', 'secret');

    expect($receiver->receive($body, $sign($body))['id'])->toBe('EV_1')
        ->and($receiver->receive($body, 'Bearer '.$sign($body))['event'])->toBe('participant_joined');
    expect(fn () => $receiver->receive($body.' ', $sign($body)))->toThrow(RuntimeException::class)           // body changed
        ->and(fn () => $receiver->receive($body, $sign($body, 'otherkey')))->toThrow(RuntimeException::class)  // another key
        ->and(fn () => $receiver->receive($body, $sign($body, 'devkey', 'x')))->toThrow(RuntimeException::class)
        ->and(fn () => $receiver->receive($body, null))->toThrow(RuntimeException::class);
});

it('names rooms so a webhook says which academy and lesson it is about', function () {
    $academy = '01a1108a-c7d0-7079-9435-254056c12067';
    $session = '01a1108a-e8fd-7221-a410-7ee1b532cf39';

    expect(Room::parseName(Room::nameFor($academy, $session)))->toBe([$academy, $session])
        ->and(Room::parseName('someone-elses-room'))->toBeNull();
});
