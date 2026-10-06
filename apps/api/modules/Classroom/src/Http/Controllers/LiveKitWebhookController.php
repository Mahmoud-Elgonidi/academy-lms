<?php

namespace Modules\Classroom\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Classroom\Actions\HandleLiveKitWebhook;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\LiveKit\WebhookReceiver;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantResolver;
use RuntimeException;

/**
 * LiveKit posts room and participant events here. The signature is checked
 * first; the academy comes from the room name ("{academy}_{session}"), and
 * the event is handled as that academy.
 */
final class LiveKitWebhookController
{
    public function __invoke(Request $request, TenantResolver $tenants, TenantContext $context, HandleLiveKitWebhook $handle): Response
    {
        try {
            $event = (new WebhookReceiver(config('classroom.livekit.key'), config('classroom.livekit.secret')))
                ->receive($request->getContent(), $request->header('Authorization'));
        } catch (RuntimeException) {
            return response('', 401);
        }

        $parsed = Room::parseName((string) ($event['room']['name'] ?? ''));
        $tenant = $parsed === null ? null : $tenants->byId($parsed[0]);
        if ($tenant === null) {
            return response('', 204);   // not one of ours: nothing to do, and no reason to retry
        }

        $context->run($tenant, function () use ($parsed, $event, $handle) {
            $room = Room::query()->where('session_id', $parsed[1])->first();
            if ($room !== null) {
                $handle->handle($event, $room);
            }
        });

        return response('', 204);
    }
}
