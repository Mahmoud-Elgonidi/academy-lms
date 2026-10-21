<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\Practice\SuggestNodeItems;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Texts\QuranText;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;
use Tests\Support\QuranProgram;

uses(QuranProgram::class);

/*
 * A lesson's text and the practice helper (docs/architecture/02, section 22):
 * the academy types a lesson's words, or names a passage of the Quran whose
 * words come only from the platform's copy; the helper suggests practice from
 * them, quoting the text by number so every quoted word is the text as kept,
 * and saves nothing until a person does.
 *
 * The Quran here is a fixture in the shape of Tanzil's files (made-up words):
 * surah 1 has three verses (the first is the basmala), surah 2 two (the basmala
 * at the head of its first verse, which is kept apart).
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->admin = User::factory()->withRole(Role::Admin)->create(['name' => 'Huda Admin', 'email' => 'huda@noor.test']);
    $this->program = $this->publishQuranProgram(app(CatalogApi::class)->createSubject('قرآن', 'Quran'));
    $this->lesson = Node::query()->findOrFail($this->lessons[0]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/quran')));
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test']);
    RateLimiter::clear('ai:practice:'.$this->academy->id.':'.$this->admin->id);
});

function setText(Node $lesson, ?array $source): Node
{
    return app(SaveNode::class)->handle($lesson->version, $lesson, ['title_ar' => $lesson->title_ar, 'title_en' => $lesson->title_en, 'text_source' => $source]);
}

function textRefusal(Closure $run): ?string
{
    try {
        $run();
    } catch (ValidationException $e) {
        return array_key_first($e->errors());
    }

    return null;
}

/** A language model that answers what it is given and keeps the prompt it was sent. */
function fakeModel(array $answer): object
{
    $model = new class($answer) implements LanguageModel
    {
        public ?AiPrompt $prompt = null;

        public function __construct(private array $answer) {}

        public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
        {
            $this->prompt = $prompt;

            return new AiAnswer($this->answer, 'the-writer', ['input' => 10, 'output' => 10]);
        }
    };
    app()->instance(LanguageModel::class, $model);

    return $model;
}

it('keeps a lesson\'s own words, or a passage of the Quran from the platform\'s copy alone', function () {
    $typed = setText($this->lesson, ['kind' => 'text', 'text' => "  أ ب ت\nث  "]);
    $passage = app(CurriculumApi::class)->passage(TextSource::fromArray($typed->text_source));
    expect($passage->words)->toBe(['أ', 'ب', 'ت', 'ث'])
        ->and($passage->quote(2, 3))->toBe('ب ت')
        ->and($passage->quote(4, 5))->toBe('')
        ->and($passage->attribution)->toBeNull();

    $quran = setText($this->lesson, ['kind' => 'quran', 'surah' => '2', 'from' => 1, 'to' => '2', 'text' => 'ignored']);
    expect($quran->fresh()->text_source)->toEqual(['kind' => 'quran', 'surah' => 2, 'from' => 1, 'to' => 2]);   // as kept (jsonb orders the keys its own way)
    $passage = app(CurriculumApi::class)->passage(TextSource::fromArray($quran->text_source));
    expect($passage->title)->toBe('الثانية ١–٢')
        ->and($passage->words)->toBe(['حَرْفٌ', 'نَصٌّ', 'آخَرُ', 'لِلتَّجْرِبَةِ', 'فَقَطْ'])     // the basmala kept apart
        ->and(array_column($passage->verses, 'verse'))->toBe([1, 2])
        ->and($passage->verses[1])->toMatchArray(['first' => 2, 'last' => 5])
        ->and($passage->text())->toBe('حَرْفٌ ﴿١﴾ نَصٌّ آخَرُ لِلتَّجْرِبَةِ فَقَطْ ﴿٢﴾')
        ->and($passage->attribution)->toContain('Tanzil');

    // Al-Fatiha's basmala is its first verse.
    expect(app(CurriculumApi::class)->passage(TextSource::quran(1, 1, 1))->words)->toBe(['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ'])
        ->and(app(CurriculumApi::class)->surahs())->toBe([
            ['number' => 1, 'name_ar' => 'الأولى', 'name_en' => 'Al-Ula', 'verses' => 3],
            ['number' => 2, 'name_ar' => 'الثانية', 'name_en' => 'Ath-Thaniya', 'verses' => 2],
        ]);

    setText($this->lesson, null);
    expect($this->lesson->fresh()->text_source)->toBeNull();
});

it('refuses a text too long, a passage that is not there, and a text on anything but a lesson', function () {
    expect(textRefusal(fn () => setText($this->lesson, ['kind' => 'text', 'text' => str_repeat('ب', TextSource::TEXT_MAX + 1)])))->toBe('text_source')
        ->and(textRefusal(fn () => setText($this->lesson, ['kind' => 'quran', 'surah' => 3, 'from' => 1, 'to' => 1])))->toBe('text_source')
        ->and(textRefusal(fn () => setText($this->lesson, ['kind' => 'quran', 'surah' => 1, 'from' => 2, 'to' => 4])))->toBe('text_source')
        ->and(textRefusal(fn () => setText($this->lesson, ['kind' => 'quran', 'surah' => 1, 'from' => 3, 'to' => 2])))->toBe('text_source')
        ->and(textRefusal(fn () => setText($this->lesson, ['kind' => 'poem', 'text' => 'x'])))->toBe('text_source');

    $unit = Node::query()->findOrFail($this->lesson->parent_id);
    expect(textRefusal(fn () => setText($unit, ['kind' => 'text', 'text' => 'نص'])))->toBe('text_source')
        ->and(fn () => DB::table('curriculum_nodes')->where('id', $unit->id)->update(['text_source' => json_encode(['kind' => 'text', 'text' => 'نص'])]))
        ->toThrow(QueryException::class);

    // Until the platform has the Quran's files, it is not offered.
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/nowhere')));
    expect(textRefusal(fn () => setText($this->lesson, ['kind' => 'quran', 'surah' => 1, 'from' => 1, 'to' => 1])))->toBe('text_source')
        ->and(app(CurriculumApi::class)->surahs())->toBe([]);
});

it('knows three words of the Quran in a row wherever they are written, marks and alef forms aside', function () {
    $quran = app(QuranText::class);

    expect($quran->quotes('سؤال: كلمه اولي وثانيه؟'))->toBeTrue()
        ->and($quran->quotes('كَلِمَةٌ أُولَى'))->toBeFalse()
        ->and($quran->quotes('كلمة ثالثة أولى'))->toBeFalse();
});

it('suggests practice that quotes the passage as kept, and drops what it may not offer', function () {
    app(SaveAiSettings::class)->handle(['practice_items' => true], $this->admin->id);
    $lesson = setText($this->lesson, ['kind' => 'quran', 'surah' => 2, 'from' => 1, 'to' => 2]);
    app(SaveNodeItems::class)->handle($lesson, [['kind' => 'pair', 'left' => 'حرف', 'right' => 'نص']], $this->admin->id);
    $model = fakeModel(['items' => [
        ['kind' => 'order', 'prompt' => 'رتّب', 'parts_from' => 2, 'parts_to' => 5],
        ['kind' => 'choice', 'prompt' => 'إيه اللي بعد {{2-3}}؟', 'options' => ['{{4}}', '{{5}}'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'أكمل: نص آخر …', 'options' => ['للتجربة', 'فقط'], 'answer' => 0],     // writes the passage itself
        ['kind' => 'choice', 'prompt' => 'إيه اللي بعد {{9}}؟', 'options' => ['{{1}}', '{{2}}'], 'answer' => 0],     // outside the passage
        ['kind' => 'choice', 'prompt' => 'سؤال', 'options' => ['واحدة'], 'answer' => 0],                            // one answer
        ['kind' => 'pair', 'left' => 'حرف', 'right' => 'نص'],                                                      // already on the lesson
        ['kind' => 'pair', 'left' => '{{1}}', 'right' => 'letter'],
    ]]);

    $result = app(SuggestNodeItems::class)->handle($lesson->fresh(), $this->admin->id);

    expect($result['items'])->toBe([
        ['kind' => 'order', 'prompt' => 'رتّب', 'parts' => ['نَصٌّ', 'آخَرُ', 'لِلتَّجْرِبَةِ', 'فَقَطْ']],
        ['kind' => 'choice', 'prompt' => 'إيه اللي بعد نَصٌّ آخَرُ؟', 'options' => ['لِلتَّجْرِبَةِ', 'فَقَطْ'], 'answer' => 0],
        ['kind' => 'pair', 'prompt' => null, 'left' => 'حَرْفٌ', 'right' => 'letter'],
    ])->and($result['dropped'])->toBe(4)
        ->and(NodeItem::query()->where('node_id', $lesson->id)->count())->toBe(1);   // nothing saved

    // The lesson and its words numbered; nothing about a person.
    expect($model->prompt->user)->toContain('1 حَرْفٌ')->toContain('5 فَقَطْ')->toContain('حرف ↔ نص')->toContain('Write up to 12 items.')
        ->not->toContain('Huda')->not->toContain('huda@noor.test')
        ->and($model->prompt->tier)->toBe('best');
});

it('suggests only when the academy turned the helper on, for a lesson with something to read, a few times an hour', function () {
    fakeModel(['items' => []]);
    $suggest = fn () => app(SuggestNodeItems::class)->handle($this->lesson->fresh(), $this->admin->id);

    expect(textRefusal($suggest))->toBe('items');      // no text and no objectives
    setText($this->lesson, ['kind' => 'text', 'text' => 'الألف والباء']);
    expect(fn () => $suggest())->toThrow(AiFailed::class, __('shared::ai.failed.off'));

    app(SaveAiSettings::class)->handle(['practice_items' => true], $this->admin->id);
    foreach (range(1, SuggestNodeItems::PER_HOUR) as $i) {
        expect($suggest()['items'])->toBe([]);
    }
    try {
        $suggest();
        $this->fail('suggested');
    } catch (AiFailed $e) {
        expect($e->reason)->toBe('limit')->and($e->getMessage())->toContain('60');
    }

    // A lesson with its room full is told so before anything is asked.
    RateLimiter::clear('ai:practice:'.$this->academy->id.':'.$this->admin->id);
    app(SaveNodeItems::class)->handle($this->lesson, array_map(fn ($i) => ['kind' => 'pair', 'left' => "a{$i}", 'right' => "b{$i}"], range(1, SaveNodeItems::MAX)), $this->admin->id);
    expect(textRefusal($suggest))->toBe('items');
});

it('runs on the demo\'s fixed answer, which quotes the passage too', function () {
    config(['platform.ai.driver' => 'demo', 'platform.ai.key' => '']);
    app()->forgetInstance(LanguageModel::class);
    app(SaveAiSettings::class)->handle(['practice_items' => true], $this->admin->id);
    $lesson = setText($this->lesson, ['kind' => 'quran', 'surah' => 1, 'from' => 1, 'to' => 3]);

    $items = app(SuggestNodeItems::class)->handle($lesson->fresh(), $this->admin->id)['items'];

    expect(array_column($items, 'kind'))->toBe(['order', 'choice', 'pair'])
        ->and($items[0]['parts'])->toBe(['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ', 'كَلِمَةٌ', 'أُولَى'])
        ->and($items[1]['options'])->toBe(['الرَّحْمَٰنِ', 'الرَّحِيمِ']);
});

it('sets the text in the builder, and puts suggestions in the practice form to look over and save', function () {
    app(SaveAiSettings::class)->handle(['practice_items' => true], $this->admin->id);
    fakeModel(['items' => [['kind' => 'pair', 'left' => '{{1}}', 'right' => 'letter'], ['kind' => 'order', 'parts_from' => 1, 'parts_to' => 2]]]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    $builder = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->callAction(TestAction::make('editNode')->arguments(['node' => $this->lesson->id]), [
            'title_ar' => 'درس ١', 'title_en' => 'Lesson 1', 'text_kind' => 'quran', 'surah' => 2, 'from' => 1, 'to' => 2,
        ])->assertHasNoActionErrors();
    expect($this->lesson->fresh()->text_source)->toEqual(['kind' => 'quran', 'surah' => 2, 'from' => 1, 'to' => 2]);   // as kept (jsonb orders the keys its own way)

    $builder->callAction(TestAction::make('suggest')->arguments(['node' => $this->lesson->id]))
        ->assertActionMounted('practice')
        ->assertSchemaStateSet(function (array $state) {
            expect(collect($state['items'])->pluck('suggested')->all())->toBe([true, true]);    // marked until saved

            return [];
        })
        ->callMountedAction()->assertHasNoActionErrors();

    expect(NodeItem::query()->where('node_id', $this->lesson->id)->orderBy('ordinal')->get()->map(fn (NodeItem $i) => $i->toData()->summary())->all())
        ->toBe(['حَرْفٌ ↔ letter', 'حَرْفٌ · نَصٌّ']);
});
