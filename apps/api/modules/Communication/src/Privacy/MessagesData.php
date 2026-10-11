<?php

namespace Modules\Communication\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The messages a person received (their inbox, with where each went) and
 * the phones registered for their pushes. Erasing removes them all, with
 * their deliveries in the message log, and takes their name out of the
 * messages others received about them (a ticket, a request to erase, a
 * hidden number): those stay in the others' inboxes, without the name.
 */
final class MessagesData implements PersonalDataSource
{
    public function key(): string
    {
        return 'messages';
    }

    public function export(string $userId): PersonalDataPart
    {
        $deliveries = DB::table('notification_deliveries as d')->join('notifications as n', fn ($j) => $j->on('n.academy_id', '=', 'd.academy_id')->on('n.id', '=', 'd.notification_id'))
            ->where('n.user_id', $userId)->get(['d.notification_id', 'd.channel', 'd.status', 'd.address', 'd.sent_at'])->groupBy('notification_id');

        return new PersonalDataPart([
            'inbox' => DB::table('notifications')->where('user_id', $userId)->orderBy('created_at')
                ->get(['id', 'category', 'title', 'body', 'created_at', 'read_at'])
                ->map(fn (object $n) => ['title' => $n->title, 'body' => $n->body, 'category' => $n->category, 'sent_at' => $n->created_at, 'read_at' => $n->read_at,
                    'went_to' => ($deliveries[$n->id] ?? collect())->map(fn (object $d) => ['channel' => $d->channel, 'address' => $d->address, 'status' => $d->status, 'sent_at' => $d->sent_at])->values()->all()])
                ->all(),
            'push_devices' => DB::table('push_devices')->where('user_id', $userId)->orderBy('created_at')
                ->get(['platform', 'app_version', 'created_at', 'last_seen_at'])->map(fn (object $d) => (array) $d)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return [
            'messages' => DB::table('notifications')->where('user_id', $userId)->delete(),
            'push_devices' => DB::table('push_devices')->where('user_id', $userId)->delete(),
            'named_in_others' => $this->unname($userId),
        ];
    }

    /** The person's name, out of the others' messages (the account is erased after this part, so it still has it). */
    private function unname(string $userId): int
    {
        $name = trim((string) DB::table('users')->where('id', $userId)->value('name'));
        if (mb_strlen($name) < 2) {
            return 0;
        }
        // The whole name only, never inside a longer word.
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u';
        $count = 0;
        DB::table('notifications as n')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'n.academy_id')->on('u.id', '=', 'n.user_id'))
            ->where('n.user_id', '!=', $userId)
            ->where(fn ($q) => $q->whereRaw('strpos(n.title, ?) > 0', [$name])->orWhereRaw('strpos(n.body, ?) > 0', [$name]))
            ->orderBy('n.id')->get(['n.id', 'n.title', 'n.body', 'u.locale'])
            ->each(function (object $n) use ($pattern, &$count) {
                $erased = trans('communication::communication.erased_person', [], $n->locale ?: null);
                $count += DB::table('notifications')->where('id', $n->id)->update([
                    'title' => (string) preg_replace($pattern, $erased, (string) $n->title),
                    'body' => (string) preg_replace($pattern, $erased, (string) $n->body),
                ]);
            });

        return $count;
    }
}
