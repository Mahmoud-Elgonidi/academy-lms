<?php

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\LiveBoard\BoardEvent;
use Modules\Scheduling\Filament\LiveBoardView;

it('has words for every entry of a session\'s log, in both languages', function (string $locale) {
    app()->setLocale($locale);
    $view = new LiveBoardView(CarbonImmutable::now(), 'UTC');

    foreach (SessionEventKind::cases() as $kind) {
        foreach (['decision_logged' => ['funded', 'allow_unfunded', 'other']][$kind->value] ?? [null] as $decision) {
            $text = $view->eventText(new BoardEvent($kind, CarbonImmutable::now(), array_filter([
                'from' => '2026-10-06T15:00:00Z', 'decision' => $decision ?? ['rule' => 2, 'credit' => 'refund'],
            ]), 'Hala'));
            expect($text)->not->toContain('scheduling::');
        }
    }
})->with(['ar', 'en']);
