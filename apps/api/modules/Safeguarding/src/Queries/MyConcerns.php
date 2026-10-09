<?php

namespace Modules\Safeguarding\Queries;

use Modules\Safeguarding\Models\Concern;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * The concerns someone raised, as they may read them: when, what kind, where
 * it stands (received, being looked into, closed) and the reply a lead wrote
 * on closing. Never the leads' notes, the outcome or who looked into it.
 */
final class MyConcerns
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /** @return list<array<string, mixed>> */
    public function of(string $reporterId, string $look = 'classic'): array
    {
        return Concern::query()->where('reporter_id', $reporterId)->orderByDesc('created_at')->limit(50)->get()
            ->map(fn (Concern $c) => [
                'id' => $c->id,
                'number' => $c->reference(),
                'category' => ['key' => $c->category->value, 'label' => $c->category->label($look)],
                'status' => ['key' => $c->status->value, 'label' => $c->status->forReporter()],
                'urgent' => $c->urgent,
                'text' => $c->body,
                'reply' => $c->status->value === 'closed' ? $c->reporter_reply : null,
                'created_at' => $this->time->present($c->created_at),
                'closed_at' => $c->closed_at ? $this->time->present($c->closed_at) : null,
            ])->all();
    }
}
