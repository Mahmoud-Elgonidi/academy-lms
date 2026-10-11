<?php

namespace Modules\Platform\Games;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * The world the platform starts with: five islands and their creatures,
 * and the games written with the platform (resources/games/starters), each
 * live at version 1. Run by the catalog's migration and by tests; adds
 * only what is missing, so running it again changes nothing.
 */
final class StarterCatalog
{
    /** key => [name_ar, name_en, story_ar, story_en, emoji, colors, music, creatures [key, ar, en, emoji, rarity]] */
    public const ISLANDS = [
        'birds' => ['جزيرة الطيور', 'Birds Island', 'ريشة العصفورة بتستناك فوق السحاب: كل ما تتعلم أكتر، الأعشاش تتملي.', 'Risha the bird waits above the clouds: the more you learn, the fuller the nests.',
            '🐦', ['sky' => '#5ec8f2', 'sea' => '#a8e2f7', 'land' => '#7fd1a8', 'accent' => '#ffb703'], 'major', [
                ['risha', 'ريشة', 'Risha', '🐦', 'common'],
                ['dove', 'قمرية', 'Dove', '🕊️', 'common'],
                ['parrot', 'بغبغان', 'Parrot', '🦜', 'rare'],
                ['falcon', 'صقر', 'Falcon', '🦅', 'legendary'],
            ]],
        'turtles' => ['جزيرة السلاحف', 'Turtle Island', 'على الشط سلاحف صغيرة مستنية جسر يوصلها للبحر.', 'Little turtles on the beach wait for a bridge to the sea.',
            '🐢', ['sky' => '#9be7ff', 'sea' => '#1a8fb0', 'land' => '#f7d07a', 'accent' => '#5aa44a'], 'pentatonic', [
                ['shell', 'صدفة', 'Shelly', '🐢', 'common'],
                ['crab', 'كابوريا', 'Crab', '🦀', 'common'],
                ['octopus', 'أخطبوط', 'Octopus', '🐙', 'rare'],
                ['whale', 'حوت', 'Whale', '🐋', 'legendary'],
            ]],
        'monkeys' => ['غابة القرود', 'Monkey Jungle', 'في الغابة الموز معلّق فوق: جاوب صح وطلّع القرود لفوق.', 'In the jungle the bananas hang high: answer right and the monkeys climb.',
            '🐒', ['sky' => '#b8f0a0', 'sea' => '#3fae5a', 'land' => '#2d7d46', 'accent' => '#ffd23f'], 'dorian', [
                ['monkey', 'موزة', 'Banana', '🐒', 'common'],
                ['sloth', 'كسلان', 'Sloth', '🦥', 'common'],
                ['elephant', 'فيلو', 'Elephanto', '🐘', 'rare'],
                ['tiger', 'نمورة', 'Tigra', '🐯', 'legendary'],
            ]],
        'penguins' => ['جزيرة البطاريق', 'Penguin Island', 'جليد وتزحلق وبطاريق بتحب السرعة.', 'Ice, slides and penguins who love speed.',
            '🐧', ['sky' => '#d6f1ff', 'sea' => '#5aa9e6', 'land' => '#ffffff', 'accent' => '#3a86ff'], 'lydian', [
                ['penguin', 'بطبوط', 'Pengo', '🐧', 'common'],
                ['seal', 'فقمة', 'Seal', '🦭', 'common'],
                ['polar', 'دبدوب', 'Polar', '🐻‍❄️', 'rare'],
                ['fox', 'ثعلب الجليد', 'Snow Fox', '🦊', 'legendary'],
            ]],
        'volcano' => ['جزيرة البركان', 'Volcano Island', 'البركان نايم، واللي يجمع كل الكائنات يصحّي التنين.', 'The volcano sleeps; whoever collects every creature wakes the dragon.',
            '🌋', ['sky' => '#ffcf9e', 'sea' => '#6b2737', 'land' => '#3d2c2e', 'accent' => '#ff5d3a'], 'phrygian', [
                ['lizard', 'سحلية', 'Lizard', '🦎', 'common'],
                ['chick', 'كتكوت النار', 'Fire Chick', '🐣', 'common'],
                ['trex', 'ديناصور', 'Dino', '🦖', 'rare'],
                ['dragon', 'تنين', 'Dragon', '🐉', 'legendary'],
            ]],
    ];

    /** key => [island, title_ar, title_en, summary_ar, summary_en, how_ar, how_en, stages, kinds, min, max] */
    public const GAMES = [
        'bird-gates' => ['birds', 'بوابات ريشة', 'Risha’s Gates', 'ريشة بتطير بين البوابات، وكل بوابة فيها جزء: عدّي من البوابة اللي عليها الجزء اللي بعده بالترتيب.', 'Risha flies through gates, each holding a part: fly through the one with the next part in order.',
            'المس البوابة اللي عليها الجزء الجاي بالترتيب، وريشة هتطير لها.', 'Tap the gate with the next part in order and Risha flies to it.', ['kids', 'middle'], ['order'], 1, 5],
        'bird-balloons' => ['birds', 'بالونات الحكمة', 'Wise Balloons', 'بالونات طالعة لفوق وعليها إجابات: فرقع البالونة الصح.', 'Balloons float up carrying answers: pop the right one.',
            'اقرا السؤال وفرقع البالونة اللي عليها الإجابة الصح.', 'Read the question and pop the balloon with the right answer.', ['kids', 'middle', 'teen'], ['choice'], 2, 10],
        'bird-twins' => ['birds', 'توأم العش', 'Nest Twins', 'عصافير على فرعين: وصّل كل عصفورة بتوأمها.', 'Birds on two branches: match each bird with its twin.',
            'المس كارت من ناحية وكارت يكمّله من الناحية التانية.', 'Tap a card on one side, then its match on the other.', ['kids', 'middle', 'teen'], ['pair'], 3, 12],
        'turtle-bridge' => ['turtles', 'جسر السلاحف', 'Turtle Bridge', 'السلاحف بتعمل جسر للسلحفاة الصغيرة: رصّها بالترتيب الصح عشان توصل للبحر.', 'The turtles build a bridge for the little one: line them up in the right order to reach the sea.',
            'المس السلحفاة اللي عليها الجزء الجاي بالترتيب.', 'Tap the turtle carrying the next part in order.', ['kids', 'middle'], ['order'], 1, 4],
    ];

    public static function install(ConnectionInterface $db): void
    {
        $now = now();
        $sort = 0;
        foreach (self::ISLANDS as $key => [$ar, $en, $storyAr, $storyEn, $emoji, $colors, $music, $creatures]) {
            $islandId = $db->table('game_islands')->where('key', $key)->value('id');
            if ($islandId === null) {
                $islandId = (string) Str::uuid7();
                $db->table('game_islands')->insert([
                    'id' => $islandId, 'key' => $key, 'name_ar' => $ar, 'name_en' => $en, 'story_ar' => $storyAr, 'story_en' => $storyEn,
                    'emoji' => $emoji, 'colors' => json_encode($colors), 'music' => $music, 'sort' => ++$sort, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($creatures as $i => [$creature, $cAr, $cEn, $cEmoji, $rarity]) {
                $db->table('game_creatures')->insertOrIgnore([
                    'id' => (string) Str::uuid7(), 'island_id' => $islandId, 'key' => $key.'.'.$creature, 'name_ar' => $cAr, 'name_en' => $cEn,
                    'emoji' => $cEmoji, 'rarity' => $rarity, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $sort = 0;
        foreach (self::GAMES as $key => [$island, $ar, $en, $sumAr, $sumEn, $howAr, $howEn, $stages, $kinds, $min, $max]) {
            $sort++;
            if ($db->table('games')->where('key', $key)->exists()) {
                continue;
            }
            $gameId = (string) Str::uuid7();
            $versionId = (string) Str::uuid7();
            $html = (string) file_get_contents(self::starterPath($key));
            $db->transaction(function () use ($db, $gameId, $versionId, $key, $island, $ar, $en, $sumAr, $sumEn, $howAr, $howEn, $stages, $kinds, $min, $max, $html, $sort, $now) {
                $db->table('games')->insert([
                    'id' => $gameId, 'key' => $key, 'island_id' => $db->table('game_islands')->where('key', $island)->value('id'),
                    'title_ar' => $ar, 'title_en' => $en, 'summary_ar' => $sumAr, 'summary_en' => $sumEn,
                    'stages' => json_encode($stages), 'kinds' => json_encode($kinds), 'status' => 'live', 'current_version_id' => $versionId,
                    'sort' => $sort, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $db->table('game_versions')->insert([
                    'id' => $versionId, 'game_id' => $gameId, 'version' => 1, 'html' => $html, 'sha256' => hash('sha256', $html),
                    'manifest' => json_encode(Manifest::make($stages, $kinds, $min, $max, $howAr, $howEn)),
                    'published_at' => $now,
                ]);
            });
        }
    }

    public static function starterPath(string $key): string
    {
        return dirname(__DIR__, 2).'/resources/games/starters/'.$key.'.html';
    }
}
