<?php

use Modules\Learning\Domain\ReportDraftMerge;
use Modules\Learning\Templates\TemplateField;

/*
 * What a voice note's draft may put in the teacher's open report form: only
 * what is empty, a lesson's outcome only while it is still the plan's guess,
 * every value within the form's limits, and nothing the form would refuse.
 */

function draftMerge(array $current, array $draft, array $fields = [], array $planned = ['n1' => 'introduced']): array
{
    return ReportDraftMerge::merge('lesson', $current, $draft, ['L1' => 'n1', 'L2' => 'n2', 'L3' => 'n3'], ['n1' => 'A › One', 'n2' => 'A › Two', 'n3' => 'A › Three'], $planned, $fields);
}

it('keeps what the teacher set and fills the rest', function () {
    $out = draftMerge(
        ['engagement' => 3, 'notes_for_student' => '  ', 'items' => [['node_id' => 'n1', 'outcome' => 'introduced'], ['node_id' => 'n2', 'outcome' => 'practiced', 'note' => 'mine']]],
        ['engagement' => 5, 'overall' => 9, 'notes_for_student' => str_repeat('ب', 3100), 'lessons' => [
            ['key' => 'L1', 'outcome' => 'mastered', 'score' => 4],
            ['key' => 'L2', 'outcome' => 'needs_review', 'note' => 'theirs'],
            ['key' => 'L3', 'outcome' => 'introduced'],
            ['key' => 'L9', 'outcome' => 'mastered'],
            ['key' => 'L1', 'outcome' => 'sung'],
        ]],
    );

    expect($out['report']['engagement'])->toBe(3)
        ->and($out['report']['overall'])->toBeNull()                                  // 9 is not 1–5
        ->and(mb_strlen($out['report']['notes_for_student']))->toBe(ReportDraftMerge::NOTES_MAX)
        ->and($out['report']['items'])->toBe([
            ['node_id' => 'n1', 'label' => 'A › One', 'outcome' => 'mastered', 'score' => 4, 'note' => null],   // the plan's guess gave way
            ['node_id' => 'n2', 'label' => 'A › Two', 'outcome' => 'practiced', 'score' => null, 'note' => 'mine'], // the teacher's own stays
            ['node_id' => 'n3', 'label' => 'A › Three', 'outcome' => 'introduced', 'score' => null, 'note' => null],
        ])
        ->and($out['filled'])->toBe(['notes_for_student', 'items.n1.outcome', 'items.n1.score', 'items.n3'])
        ->and($out['left'])->toBe(['overall']);
});

it('keeps an outcome the teacher changed from the plan\'s guess', function () {
    $out = draftMerge(['items' => [['node_id' => 'n1', 'outcome' => 'practiced']]], ['lessons' => [['key' => 'L1', 'outcome' => 'mastered']]]);
    expect($out['report']['items'][0]['outcome'])->toBe('practiced')->and($out['filled'])->toBe([]);
});

it('answers only questions the form takes, and says which required ones are left', function () {
    $fields = [
        new TemplateField('f1', 'صفحات', 'Pages', 'number', required: true, min: 0, max: 20),
        new TemplateField('f2', 'ركّز؟', 'Focused?', 'yes_no'),
        new TemplateField('f3', 'التجويد', 'Tajweed', 'rating', required: true),
    ];
    $out = draftMerge(['answers' => ['f2' => false]], ['answers' => [['key' => 'f1', 'value' => '25'], ['key' => 'f2', 'value' => 'true'], ['key' => 'f3', 'value' => '4'], ['key' => 'zz', 'value' => 'x']]], $fields);

    expect($out['report']['answers'])->toBe(['f2' => false, 'f3' => 4])
        ->and($out['filled'])->toBe(['answers.f3'])
        ->and($out['left'])->toBe(['engagement', 'overall', 'items', 'answers.f1']);
});

it('suggests homework only where there is none, within its limits', function () {
    $said = ['homework' => ['title' => 'Recite', 'accepts' => 'practice', 'due_days' => 0]];
    expect(draftMerge([], $said)['report']['homework'])->toBe(['title' => 'Recite', 'instructions' => null, 'accepts' => 'any', 'due_days' => 1, 'practice' => false])
        ->and(draftMerge(['homework' => ['title' => 'Mine', 'accepts' => 'text']], $said)['report']['homework'])->toBe(['title' => 'Mine', 'accepts' => 'text']);
});

it('marks as unsure only fields it filled', function () {
    $out = draftMerge(['overall' => 2], ['overall' => 5, 'engagement' => 4, 'lessons' => [['key' => 'L2', 'outcome' => 'practiced']], 'unsure' => ['overall', 'engagement', 'L2']], planned: []);
    expect($out['unsure'])->toBe(['engagement', 'items.n2']);
});

it('drafts an absence report\'s two notes alone', function () {
    $out = ReportDraftMerge::merge('absence', ['notes_internal' => 'mine'], ['absence_note' => 'Did not come', 'notes_internal' => 'theirs', 'engagement' => 5], [], [], [], []);
    expect($out)->toBe(['report' => ['notes_internal' => 'mine', 'absence_note' => 'Did not come'], 'filled' => ['absence_note'], 'unsure' => [], 'left' => []]);
});
