<?php

namespace Modules\Curriculum\Queries;

use Modules\Curriculum\Contracts\DTOs\CurriculumTree;
use Modules\Curriculum\Domain\PublishReadiness;
use Modules\Curriculum\Domain\ReadinessItem;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;

/**
 * A version's readiness to publish (Domain\PublishReadiness) read from its
 * own tables each time: the tree, each lesson's practice count and the kind
 * of its text. The builder's bar and PublishVersion both read it here.
 */
final class VersionReadiness
{
    public function of(CurriculumVersion $version): PublishReadiness
    {
        return $this->read($version)[0];
    }

    /** @return array{0: PublishReadiness, 1: CurriculumTree} */
    public function read(CurriculumVersion $version): array
    {
        $nodes = Node::query()->where('version_id', $version->id)->get();
        $tree = new CurriculumTree($version->id, $nodes->map(fn (Node $n) => $n->toData())->all());
        $lessons = $nodes->where('kind', 'lesson');

        $practice = NodeItem::query()->whereIn('node_id', $lessons->pluck('id'))
            ->selectRaw('node_id, count(*) as n')->groupBy('node_id')->pluck('n', 'node_id')->map(fn ($n) => (int) $n)->all();
        $texts = $lessons->filter(fn (Node $n) => is_array($n->text_source) && isset($n->text_source['kind']))
            ->mapWithKeys(fn (Node $n) => [$n->id => (string) $n->text_source['kind']])->all();

        return [PublishReadiness::of($tree, $practice, $texts), $tree];
    }

    /** An item in the reader's words, naming its level, unit or lesson. */
    public static function say(ReadinessItem $item, CurriculumTree $tree): string
    {
        $node = $item->nodeId !== null ? ($tree->nodes[$item->nodeId] ?? null) : null;

        return __('curriculum::curriculum.readiness.items.'.$item->key, ['title' => $node?->title() ?? '']);
    }
}
