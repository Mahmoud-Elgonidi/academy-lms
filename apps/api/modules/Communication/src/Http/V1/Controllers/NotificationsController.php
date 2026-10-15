<?php

namespace Modules\Communication\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Actions\MarkNotificationsRead;
use Modules\Communication\Actions\RegisterPushDevice;
use Modules\Communication\Models\Notification;
use Modules\Communication\Models\PushDevice;
use Modules\Communication\NotificationPreferences;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** The signed-in person's inbox, notification choices and push devices (mobile app and web). */
final class NotificationsController
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $page = Notification::query()->where('user_id', $userId)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (Notification $n) => [
                'id' => $n->id,
                'category' => $n->category,
                'template' => $n->template_key,
                'title' => $n->title,
                'body' => $n->body,
                'action' => (object) $n->action,
                'urgent' => $n->urgent,
                'read' => $n->read_at !== null,
                'created_at' => $this->time->present($n->created_at),
            ])->all(),
            'meta' => [
                'unread' => Notification::query()->where('user_id', $userId)->whereNull('read_at')->count(),
                'next_cursor' => $page->nextCursor()?->encode(),
            ],
        ]);
    }

    public function read(Request $request, MarkNotificationsRead $mark): JsonResponse
    {
        $request->validate(['ids' => ['sometimes', 'array', 'max:100'], 'ids.*' => ['string']]);
        $changed = $mark->handle($request->user()->getAuthIdentifier(), $request->has('ids') ? array_values($request->input('ids')) : null);

        return response()->json(['data' => ['marked' => $changed]]);
    }

    public function preferences(Request $request, NotificationPreferences $preferences, UserPreferences $definitions): JsonResponse
    {
        return response()->json(['data' => $this->preferenceView($request->user()->getAuthIdentifier(), $preferences, $definitions)]);
    }

    public function savePreferences(Request $request, NotificationPreferences $preferences, UserPreferences $definitions): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $all = $definitions->definitions();
        foreach ((array) $request->input('preferences', []) as $key => $value) {
            $definition = str_starts_with((string) $key, 'notifications.') ? ($all[$key] ?? null) : null;
            if ($definition === null || ! $definition->offered() || ! $definition->allows($value)) {
                throw ValidationException::withMessages(["preferences.{$key}" => __('validation.in', ['attribute' => $key])]);
            }
            $preferences->set($userId, $key, $value);
        }

        return response()->json(['data' => $this->preferenceView($userId, $preferences, $definitions)]);
    }

    public function registerDevice(Request $request, RegisterPushDevice $register): JsonResponse
    {
        $device = $register->handle($request->user()->getAuthIdentifier(), (string) $request->input('platform'), (string) $request->input('token'), $request->input('app_version'));

        return response()->json(['data' => ['id' => $device->id, 'platform' => $device->platform]], 201);
    }

    public function forgetDevice(Request $request): JsonResponse
    {
        PushDevice::query()->where('user_id', $request->user()->getAuthIdentifier())->where('token', (string) $request->input('token'))->delete();

        return response()->json(null, 204);
    }

    /** @return list<array{key: string, label: string, hint: ?string, value: string, options: list<array{value: string, label: string}>}> */
    private function preferenceView(string $userId, NotificationPreferences $preferences, UserPreferences $definitions): array
    {
        $out = [];
        foreach ($preferences->all($userId) as $key => $value) {
            $definition = $definitions->definitions()[$key];
            if (! $definition->offered()) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => __($definition->label),
                'hint' => $definition->hint ? __($definition->hint) : null,
                'value' => $value,
                'options' => collect($definition->options)->map(fn ($label, $v) => ['value' => $v, 'label' => __($label)])->values()->all(),
            ];
        }

        return $out;
    }
}
