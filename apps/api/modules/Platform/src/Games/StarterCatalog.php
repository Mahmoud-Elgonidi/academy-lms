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
    /** key => [name_ar, name_en, story_ar, story_en, emoji, colors, music, creatures [key, ar, en, emoji, rarity, look, story_ar, story_en]] */
    public const ISLANDS = [
        'birds' => ['جزيرة الطيور', 'Birds Island', 'ريشة العصفورة تنتظرك فوق السحاب: كلما تعلّمت أكثر، امتلأت الأعشاش.', 'Risha the bird waits above the clouds: the more you learn, the fuller the nests.',
            '🐦', ['sky' => '#5ec8f2', 'sea' => '#a8e2f7', 'land' => '#7fd1a8', 'accent' => '#ffb703'], 'major', [
                ['risha', 'ريشة', 'Risha', '🐦', 'common', ['kind' => 'bird', 'body' => '#74C0FC', 'wing' => '#4DABF7', 'belly' => '#E7F5FF', 'tuft' => '#FF6B6B', 'beak' => '#FF922B', 'pitch' => 1.0], 'أول طائر في الجزيرة. تطير بجانبك وأنت تلعب.', 'The first bird on the island. She flies beside you while you play.'],
                ['dove', 'قمرية', 'Dove', '🕊️', 'common', ['kind' => 'bird', 'body' => '#F1F3F5', 'wing' => '#ADB5BD', 'belly' => '#FFFFFF', 'tuft' => '#868E96', 'beak' => '#FFD43B', 'pitch' => 0.8], 'قمرية هادئة تحب الصباح، وتغني حين تنهي درسًا.', 'A calm dove who loves mornings and sings when you finish a lesson.'],
                ['parrot', 'ببغاء', 'Parrot', '🦜', 'rare', ['kind' => 'bird', 'body' => '#8CE99A', 'wing' => '#40C057', 'belly' => '#EBFBEE', 'tuft' => '#FF6B6B', 'beak' => '#FFD43B', 'crest' => '#FF6B6B', 'pitch' => 1.4], 'ببغاء نادر يكرّر كل كلمة جميلة تقولها.', 'A rare parrot who repeats every good word you say.'],
                ['falcon', 'صقر', 'Falcon', '🦅', 'legendary', ['kind' => 'bird', 'body' => '#FFC078', 'wing' => '#5C3D1E', 'belly' => '#FFF4E6', 'tuft' => '#FFC078', 'beak' => '#13294B', 'crest' => '#FF922B', 'pitch' => 0.7], 'صقر أسطوري، لا يأتي إلا لمن يُتقن.', 'A legendary falcon who only comes to those who master.'],
            ]],
        'turtles' => ['جزيرة السلاحف', 'Turtle Island', 'على الشاطئ سلاحف صغيرة تنتظر جسرًا يوصلها إلى البحر.', 'Little turtles on the beach wait for a bridge to the sea.',
            '🐢', ['sky' => '#9be7ff', 'sea' => '#1a8fb0', 'land' => '#f7d07a', 'accent' => '#5aa44a'], 'pentatonic', [
                ['shell', 'صدفة', 'Shelly', '🐢', 'common', ['kind' => 'turtle', 'body' => '#8CE99A', 'wing' => '#2F9E44', 'belly' => '#40C057', 'pitch' => 0.9], 'سلحفاة صغيرة تحب البحر وتمشي على مهل.', 'A little turtle who loves the sea and takes her time.'],
                ['crab', 'سلطعون', 'Crab', '🦀', 'common', ['kind' => 'crab', 'body' => '#FF6B6B', 'wing' => '#C92A2A', 'belly' => '#FFC9C9', 'pitch' => 1.3], 'سلطعون يمشي جانبيًا ويصفّق بمخالبه.', 'A crab who walks sideways and claps her claws.'],
                ['octopus', 'أخطبوط', 'Octopus', '🐙', 'rare', ['kind' => 'octopus', 'body' => '#B197FC', 'wing' => '#7048E8', 'belly' => '#E5DBFF', 'pitch' => 1.1], 'أخطبوط نادر يستطيع أن يفعل 8 أشياء معًا.', 'A rare octopus who does eight things at once.'],
                ['whale', 'حوت', 'Whale', '🐋', 'legendary', ['kind' => 'whale', 'body' => '#4DABF7', 'wing' => '#1C7ED6', 'belly' => '#E7F5FF', 'pitch' => 0.5], 'حوت أسطوري، يصل صوته إلى كل الجزر.', 'A legendary whale whose song reaches every island.'],
            ]],
        'monkeys' => ['غابة القرود', 'Monkey Jungle', 'في الغابة الموز معلّق في الأعلى: أجب إجابة صحيحة وساعد القرود على الصعود.', 'In the jungle the bananas hang high: answer right and the monkeys climb.',
            '🐒', ['sky' => '#b8f0a0', 'sea' => '#3fae5a', 'land' => '#2d7d46', 'accent' => '#ffd23f'], 'dorian', [
                ['monkey', 'موزة', 'Banana', '🐒', 'common', ['kind' => 'monkey', 'body' => '#A9733E', 'wing' => '#8C5A2B', 'belly' => '#F3D7AE', 'pitch' => 1.5], 'قرد شقي يحب الموز ويقفز من شجرة إلى شجرة.', 'A cheeky monkey who loves bananas and jumps tree to tree.'],
                ['sloth', 'كسلان', 'Sloth', '🦥', 'common', ['kind' => 'sloth', 'body' => '#C2A385', 'wing' => '#6B4F3A', 'belly' => '#EDE0D4', 'pitch' => 0.6], 'كسلان يحب النوم، لكنه يستيقظ من أجل الدرس.', 'A sleepy sloth who wakes up for lessons.'],
                ['elephant', 'فيلو', 'Elephanto', '🐘', 'rare', ['kind' => 'elephant', 'body' => '#ADB5BD', 'wing' => '#FFA8C5', 'belly' => '#DEE2E6', 'pitch' => 0.55], 'فيل نادر، لا ينسى أبدًا شيئًا تعلّمه.', 'A rare elephant who never forgets what he learned.'],
                ['tiger', 'نمورة', 'Tigra', '🐯', 'legendary', ['kind' => 'tiger', 'body' => '#FF922B', 'wing' => '#13294B', 'belly' => '#FFF4E6', 'pitch' => 0.75], 'نمرة أسطورية، أسرع من في الغابة.', 'A legendary tiger, the fastest in the jungle.'],
            ]],
        'penguins' => ['جزيرة البطاريق', 'Penguin Island', 'جليد وتزلّج وبطاريق تحب السرعة.', 'Ice, slides and penguins who love speed.',
            '🐧', ['sky' => '#d6f1ff', 'sea' => '#5aa9e6', 'land' => '#ffffff', 'accent' => '#3a86ff'], 'lydian', [
                ['penguin', 'بطبوط', 'Pengo', '🐧', 'common', ['kind' => 'penguin', 'body' => '#1F2A44', 'wing' => '#1F2A44', 'belly' => '#FFFFFF', 'beak' => '#FF922B', 'pitch' => 1.2], 'بطريق يتزلّج على بطنه فوق الجليد.', 'A penguin who slides on his belly across the ice.'],
                ['seal', 'فقمة', 'Seal', '🦭', 'common', ['kind' => 'seal', 'body' => '#A5B4C4', 'wing' => '#748596', 'belly' => '#DDE6EE', 'pitch' => 0.9], 'فقمة تحب اللعب بالكرة.', 'A seal who loves playing with a ball.'],
                ['polar', 'دبدوب', 'Polar', '🐻‍❄️', 'rare', ['kind' => 'bear', 'body' => '#F8F9FA', 'wing' => '#CED4DA', 'belly' => '#E9ECEF', 'pitch' => 0.6], 'دبدوب جليد نادر، حضنه دافئ.', 'A rare polar bear with a warm hug.'],
                ['fox', 'ثعلب الجليد', 'Snow Fox', '🦊', 'legendary', ['kind' => 'fox', 'body' => '#F1F3F5', 'wing' => '#74C0FC', 'belly' => '#FFFFFF', 'pitch' => 1.0], 'ثعلب الجليد الأسطوري، ذكي وسريع.', 'The legendary snow fox, clever and quick.'],
            ]],
        'volcano' => ['جزيرة البركان', 'Volcano Island', 'البركان نائم، ومن يجمع كل الكائنات يوقظ التنين.', 'The volcano sleeps; whoever collects every creature wakes the dragon.',
            '🌋', ['sky' => '#ffcf9e', 'sea' => '#6b2737', 'land' => '#3d2c2e', 'accent' => '#ff5d3a'], 'phrygian', [
                ['lizard', 'سحلية', 'Lizard', '🦎', 'common', ['kind' => 'lizard', 'body' => '#69DB7C', 'wing' => '#2F9E44', 'belly' => '#D3F9D8', 'pitch' => 1.3], 'سحلية تحب الشمس وتغيّر لونها.', 'A lizard who loves the sun and changes colour.'],
                ['chick', 'كتكوت النار', 'Fire Chick', '🐣', 'common', ['kind' => 'chick', 'body' => '#FFD43B', 'wing' => '#FAB005', 'belly' => '#FFF3BF', 'tuft' => '#FF6B3D', 'beak' => '#FF922B', 'pitch' => 1.7], 'كتكوت النار، صغير لكنه شجاع.', 'The fire chick, small but brave.'],
                ['trex', 'ديناصور', 'Dino', '🦖', 'rare', ['kind' => 'dino', 'body' => '#63E6BE', 'wing' => '#0CA678', 'belly' => '#C3FAE8', 'pitch' => 0.5], 'ديناصور نادر، صوته عالٍ لكن قلبه طيب.', 'A rare dinosaur with a loud roar and a kind heart.'],
                ['dragon', 'تنين', 'Dragon', '🐉', 'legendary', ['kind' => 'dragon', 'body' => '#38D9A9', 'wing' => '#9775FA', 'belly' => '#C3FAE8', 'tuft' => '#FF922B', 'pitch' => 0.45], 'التنين الأسطوري، يستيقظ حين تجمع كل الكائنات.', 'The legendary dragon, who wakes when you collect them all.'],
            ]],
    ];

    /** key => [island, title_ar, title_en, summary_ar, summary_en, how_ar, how_en, stages, kinds, min, max] */
    public const GAMES = [
        'bird-gates' => ['birds', 'بوابات ريشة', 'Risha’s Gates', 'تطير ريشة بين البوابات، وفي كل بوابة جزء: اعبر من البوابة التي عليها الجزء التالي بالترتيب.', 'Risha flies through gates, each holding a part: fly through the one with the next part in order.',
            'المس البوابة التي عليها الجزء التالي بالترتيب، وستطير ريشة إليها.', 'Tap the gate with the next part in order and Risha flies to it.', ['kids', 'middle'], ['order'], 1, 5],
        'bird-balloons' => ['birds', 'بالونات الحكمة', 'Wise Balloons', 'بالونات تصعد إلى الأعلى وعليها إجابات: فرقع البالونة الصحيحة.', 'Balloons float up carrying answers: pop the right one.',
            'اقرأ السؤال وفرقع البالونة التي عليها الإجابة الصحيحة.', 'Read the question and pop the balloon with the right answer.', ['kids', 'middle', 'teen'], ['choice'], 2, 10],
        'bird-twins' => ['birds', 'توأم العش', 'Nest Twins', 'عصافير على فرعين: وصّل كل عصفورة بتوأمها.', 'Birds on two branches: match each bird with its twin.',
            'المس بطاقة من جهة وبطاقة تكمّلها من الجهة الأخرى.', 'Tap a card on one side, then its match on the other.', ['kids', 'middle', 'teen'], ['pair'], 3, 12],
        'turtle-bridge' => ['turtles', 'جسر السلاحف', 'Turtle Bridge', 'تبني السلاحف جسرًا للسلحفاة الصغيرة: رتّبها بالترتيب الصحيح لتصل إلى البحر.', 'The turtles build a bridge for the little one: line them up in the right order to reach the sea.',
            'المس السلحفاة التي عليها الجزء التالي بالترتيب.', 'Tap the turtle carrying the next part in order.', ['kids', 'middle'], ['order'], 1, 4],
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
            foreach ($creatures as $i => [$creature, $cAr, $cEn, $cEmoji, $rarity, $look, $storyAr, $storyEn]) {
                $db->table('game_creatures')->insertOrIgnore([
                    'id' => (string) Str::uuid7(), 'island_id' => $islandId, 'key' => $key.'.'.$creature, 'name_ar' => $cAr, 'name_en' => $cEn,
                    'emoji' => $cEmoji, 'rarity' => $rarity, 'look' => json_encode($look), 'story_ar' => $storyAr, 'story_en' => $storyEn,
                    'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
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
