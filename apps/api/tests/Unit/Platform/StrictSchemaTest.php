<?php

use Modules\Curriculum\Practice\PracticePrompt;
use Modules\Learning\Assist\ReportDraftPrompt;
use Modules\Platform\Ai\Support\StrictSchema;

/*
 * Strict structured outputs (OpenAI) want every property required: what our
 * schemas leave optional becomes "or null" there, and comes out again.
 */

$schema = ['type' => 'object', 'properties' => [
    'title' => ['type' => 'string'],
    'kind' => ['type' => 'string', 'enum' => ['a', 'b']],
    'homework' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'due_days' => ['type' => 'integer']], 'required' => ['title']],
    'lessons' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['key' => ['type' => 'string'], 'note' => ['type' => 'string']], 'required' => ['key']]],
], 'required' => ['title']];

it('makes every property required and the optional ones nullable, all the way down', function () use ($schema) {
    $strict = StrictSchema::of($schema);

    expect($strict['required'])->toBe(['title', 'kind', 'homework', 'lessons'])
        ->and($strict['additionalProperties'])->toBeFalse()
        ->and($strict['properties']['title'])->toBe(['type' => 'string'])
        ->and($strict['properties']['kind'])->toBe(['type' => ['string', 'null'], 'enum' => ['a', 'b', null]])
        ->and($strict['properties']['homework']['anyOf'][1])->toBe(['type' => 'null'])
        ->and($strict['properties']['homework']['anyOf'][0]['required'])->toBe(['title', 'due_days'])
        ->and($strict['properties']['homework']['anyOf'][0]['properties']['due_days']['type'])->toBe(['integer', 'null'])
        ->and($strict['properties']['lessons']['anyOf'][0]['items']['properties']['note']['type'])->toBe(['string', 'null']);
});

it('takes the nulls of what was optional out of the answer, and keeps the rest', function () use ($schema) {
    expect(StrictSchema::strip([
        'title' => 'T', 'kind' => null, 'homework' => ['title' => 'H', 'due_days' => null],
        'lessons' => [['key' => 'L1', 'note' => null], ['key' => 'L2', 'note' => 'ok']],
    ], $schema))->toBe(['title' => 'T', 'homework' => ['title' => 'H'], 'lessons' => [['key' => 'L1'], ['key' => 'L2', 'note' => 'ok']]]);
});

it('converts the helpers\' own schemas', function (string $class, string $method, array $args) {
    $schema = (new ReflectionMethod($class, $method))->invoke(null, ...$args);
    $strict = StrictSchema::of($schema);

    expect($strict['additionalProperties'])->toBeFalse()->and($strict['required'])->toBe(array_keys($schema['properties']));
})->with([
    'practice items' => [PracticePrompt::class, 'schema', []],
    'a report\'s draft' => [ReportDraftPrompt::class, 'lessonSchema', [['L1', 'L2'], []]],
    'an absence\'s draft' => [ReportDraftPrompt::class, 'absenceSchema', []],
]);
