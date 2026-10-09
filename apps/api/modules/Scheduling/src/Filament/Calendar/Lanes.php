<?php

namespace Modules\Scheduling\Filament\Calendar;

/**
 * Lessons side by side where their times overlap, as a calendar column draws
 * them. Lessons that overlap (directly or through others) form a cluster and
 * share its width; each takes the first lane free at its start. A cluster
 * needing more lanes than fit keeps the first ones and folds the rest into
 * one "+n" in the last lane, spanning their times.
 */
final class Lanes
{
    /**
     * @template T of array{start: int, end: int}
     *
     * @param  list<T>  $items  start and end in any one unit (minutes of the day)
     * @return array{items: list<T&array{lane: int, lanes: int}>, more: list<array{start: int, end: int, count: int, lane: int, lanes: int, ids: list<mixed>}>}
     */
    public static function place(array $items, int $max = 3): array
    {
        usort($items, fn (array $a, array $b) => [$a['start'], $b['end']] <=> [$b['start'], $a['end']]);

        $placed = [];
        $more = [];
        $cluster = [];
        $clusterEnd = PHP_INT_MIN;
        $flush = function () use (&$cluster, &$placed, &$more, $max) {
            if ($cluster === []) {
                return;
            }
            $ends = [];
            foreach ($cluster as $i => $item) {
                $lane = 0;
                while (isset($ends[$lane]) && $ends[$lane] > $item['start']) {
                    $lane++;
                }
                $ends[$lane] = $item['end'];
                $cluster[$i]['lane'] = $lane;
            }
            $lanes = count($ends);
            if ($lanes <= $max) {
                foreach ($cluster as $item) {
                    $placed[] = $item + ['lanes' => $lanes];
                }
            } else {
                $hidden = [];
                foreach ($cluster as $item) {
                    if ($item['lane'] < $max - 1) {
                        $placed[] = $item + ['lanes' => $max];
                    } else {
                        $hidden[] = $item;
                    }
                }
                $more[] = [
                    'start' => min(array_column($hidden, 'start')), 'end' => max(array_column($hidden, 'end')),
                    'count' => count($hidden), 'lane' => $max - 1, 'lanes' => $max, 'ids' => array_column($hidden, 'id'),
                ];
            }
            $cluster = [];
        };

        foreach ($items as $item) {
            if ($item['start'] >= $clusterEnd) {
                $flush();
                $clusterEnd = PHP_INT_MIN;
            }
            $cluster[] = $item;
            $clusterEnd = max($clusterEnd, $item['end']);
        }
        $flush();

        return ['items' => $placed, 'more' => $more];
    }
}
