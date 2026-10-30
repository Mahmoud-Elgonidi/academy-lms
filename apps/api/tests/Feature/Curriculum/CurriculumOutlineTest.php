<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\Outline\ApplyOutline;
use Modules\Curriculum\Actions\Outline\CopyProgram;
use Modules\Curriculum\Actions\Outline\SuggestOutline;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Actions\Resources\RemoveNodeResource;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Filament\Programs\ManagePrograms;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\Curriculum\Models\NodeResource;
use Modules\Curriculum\Models\Program;
use Modules\Curriculum\Outline\OutlineSheet;
use Modules\Curriculum\Outline\Templates;
use Modules\Curriculum\Outline\TextOutline;
use Modules\Curriculum\Texts\QuranText;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;

/*
 * The quick ways to start a curriculum (docs/architecture/02, section 6):
 * every way hands back an outline to review, saved to the draft in one
 * transaction by ApplyOutline (limits, audited with counts); the helper's
 * outline is sent the subject and nothing about a person, and for the Quran
 * keeps only numbers the platform's copy has; templates; and a copy of a
 * program of the same academy, its materials pointing at the same files.
 *
 * The Quran here is made up in the shape of Tanzil's files: 114 surahs named
 * "سورة n", each with n % 7 + 3 verses.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->admin = User::factory()->withRole(Role::Admin)->create(['name' => 'Huda Admin', 'email' => 'huda@noor.test']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $this->subject = app(CatalogApi::class)->createSubject('تحفيظ القرآن', 'Quran');
    $this->program = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'جزء عمّ', 'name_en' => 'Juz Amma']);
    $this->version = $this->program->workingVersion();
    app()->instance(QuranText::class, new QuranText(oQuran()));
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'a-key-for-the-test']);
    RateLimiter::clear('ai:outline:'.$this->academy->id.':'.$this->admin->id);
});

/** A made-up Quran of 114 surahs in Tanzil's shape, written once a run. */
function oQuran(): string
{
    $dir = sys_get_temp_dir().'/lms-outline-quran';
    if (! is_file($dir.'/quran-data.xml')) {
        @mkdir($dir, 0777, true);
        $suras = '';
        $text = '';
        foreach (range(1, 114) as $s) {
            $ayas = $s % 7 + 3;
            $suras .= "<sura index=\"{$s}\" ayas=\"{$ayas}\" start=\"0\" name=\"سورة {$s}\" tname=\"Surah-{$s}\" ename=\"S{$s}\" type=\"Meccan\" order=\"{$s}\" rukus=\"1\" />\n";
            foreach (range(1, $ayas) as $v) {
                $text .= "{$s}|{$v}|كلمة{$s} آية{$v} نص\n";
            }
        }
        file_put_contents($dir.'/quran-data.xml', "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<quran><suras alias=\"chapters\">\n{$suras}</suras><juzs alias=\"parts\"><juz index=\"1\" sura=\"1\" aya=\"1\" /></juzs><pages><page index=\"1\" sura=\"1\" aya=\"1\" /></pages></quran>");
        file_put_contents($dir.'/quran-simple.txt', $text."\n# A made-up test copy\n");
    }

    return $dir;
}

/** A language model that answers what it is given and keeps the prompt it was sent. */
function oModel(array $answer): object
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

/** @return list<string> each node as "kind title_en" in teaching order, with "> " per depth */
function oTree(string $versionId): array
{
    $out = [];
    $walk = function (?string $parent, int $depth) use (&$walk, &$out, $versionId) {
        foreach (Node::query()->where('version_id', $versionId)->where('parent_id', $parent)->orderBy('ordinal')->get() as $n) {
            $out[] = str_repeat('> ', $depth).$n->kind.' '.$n->title_en;
            $walk($n->id, $depth + 1);
        }
    };
    $walk(null, 0);

    return $out;
}

function oRefusal(Closure $run): ?string
{
    try {
        $run();
    } catch (ValidationException $e) {
        return collect($e->errors())->flatten()->first();
    }

    return null;
}

it('saves a reviewed outline to the draft in one go, unticked branches left out, audited with counts', function () {
    $rows = TextOutline::parse("Level one\n  Unit\n    Alif | Knows alif\n    Baa\n  Loose\nLevel two\n  Taa", 'x');
    $rows[1]['on'] = true;
    $rows[5]['on'] = false;                                         // Level two and its lesson stay out
    $rows[] = ['kind' => 'level', 'depth' => 0, 'title_ar' => 'قرآن', 'title_en' => 'Quran'];
    $rows[] = ['kind' => 'lesson', 'depth' => 1, 'title_ar' => 'سورة 112', 'title_en' => 'Surah 112', 'quran' => ['surah' => 112, 'from' => 1, 'to' => 3]];

    expect(app(ApplyOutline::class)->handle($this->version, $rows))->toBe(['levels' => 2, 'units' => 1, 'lessons' => 4]);

    expect(oTree($this->version->id))->toBe([
        'level Level one', '> unit Unit', '> > lesson Alif', '> > lesson Baa', '> lesson Loose',
        'level Quran', '> lesson Surah 112',
    ])->and(Node::query()->where('title_en', 'Alif')->value('objectives'))->toBe('Knows alif')
        ->and(Node::query()->where('title_en', 'Surah 112')->first()->text_source)->toEqual(['kind' => 'quran', 'surah' => 112, 'from' => 1, 'to' => 3]);

    $log = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'curriculum.outline_applied')->sole();
    expect(json_decode($log->data, true))->toEqual(['levels' => 2, 'units' => 1, 'lessons' => 4])
        ->and($log->actor_id)->toBe($this->admin->id);
});

it('saves all of it or nothing, within the limits, on a draft only', function () {
    // A passage the platform does not have refuses the whole outline (SaveNode checks it), nothing kept.
    $rows = [
        ['kind' => 'level', 'depth' => 0, 'title_ar' => 'L', 'title_en' => 'L'],
        ['kind' => 'lesson', 'depth' => 1, 'title_ar' => 'a', 'title_en' => 'a'],
        ['kind' => 'lesson', 'depth' => 1, 'title_ar' => 'b', 'title_en' => 'b', 'quran' => ['surah' => 112, 'from' => 1, 'to' => 40]],
    ];
    expect(oRefusal(fn () => app(ApplyOutline::class)->handle($this->version, $rows)))->not->toBeNull()
        ->and(Node::query()->where('version_id', $this->version->id)->count())->toBe(0);

    $many = [['kind' => 'level', 'depth' => 0, 'title_ar' => 'L', 'title_en' => 'L'], ...array_map(fn ($i) => ['kind' => 'lesson', 'depth' => 1, 'title_ar' => "a{$i}", 'title_en' => "a{$i}"], range(1, OutlineRules::LESSONS_MAX + 1))];
    expect(oRefusal(fn () => app(ApplyOutline::class)->handle($this->version, $many)))->toBe(__('curriculum::outline.errors.lessons', ['lessons' => OutlineRules::LESSONS_MAX]));

    // Counted with what the version has: 11 levels there, two more is past 12.
    foreach (range(1, 11) as $i) {
        app(SaveNode::class)->handle($this->version, null, ['kind' => 'level', 'title_ar' => "L{$i}", 'title_en' => "L{$i}"]);
    }
    $two = [['kind' => 'level', 'depth' => 0, 'title_ar' => 'x', 'title_en' => 'x'], ['kind' => 'level', 'depth' => 0, 'title_ar' => 'y', 'title_en' => 'y']];
    expect(oRefusal(fn () => app(ApplyOutline::class)->handle($this->version, $two)))->toBe(__('curriculum::outline.errors.levels', ['levels' => OutlineRules::LEVELS_MAX]))
        ->and(oRefusal(fn () => app(ApplyOutline::class)->handle($this->version, [['kind' => 'level', 'depth' => 0, 'title_ar' => 'x', 'on' => false]])))->toBe(__('curriculum::outline.errors.empty'));

    app(ApplyOutline::class)->handle($this->version, [$two[0], ['kind' => 'lesson', 'depth' => 1, 'title_ar' => 'z', 'title_en' => 'z']]);
    Node::query()->where('version_id', $this->version->id)->where('kind', 'level')->where('title_en', '!=', 'x')->get()->each(fn (Node $n) => app(DeleteNode::class)->handle($n));
    app(PublishVersion::class)->handle($this->version->fresh(), null);
    expect(oRefusal(fn () => app(ApplyOutline::class)->handle($this->version->fresh(), $two)))->toBe(__('curriculum::outline.errors.not_draft'))
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'curriculum.outline_applied')->count())->toBe(1);
});

it('asks the helper with the subject and no one, and keeps for the Quran only numbers the copy has', function () {
    app(SaveAiSettings::class)->handle(['curriculum_outline' => true], $this->admin->id);
    $model = oModel(['levels' => [[
        'title_ar' => 'قصار السور', 'title_en' => 'Short surahs', 'units' => [],
        'lessons' => [
            ['surah' => 114, 'from' => 1, 'to' => 5, 'objective' => 'الحفظ'],
            ['surah' => 2, 'from' => 1, 'to' => 3, 'objective' => 'تلاوة كلمة2 آية1 نص بإتقان'],   // the objective writes the Quran: left out
            ['surah' => 115, 'from' => 1, 'to' => 2, 'objective' => 'x'],                         // no such surah
            ['surah' => 112, 'from' => 2, 'to' => 9, 'objective' => 'x'],                         // past the surah's end
            ['surah' => 113, 'from' => 2, 'to' => 3, 'objective' => 'مراجعة'],
        ],
    ]]]);

    $result = app(SuggestOutline::class)->handle($this->program, ['goal' => 'حفظ قصار السور', 'lessons' => 10, 'age' => 'kids', 'quran' => true], $this->admin->id);

    expect($result['dropped'])->toBe(2)
        ->and(array_map(fn ($r) => [$r['kind'], $r['title_ar'], $r['objective'], $r['quran']], $result['rows']))->toBe([
            ['level', 'قصار السور', null, null],
            ['lesson', 'سورة سورة 114', 'الحفظ', ['surah' => 114, 'from' => 1, 'to' => 5]],
            ['lesson', 'سورة سورة 2 (1–3)', null, ['surah' => 2, 'from' => 1, 'to' => 3]],
            ['lesson', 'سورة سورة 113 (2–3)', 'مراجعة', ['surah' => 113, 'from' => 2, 'to' => 3]],
        ])
        ->and($result['rows'][1]['title_en'])->toBe('Surah Surah-114')
        ->and(Node::query()->count())->toBe(0);                     // nothing saved

    expect($model->prompt->user)->toContain('Subject: Quran')->toContain('children aged 6 to 9')->toContain('about 10')->toContain('حفظ قصار السور')
        ->toContain('only by numbers')->not->toContain('Huda')->not->toContain('huda@noor.test')
        ->and($model->prompt->tier)->toBe('best')
        ->and($model->prompt->schema['properties']['levels']['items']['properties']['lessons']['items']['required'])->toBe(['surah', 'from', 'to', 'objective'])
        ->and($model->prompt->system)->toContain('never write any of the Quran');
});

it('suggests nothing while the helper is off, and says why when it fails', function () {
    oModel(['levels' => []]);
    $ask = ['goal' => 'Read short words', 'lessons' => 12, 'age' => 'adults'];

    expect(fn () => app(SuggestOutline::class)->handle($this->program, $ask, $this->admin->id))->toThrow(AiFailed::class, __('shared::ai.failed.off'));

    app(SaveAiSettings::class)->handle(['curriculum_outline' => true], $this->admin->id);
    expect(fn () => app(SuggestOutline::class)->handle($this->program, $ask, $this->admin->id))->toThrow(AiFailed::class, __('shared::ai.failed.unreadable'));

    expect(oRefusal(fn () => app(SuggestOutline::class)->handle($this->program, ['goal' => ''] + $ask, $this->admin->id)))->toBe(__('curriculum::outline.ai.errors.goal', ['max' => SuggestOutline::GOAL_MAX]))
        ->and(oRefusal(fn () => app(SuggestOutline::class)->handle($this->program, ['lessons' => 301] + $ask, $this->admin->id)))->toBe(__('curriculum::outline.ai.errors.lessons', ['max' => OutlineRules::LESSONS_MAX]));
});

it('runs on the demo\'s answer: the academy\'s levels, or the last surahs for the Quran', function () {
    config(['platform.ai.driver' => 'demo', 'platform.ai.key' => '']);
    app()->forgetInstance(LanguageModel::class);
    app(SaveAiSettings::class)->handle(['curriculum_outline' => true], $this->admin->id);

    $plain = app(SuggestOutline::class)->handle($this->program, ['goal' => 'x', 'lessons' => 4, 'age' => 'any'], $this->admin->id)['rows'];
    $quran = app(SuggestOutline::class)->handle($this->program, ['goal' => 'x', 'lessons' => 3, 'age' => 'any', 'quran' => true], $this->admin->id)['rows'];

    expect(OutlineRules::counts($plain))->toBe(['levels' => 2, 'units' => 0, 'lessons' => 4])
        ->and(array_column(array_filter($quran, fn ($r) => $r['kind'] === 'lesson'), 'quran'))->toBe([
            ['surah' => 114, 'from' => 1, 'to' => 5], ['surah' => 113, 'from' => 1, 'to' => 4], ['surah' => 112, 'from' => 1, 'to' => 3],
        ]);
});

it('offers templates in both languages, Juz Amma from the platform\'s names only while it has them', function () {
    $templates = app(Templates::class);
    $juz = $templates->rows('juz_amma');
    $letters = $templates->rows('arabic_letters');
    $english = OutlineRules::clean($templates->rows('english_cefr'))['rows'];
    $noorani = $templates->rows('noorani');

    expect(array_keys($templates->options()))->toBe(Templates::KEYS)
        ->and(count($juz))->toBe(38)
        ->and($juz[1])->toMatchArray(['title_ar' => 'سورة سورة 114', 'title_en' => 'Surah Surah-114', 'quran' => ['surah' => 114, 'from' => 1, 'to' => 5]])
        ->and(end($juz)['quran'])->toBe(['surah' => 78, 'from' => 1, 'to' => 4])
        ->and(count($letters))->toBe(29)
        ->and($letters[2])->toMatchArray(['title_ar' => 'حرف الباء (ب)', 'title_en' => 'The letter Baa (ب)'])
        ->and(OutlineRules::counts($english))->toBe(['levels' => 5, 'units' => 20, 'lessons' => 60])
        ->and($english[0]['title_en'])->toBe('Level A1')
        ->and(count($noorani))->toBe(18);

    // Saved as they are, the Quran's text from the copy.
    app(ApplyOutline::class)->handle($this->version, $juz);
    expect(Node::query()->where('kind', 'lesson')->count())->toBe(37);

    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/nowhere')));
    expect(app(Templates::class)->options())->not->toHaveKey('juz_amma')
        ->and(app(Templates::class)->rows('juz_amma'))->toBe([]);
});

it('reads the example sheet it hands out', function () {
    $path = OutlineSheet::template();
    $rows = TextOutline::fromSheet(OutlineSheet::read($path, 'outline.xlsx'), OutlineSheet::headers());
    @unlink($path);

    expect(OutlineRules::counts($rows))->toBe(['levels' => 2, 'units' => 2, 'lessons' => 4])
        ->and(OutlineSheet::read(__FILE__, 'not-a-sheet.xlsx'))->toBeNull();
});

it('copies a program with its practice, texts and materials, the files shared and kept while one points at them', function () {
    Storage::fake('local');
    $level = app(SaveNode::class)->handle($this->version, null, ['kind' => 'level', 'title_ar' => 'م', 'title_en' => 'L']);
    $unit = app(SaveNode::class)->handle($this->version, null, ['kind' => 'unit', 'parent_id' => $level->id, 'title_ar' => 'و', 'title_en' => 'U']);
    $lesson = app(SaveNode::class)->handle($this->version, null, ['kind' => 'lesson', 'parent_id' => $unit->id, 'title_ar' => 'د', 'title_en' => 'A', 'objectives' => 'Goal', 'weight' => 3, 'est_sessions' => 2, 'mastery_rule' => 'score_twice', 'text_source' => ['kind' => 'quran', 'surah' => 112, 'from' => 1, 'to' => 3]]);
    app(SaveNode::class)->handle($this->version, null, ['kind' => 'lesson', 'parent_id' => $level->id, 'title_ar' => 'ب', 'title_en' => 'B']);
    app(SaveNodeItems::class)->handle($lesson, [['kind' => 'pair', 'left' => 'a', 'right' => 'b'], ['kind' => 'choice', 'prompt' => 'Q', 'options' => ['x', 'y'], 'answer' => 1]], $this->admin->id);
    $file = app(AddNodeResource::class)->handle($lesson, ['kind' => 'file', 'audience' => 'teachers'], UploadedFile::fake()->create('page.pdf', 30, 'application/pdf'), $this->admin->id);
    app(AddNodeResource::class)->handle($lesson, ['kind' => 'link', 'url' => 'https://example.com/v', 'title_en' => 'Video'], null, $this->admin->id);
    app(PublishVersion::class)->handle($this->version, null);

    $copy = app(CopyProgram::class)->handle($this->program->id, ['name_ar' => 'نسخة', 'name_en' => 'Copy'], $this->admin->id);
    $version = $copy->workingVersion();

    expect($version->status)->toBe('draft')
        ->and($copy->subject_id)->toBe($this->subject)
        ->and(oTree($version->id))->toBe(oTree($this->version->id));
    $copied = Node::query()->where('version_id', $version->id)->where('title_en', 'A')->sole();
    expect($copied->only(['objectives', 'weight', 'est_sessions', 'mastery_rule']))->toBe(['objectives' => 'Goal', 'weight' => 3, 'est_sessions' => 2, 'mastery_rule' => 'score_twice'])
        ->and($copied->text_source)->toEqual(['kind' => 'quran', 'surah' => 112, 'from' => 1, 'to' => 3])
        ->and(NodeItem::query()->where('node_id', $copied->id)->orderBy('ordinal')->pluck('kind')->all())->toBe(['pair', 'choice']);
    $materials = NodeResource::query()->where('node_id', $copied->id)->orderBy('ordinal')->get();
    expect($materials->pluck('kind')->all())->toBe(['file', 'link'])
        ->and($materials[0]->file_path)->toBe($file->file_path)                       // the same file, not a second copy
        ->and($materials[0]->audience)->toBe('teachers')
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);

    // Taking it off one lesson keeps the file for the other; off both, it goes.
    app(RemoveNodeResource::class)->handle($file);
    Storage::disk('local')->assertExists($file->file_path);
    app(DeleteNode::class)->handle(Node::query()->where('version_id', $version->id)->where('kind', 'level')->sole());
    Storage::disk('local')->assertMissing($file->file_path);

    $log = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'curriculum.program_copied')->sole();
    expect(json_decode($log->data, true))->toEqual(['levels' => 1, 'units' => 1, 'lessons' => 2, 'items' => 2, 'materials' => 2]);
});

it('copies only a program of the same academy, only into an empty draft', function () {
    $level = app(SaveNode::class)->handle($this->version, null, ['kind' => 'level', 'title_ar' => 'م', 'title_en' => 'L']);
    app(SaveNode::class)->handle($this->version, null, ['kind' => 'lesson', 'parent_id' => $level->id, 'title_ar' => 'د', 'title_en' => 'A']);
    $mine = $this->program;

    $other = $this->actAsTenant($this->createAcademy(['slug' => 'other']));
    $theirs = app(SaveProgram::class)->handle(null, ['subject_id' => app(CatalogApi::class)->createSubject('عربي', 'Arabic'), 'name_ar' => 'س', 'name_en' => 'Secret']);
    $target = app(SaveProgram::class)->handle(null, ['subject_id' => $theirs->subject_id, 'name_ar' => 'ف', 'name_en' => 'Empty']);

    // From the other academy, this academy's program is not there to copy.
    expect(oRefusal(fn () => app(CopyProgram::class)->into($mine->id, $target, null)))->toBe(__('curriculum::outline.copy.errors.missing'))
        ->and(oRefusal(fn () => app(CopyProgram::class)->handle($mine->id, ['name_ar' => 'x', 'name_en' => 'x'], null)))->toBe(__('curriculum::outline.copy.errors.missing'))
        ->and(Node::query()->count())->toBe(0)
        ->and(Program::query()->pluck('name_en')->sort()->values()->all())->toBe(['Empty', 'Secret']);

    $this->actAsTenant($this->academy);
    $empty = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'ف', 'name_en' => 'Empty here']);
    expect(oRefusal(fn () => app(CopyProgram::class)->handle($empty->id, ['name_ar' => 'x', 'name_en' => 'x'], null)))->toBe(__('curriculum::outline.copy.errors.empty'))
        ->and(oRefusal(fn () => app(CopyProgram::class)->into($mine->id, $mine, null)))->toBe(__('curriculum::outline.copy.errors.not_empty'));

    app(CopyProgram::class)->into($mine->id, $empty, null);
    expect(oTree($empty->workingVersion()->id))->toBe(['level L', '> lesson A'])
        ->and(oRefusal(fn () => app(CopyProgram::class)->into($mine->id, $empty, null)))->toBe(__('curriculum::outline.copy.errors.not_empty'));
});

it('starts an empty curriculum from a template or a pasted list, reviewed and edited before it is saved', function () {
    $builder = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::outline.ways.ai.label'))
        ->assertSee(__('curriculum::outline.ways.list.label'))
        ->assertSee(__('curriculum::outline.ways.template.label'))
        ->assertSee(__('curriculum::outline.ways.copy.label'))
        ->callAction('startTemplate', ['template' => 'arabic_letters'])
        ->assertActionMounted('reviewOutline')
        ->assertMountedActionModalSee(__('curriculum::outline.review.counts', ['levels' => 1, 'units' => 0, 'lessons' => 28]));
    expect(Node::query()->count())->toBe(0);                                    // nothing saved yet

    $builder->set('outline.1.title_ar', 'الألف أولًا')                         // rename (its English stays: it was written apart)
        ->call('dropOutlineRow', 2)                                            // remove
        ->set('outline.2.on', false)                                           // untick
        ->assertMountedActionModalSee(__('curriculum::outline.review.counts', ['levels' => 1, 'units' => 0, 'lessons' => 26]))
        ->callMountedAction()
        ->assertNotified(__('curriculum::outline.review.saved', ['levels' => 1, 'units' => 0, 'lessons' => 26]));

    $titles = Node::query()->where('version_id', $this->version->id)->where('kind', 'lesson')->orderBy('ordinal')->pluck('title_ar')->all();
    expect($titles)->toHaveCount(26)
        ->and($titles[0])->toBe('الألف أولًا')
        ->and($titles[1])->toBe('حرف الثاء (ث)');                             // the baa removed, the taa unticked

    // A pasted list, in a second program.
    $second = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'ب', 'name_en' => 'Two']);
    Livewire::test(EditCurriculum::class, ['record' => $second->id])
        ->callAction('startList', ['text' => "Level\n  One\n  Two"])
        ->assertActionMounted('reviewOutline')
        ->set('outline.1.title_en', 'First')                                  // written the same in both: both change
        ->callMountedAction();
    expect(oTree($second->workingVersion()->id))->toBe(['level Level', '> lesson First', '> lesson Two'])
        ->and(Node::query()->where('title_en', 'First')->value('title_ar'))->toBe('First');

    // Nothing to read is said, and the way stays open.
    Livewire::test(EditCurriculum::class, ['record' => app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'ج', 'name_en' => 'Three'])->id])
        ->callAction('startList', ['text' => ''])
        ->assertNotified(__('curriculum::outline.list.one'))
        ->assertActionMounted('startList');
});

it('starts from the helper\'s outline when it is on, says why when it is off, and copies a program', function () {
    $builder = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->mountAction('startAi')
        ->assertMountedActionModalSee(__('curriculum::outline.ai.off'));

    app(SaveAiSettings::class)->handle(['curriculum_outline' => true], $this->admin->id);
    oModel(['levels' => [['title_ar' => 'المستوى الأول', 'title_en' => 'Level one', 'units' => [], 'lessons' => [['surah' => 112, 'from' => 1, 'to' => 3, 'objective' => 'الحفظ']]]]]);
    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->callAction('startAi', ['goal' => 'حفظ', 'lessons' => 5, 'age' => 'kids', 'quran' => true])
        ->assertActionMounted('reviewOutline')
        ->callMountedAction();
    expect(oTree($this->version->id))->toBe(['level Level one', '> lesson Surah Surah-112']);

    // A failing helper says so and keeps the form.
    $failing = new class implements LanguageModel
    {
        public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
        {
            throw new AiFailed('unavailable');
        }
    };
    app()->instance(LanguageModel::class, $failing);
    $second = app(SaveProgram::class)->handle(null, ['subject_id' => $this->subject, 'name_ar' => 'ب', 'name_en' => 'Two']);
    Livewire::test(EditCurriculum::class, ['record' => $second->id])
        ->callAction('startAi', ['goal' => 'حفظ', 'lessons' => 5, 'age' => 'kids'])
        ->assertNotified(__('shared::ai.failed.unavailable'))
        ->assertActionMounted('startAi');

    // Copy the first program into the second's empty draft.
    Livewire::test(EditCurriculum::class, ['record' => $second->id])
        ->callAction(TestAction::make('startCopy'), ['program' => $this->program->id])
        ->assertNotified(__('curriculum::outline.copy.done', ['levels' => 1, 'lessons' => 1]))
        ->assertDontSee(__('curriculum::outline.ways.copy.label'));
    expect(oTree($second->workingVersion()->id))->toBe(['level Level one', '> lesson Surah Surah-112']);
});

it('copies a program as a new one from the programs list', function () {
    $level = app(SaveNode::class)->handle($this->version, null, ['kind' => 'level', 'title_ar' => 'م', 'title_en' => 'L']);
    app(SaveNode::class)->handle($this->version, null, ['kind' => 'lesson', 'parent_id' => $level->id, 'title_ar' => 'د', 'title_en' => 'A']);

    Livewire::test(ManagePrograms::class)
        ->mountTableAction('copy', $this->program)
        ->assertTableActionDataSet(['name_en' => 'Juz Amma (copy)'])
        ->callMountedTableAction()
        ->assertNotified(__('curriculum::outline.copy.copied'));

    $copy = Program::query()->where('name_en', 'Juz Amma (copy)')->sole();
    expect(oTree($copy->workingVersion()->id))->toBe(['level L', '> lesson A'])
        ->and($copy->workingVersion()->status)->toBe('draft');
});
