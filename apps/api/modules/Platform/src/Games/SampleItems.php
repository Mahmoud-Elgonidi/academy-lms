<?php

namespace Modules\Platform\Games;

/**
 * Items for trying a game in the studio: what a lesson's practice items
 * look like, in Arabic, a few of each kind. The academies' own items replace
 * them when a learner plays.
 */
final class SampleItems
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            ['id' => 'sample-o1', 'kind' => 'order', 'prompt' => 'رتّب الجملة', 'parts' => ['ذهب', 'الولد', 'إلى', 'المدرسة']],
            ['id' => 'sample-o2', 'kind' => 'order', 'prompt' => 'رتّب حروف كلمة «قمر»', 'parts' => ['ق', 'م', 'ر']],
            ['id' => 'sample-o3', 'kind' => 'order', 'prompt' => 'رتّب الأعداد من الأصغر', 'parts' => ['٣', '٧', '١٢', '٢٠', '٣٥']],
            ['id' => 'sample-c1', 'kind' => 'choice', 'prompt' => 'كام ٣ + ٤ ؟', 'options' => ['٦', '٧', '٨'], 'answer' => 1],
            ['id' => 'sample-c2', 'kind' => 'choice', 'prompt' => 'أي كلمة بتبدأ بحرف الشين؟', 'options' => ['قمر', 'شمس', 'بيت', 'سمكة'], 'answer' => 1],
            ['id' => 'sample-c3', 'kind' => 'choice', 'prompt' => 'جمع كلمة «كتاب»', 'options' => ['كتابين', 'كُتب'], 'answer' => 1],
            ['id' => 'sample-c4', 'kind' => 'choice', 'prompt' => 'What is the opposite of «big»?', 'options' => ['small', 'tall', 'fast', 'happy'], 'answer' => 0],
            ['id' => 'sample-p1', 'kind' => 'pair', 'left' => 'قطة', 'right' => 'cat'],
            ['id' => 'sample-p2', 'kind' => 'pair', 'left' => 'كلب', 'right' => 'dog'],
            ['id' => 'sample-p3', 'kind' => 'pair', 'left' => 'شمس', 'right' => 'sun'],
            ['id' => 'sample-p4', 'kind' => 'pair', 'left' => 'قمر', 'right' => 'moon'],
            ['id' => 'sample-p5', 'kind' => 'pair', 'left' => '٥ × ٢', 'right' => '١٠'],
        ];
    }

    /**
     * @param  list<string>  $kinds
     * @return list<array<string, mixed>>
     */
    public static function of(array $kinds, int $max): array
    {
        $items = array_values(array_filter(self::all(), fn (array $item) => in_array($item['kind'], $kinds, true)));

        return array_slice($items, 0, max(1, $max));
    }
}
