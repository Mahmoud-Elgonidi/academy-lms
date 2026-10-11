<?php

use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\SharedKernel\Games\ItemKind;

it('reads an item the way its own words read, whatever the screen', function () {
    expect(PracticeItem::directionOf('أكمل الآية'))->toBe('rtl')
        ->and(PracticeItem::directionOf('Choose the past tense'))->toBe('ltr')
        ->and(PracticeItem::directionOf('שלום'))->toBe('rtl')
        ->and(PracticeItem::directionOf('Bonjour'))->toBe('ltr')
        // The first letter decides: numbers and marks before it do not.
        ->and(PracticeItem::directionOf('1) ما معنى "book"?'))->toBe('rtl')
        ->and(PracticeItem::directionOf('« Read » اقرأ'))->toBe('ltr')
        ->and(PracticeItem::directionOf('3 + 4 = ?'))->toBe('auto')
        ->and(PracticeItem::directionOf(''))->toBe('auto');
});

it('hands each round item its direction', function () {
    $english = new PracticeItem('1', 'n', ItemKind::Choice, 'Pick the verb', ['run', 'blue'], 0);
    $arabic = new PracticeItem('2', 'n', ItemKind::Pair, null, left: 'كتاب', right: 'book');
    $order = new PracticeItem('3', 'n', ItemKind::Order, null, parts: ['بسم', 'الله']);

    expect($english->forRound()['dir'])->toBe('ltr')
        ->and($arabic->forRound()['dir'])->toBe('rtl')
        ->and($order->forRound())->toMatchArray(['kind' => 'order', 'parts' => ['بسم', 'الله'], 'dir' => 'rtl']);
});
