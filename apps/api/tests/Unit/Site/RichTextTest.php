<?php

use Modules\Site\Domain\Page\Problems;
use Modules\Site\Domain\Page\RichText;

/*
 * Limited rich text (Site\Domain\Page\RichText, pure): from the editor's
 * TipTap JSON only paragraphs, h3/h4, lists and quotes with bold, italic and
 * safe links are kept; never HTML; held to 60 nodes and 5,000 characters.
 */

it('keeps only the allowed nodes and marks from the editor', function () {
    $tree = RichText::fromEditor(['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Title']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold'], ['type' => 'underline']]],
            ['type' => 'text', 'text' => ' and a ', 'marks' => [['type' => 'strike']]],
            ['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]],
            ['type' => 'text', 'text' => ' bad', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
        ]],
        ['type' => 'image', 'attrs' => ['src' => 'x.png']],
        ['type' => 'table', 'content' => []],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One', 'marks' => [['type' => 'italic']]]]]]]]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Said']]]]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '   ']]],
    ]]);

    expect($tree)->toBe([
        ['t' => 'h3', 'c' => [['text' => 'Title']]],
        ['t' => 'p', 'c' => [['text' => 'Bold', 'b' => true], ['text' => ' and a '], ['text' => 'link', 'href' => 'https://example.com'], ['text' => ' bad']]],
        ['t' => 'ul', 'items' => [[['text' => 'One', 'i' => true]]]],
        ['t' => 'quote', 'c' => [['text' => 'Said']]],
    ]);
    // And back to the editor, the same tree again.
    expect(RichText::fromEditor(RichText::toEditor($tree)))->toBe($tree);
});

it('never takes HTML: a string is its text', function () {
    expect(RichText::fromEditor('<p onclick="x()">Hi <script>alert(1)</script></p>'))->toBe([['t' => 'p', 'c' => [['text' => 'Hi alert(1)']]]]);
});

it('holds the text to 60 nodes and 5,000 characters', function () {
    $problems = new Problems;
    RichText::clean(array_fill(0, 61, ['t' => 'p', 'c' => [['text' => 'x']]]), 'body.ar', $problems);
    expect($problems->all())->toHaveKey('body.ar');

    $problems = new Problems;
    RichText::clean([['t' => 'p', 'c' => [['text' => str_repeat('x', 5001)]]]], 'body.en', $problems);
    expect($problems->all()['body.en'][0])->toBe('rich_chars');

    $problems = new Problems;
    RichText::clean([['t' => 'p', 'c' => [['text' => str_repeat('x', 5000)]]]], 'body.en', $problems);
    expect($problems->none())->toBeTrue();
});
