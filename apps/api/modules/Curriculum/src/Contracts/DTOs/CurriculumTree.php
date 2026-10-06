<?php

namespace Modules\Curriculum\Contracts\DTOs;

/**
 * A curriculum version as a tree, read in teaching order: levels, then their
 * units, then their lessons, by ordinal. Lessons are what reports cover and
 * progress counts.
 */
final readonly class CurriculumTree
{
    /** @var array<string, CurriculumNode> */
    public array $nodes;

    /** @param  list<CurriculumNode>  $nodes */
    public function __construct(public string $versionId, array $nodes)
    {
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$node->id] = $node;
        }
        $this->nodes = $byId;
    }

    /** @return list<CurriculumNode> */
    public function childrenOf(?string $parentId): array
    {
        $children = array_values(array_filter($this->nodes, fn (CurriculumNode $n) => $n->parentId === $parentId));
        usort($children, fn (CurriculumNode $a, CurriculumNode $b) => [$a->ordinal, $a->id] <=> [$b->ordinal, $b->id]);

        return $children;
    }

    /** @return list<CurriculumNode> every node, depth first, in teaching order */
    public function inOrder(?string $from = null): array
    {
        $out = [];
        foreach ($this->childrenOf($from) as $child) {
            $out[] = $child;
            array_push($out, ...$this->inOrder($child->id));
        }

        return $out;
    }

    /** @return list<CurriculumNode> */
    public function lessons(?string $under = null): array
    {
        return array_values(array_filter($this->inOrder($under), fn (CurriculumNode $n) => $n->kind === 'lesson'));
    }

    /** @return list<CurriculumNode> from the top level down to the node's parent */
    public function ancestorsOf(string $id): array
    {
        $chain = [];
        for ($node = $this->nodes[$id] ?? null; $node?->parentId !== null; $node = $this->nodes[$node->parentId] ?? null) {
            array_unshift($chain, $this->nodes[$node->parentId]);
        }

        return $chain;
    }

    public function has(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function totalWeight(?string $under = null): int
    {
        return array_sum(array_map(fn (CurriculumNode $n) => $n->weight, $this->lessons($under)));
    }
}
