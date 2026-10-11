<?php

use Anthropic\Beta\Messages\BetaMessage;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Games\OpenGameDraft;
use Modules\Platform\Actions\Games\PublishGameDraft;
use Modules\Platform\Actions\Games\ReviseGameDraft;
use Modules\Platform\Actions\Games\SetGameStatus;
use Modules\Platform\Actions\Games\StartGameDraft;
use Modules\Platform\Actions\Games\StopGameDesign;
use Modules\Platform\Filament\Console\Pages\GameDraftPage;
use Modules\Platform\Filament\Console\Pages\GameStudio;
use Modules\Platform\Games\Designer\ClaudeGameDesigner;
use Modules\Platform\Games\Designer\DesignBrief;
use Modules\Platform\Games\Designer\DesignedGame;
use Modules\Platform\Games\Designer\DesignFailed;
use Modules\Platform\Games\Designer\GameDesigner;
use Modules\Platform\Games\GamePage;
use Modules\Platform\Games\PackageCheck;
use Modules\Platform\Games\StarterCatalog;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameIsland;
use Modules\Platform\Models\Games\GameVersion;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Games\GameCatalog;
use Tests\Support\StarterGames;

uses(StarterGames::class);

/*
 * The games world and its studio (docs/architecture/02, section 18): one
 * catalog for every academy (islands, creatures, games and their versions,
 * read through the shared kernel's contract), every page checked before it
 * is published and served in a sandbox that cannot reach anything, and new
 * games from a story: Claude designs, the platform team reviews, asks for
 * changes and publishes. Here a stand-in designer answers.
 */

/** Answers each brief with a starter page, and remembers what it was asked. */
final class StandInDesigner implements GameDesigner
{
    /** @var list<DesignBrief> */
    public array $briefs = [];

    public ?string $html = null;

    public ?DesignFailed $fails = null;

    public function design(DesignBrief $brief): DesignedGame
    {
        $this->briefs[] = $brief;
        if ($this->fails !== null) {
            throw $this->fails;
        }

        return new DesignedGame(
            titleAr: 'بالونات النجوم', titleEn: 'Star Balloons', summaryAr: 'بالونات بين النجوم.', summaryEn: 'Balloons among the stars.',
            howToPlayAr: 'فرقع البالونة الصح.', howToPlayEn: 'Pop the right balloon.',
            stages: ['kids', 'middle'], kinds: ['choice'], minItems: 2, maxItems: 8,
            message: $brief->isRevision() ? 'غيّرت الألوان زي ما طلبت.' : 'عملت اللعبة على جزيرة '.$brief->island['name_ar'].'.',
            html: $this->html ?? (string) file_get_contents(StarterCatalog::starterPath('bird-balloons')),
            servedModel: 'stand-in', usage: ['input_tokens' => 1200, 'output_tokens' => 9000],
        );
    }
}

beforeEach(function () {
    $this->installStarterGames();
    $this->designer = new StandInDesigner;
    app()->instance(GameDesigner::class, $this->designer);
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->admin->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    $this->birds = GameIsland::query()->where('key', 'birds')->value('id');
});

afterEach(fn () => AuditActor::forget());

function newDraft(array $data = []): GameDraft
{
    return app(StartGameDraft::class)->handle(test()->admin->id, $data + [
        'story' => 'بالونات طالعة لفوق بين النجوم، وعلى كل بالونة إجابة، والطفل يفرقع الصح.',
        'island_id' => test()->birds, 'stages' => ['kids', 'middle'], 'kinds' => ['choice'], 'topic' => 'جزء عمّ',
    ]);
}

it('installs one world for every academy, which academies read only through the catalog contract', function () {
    $academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));

    expect(DB::table('games')->count())->toBe(0)
        ->and(fn () => DB::table('games')->insert(['id' => (string) Str::uuid7(), 'key' => 'mine']))->toThrow(QueryException::class);

    $catalog = app(GameCatalog::class);
    expect(collect($catalog->islands())->pluck('key')->all())->toBe(['birds', 'turtles', 'monkeys', 'penguins', 'volcano'])
        ->and(collect($catalog->islands())->sum(fn ($i) => count($i->creatures)))->toBe(20)
        ->and(collect($catalog->games())->pluck('key')->sort()->values()->all())->toBe(['bird-balloons', 'bird-gates', 'bird-twins', 'turtle-bridge'])
        ->and($catalog->game('turtle-bridge')->fits('teen'))->toBeFalse()
        ->and($catalog->game('bird-twins')->fits('teen'))->toBeTrue()
        ->and($catalog->creature('birds.falcon')?->islandKey)->toBe('birds');
    expect($academy->id)->not->toBeEmpty();
});

it('passes every starter page through the package check', function (string $key) {
    expect(PackageCheck::issues((string) file_get_contents(StarterCatalog::starterPath($key))))->toBe([]);
})->with(['bird-gates', 'bird-balloons', 'bird-twins', 'turtle-bridge']);

it('refuses a page that reaches outside, keeps data, escapes its frame or skips the bridge', function (string $code, string $rule) {
    $page = '<!doctype html><html><head></head><body><script>LMS.onStart(()=>{}); LMS.answer("a", true); LMS.finish();'.$code.'</script></body></html>';

    expect(collect(PackageCheck::issues($page))->pluck('rule')->all())->toContain($rule);
})->with([
    'fetch' => ['fetch("https://evil.test")', 'network'],
    'socket' => ['new WebSocket("wss://x")', 'network'],
    'a script from elsewhere' => ['</script><script src="https://cdn.test/x.js"></script><script>', 'outside'],
    'a picture from elsewhere' => ['</script><img src="//img.test/a.png"><script>', 'outside'],
    'storage' => ['localStorage.setItem("a", 1)', 'storage'],
    'cookies' => ['document.cookie = "a=1"', 'storage'],
    'the parent' => ['window.parent.location = "/"', 'escape'],
    'eval' => ['eval("1+1")', 'eval'],
    'a frame' => ['</script><iframe></iframe><script>', 'elements'],
]);

it('wants the whole document, the bridge calls and no inline handlers, and keeps pages small', function () {
    expect(collect(PackageCheck::issues('<div>hi</div>'))->pluck('rule')->unique()->values()->all())->toContain('document', 'bridge')
        ->and(collect(PackageCheck::issues('<html><body><button onclick="go()">x</button><script>LMS.onStart(1);LMS.answer(1);LMS.finish(1)</script></body></html>'))->pluck('rule')->all())->toBe(['handlers'])
        ->and(collect(PackageCheck::issues('<html><script>LMS.onStart(1);LMS.answer(1);LMS.finish(1)</script>'.str_repeat('x', PackageCheck::MAX_BYTES).'</html>'))->pluck('rule')->all())->toBe(['size']);
});

it('serves a live game in a sandbox that reaches nothing, with the bridge first in its head', function () {
    $version = Game::query()->where('key', 'bird-balloons')->value('current_version_id');

    $response = $this->get('/games/'.$version)->assertOk();
    $policy = $response->headers->get('Content-Security-Policy');
    expect($policy)->toBe(GamePage::POLICY)
        ->and($policy)->toContain("connect-src 'none'", 'sandbox allow-scripts', "frame-ancestors 'self'")
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->getContent())->toMatch('/<head[^>]*><script>[\s\S]*window\.LMS/');

    Game::query()->where('key', 'bird-balloons')->update(['status' => 'retired']);
    $this->get('/games/'.$version)->assertNotFound();
    $this->get('/games/'.Str::uuid7())->assertNotFound();
});

it('designs a game from a story with the island and the learners in the brief, and keeps the conversation', function () {
    $draft = newDraft()->refresh();

    expect($draft->status)->toBe('ready')
        ->and($draft->title_ar)->toBe('بالونات النجوم')
        ->and($draft->rounds)->toBe(1)
        ->and($draft->issues)->toBe([])
        ->and($draft->manifest)->toMatchArray(['stages' => ['kids', 'middle'], 'kinds' => ['choice'], 'min_items' => 2, 'max_items' => 8])
        ->and($draft->usage)->toBe(['input_tokens' => 1200, 'output_tokens' => 9000])
        ->and($draft->turns()->orderBy('created_at')->pluck('role')->all())->toBe(['admin', 'designer']);
    $brief = $this->designer->briefs[0];
    expect($brief->island['key'])->toBe('birds')
        ->and($brief->island['creatures'])->toHaveCount(4)
        ->and($brief->stages)->toBe(['kids', 'middle'])
        ->and($brief->topic)->toBe('جزء عمّ')
        ->and($brief->isRevision())->toBeFalse()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.game_draft_started')->count())->toBe(1);

    // The page under review is served only to the platform team, never kept.
    $this->get('/console/games/drafts/'.$draft->id.'/page')->assertRedirect();
    $this->actingAs($this->admin, 'platform')->get('/console/games/drafts/'.$draft->id.'/page')
        ->assertOk()->assertHeader('Content-Security-Policy', GamePage::POLICY);
    expect($this->get('/console/games/drafts/'.$draft->id.'/page')->headers->get('Cache-Control'))->toContain('no-store');
});

it('revises on the platform team\'s changes with the page as it stands, and says why when the designer cannot', function () {
    $draft = newDraft();
    app(ReviseGameDraft::class)->handle($draft, $this->admin->id, 'خلّي السما بنفسجي والبالونات أكبر');

    expect($draft->refresh()->rounds)->toBe(2)
        ->and($this->designer->briefs[1]->isRevision())->toBeTrue()
        ->and(collect($this->designer->briefs[1]->conversation)->pluck('role')->all())->toBe(['admin', 'designer', 'admin'])
        ->and($draft->turns()->orderBy('created_at')->get()->last()->body)->toBe('غيّرت الألوان زي ما طلبت.');

    $this->designer->fails = new DesignFailed('refused', 'the story asked for something unsafe');
    app(ReviseGameDraft::class)->handle($draft, $this->admin->id, 'كمان تغيير');
    expect($draft->refresh()->status)->toBe('failed')
        ->and($draft->error)->toStartWith('refused');

    // A failed round can be asked again; one already designing cannot.
    $this->designer->fails = null;
    app(ReviseGameDraft::class)->handle($draft, $this->admin->id, 'جرّب تاني');
    expect($draft->refresh()->status)->toBe('ready');
    GameDraft::query()->whereKey($draft->id)->update(['status' => 'designing']);
    expect(fn () => app(ReviseGameDraft::class)->handle($draft->refresh(), $this->admin->id, 'تاني'))->toThrow(ValidationException::class);

    // One stuck past its time can be stopped.
    GameDraft::query()->whereKey($draft->id)->update(['updated_at' => now()->subMinutes(StopGameDesign::STALE_MINUTES + 1)]);
    expect(app(StopGameDesign::class)->handle($draft->refresh()))->toBeTrue()
        ->and($draft->refresh()->status)->toBe('failed');
});

it('publishes only a page that passes the check, as a new live game every academy sees', function () {
    $this->designer->html = '<html><head></head><body><script>LMS.onStart(()=>{});LMS.answer("a",true);LMS.finish();fetch("/x")</script></body></html>';
    $draft = newDraft()->refresh();
    expect(collect($draft->issues)->pluck('rule')->all())->toBe(['network'])
        ->and(fn () => app(PublishGameDraft::class)->handle($draft, $this->admin->id, 'star-balloons'))->toThrow(ValidationException::class);

    $this->designer->html = null;
    app(ReviseGameDraft::class)->handle($draft, $this->admin->id, 'شيل الـ fetch');
    expect(fn () => app(PublishGameDraft::class)->handle($draft->refresh(), $this->admin->id, 'Bird Gates'))->toThrow(ValidationException::class)
        ->and(fn () => app(PublishGameDraft::class)->handle($draft->refresh(), $this->admin->id, 'bird-gates'))->toThrow(ValidationException::class);

    $version = app(PublishGameDraft::class)->handle($draft->refresh(), $this->admin->id, 'star-balloons');
    $game = Game::query()->findOrFail($version->game_id);
    expect($version->version)->toBe(1)
        ->and($game->status)->toBe('live')
        ->and($game->current_version_id)->toBe($version->id)
        ->and($draft->refresh()->status)->toBe('published')
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.game_published')->count())->toBe(1);

    // Published versions never change.
    expect(fn () => GameVersion::query()->whereKey($version->id)->update(['html' => '<html></html>']))->toThrow(QueryException::class);

    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    app()->forgetInstance(GameCatalog::class);
    expect(app(GameCatalog::class)->game('star-balloons')?->versionId)->toBe($version->id);
});

it('develops a live game into its next version, and retires or brings back a game', function () {
    $game = Game::query()->where('key', 'bird-gates')->firstOrFail();
    $draft = app(OpenGameDraft::class)->handle($game, $this->admin->id);
    expect($draft->status)->toBe('ready')
        ->and($draft->html)->toBe($game->current->html)
        ->and(app(OpenGameDraft::class)->handle($game, $this->admin->id)->id)->toBe($draft->id);

    app(ReviseGameDraft::class)->handle($draft, $this->admin->id, 'زوّد بوابة كمان');
    $version = app(PublishGameDraft::class)->handle($draft->refresh(), $this->admin->id);
    expect($version->version)->toBe(2)
        ->and($game->refresh()->current_version_id)->toBe($version->id)
        ->and(GameVersion::query()->where('game_id', $game->id)->count())->toBe(2);

    app(SetGameStatus::class)->handle($game, 'retired');
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    app()->forgetInstance(GameCatalog::class);
    expect(app(GameCatalog::class)->game('bird-gates'))->toBeNull();
    $this->clearTenant();
    app(SetGameStatus::class)->handle($game->refresh(), 'live');
    expect(DB::connection('pgsql_platform')->table('audit_logs')->whereIn('action', ['platform.game_retired', 'platform.game_revived'])->count())->toBe(2);
});

it('runs the studio in the console: the world, trying a game, a new game from a story, and the draft to publish', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');

    $studio = Livewire::test(GameStudio::class)->assertOk()->assertSee('Birds Island')->assertSee('Nest Twins');
    $gameId = Game::query()->where('key', 'bird-twins')->value('id');
    $studio->call('tryGame', $gameId)->assertSet('trying', $gameId);
    expect($studio->instance()->tried['items'])->not->toBeEmpty()
        ->and(collect($studio->instance()->tried['items'])->pluck('kind')->unique()->all())->toBe(['pair']);

    $studio->callAction('new', data: [
        'story' => 'قطار بيعدّي على محطات، وفي كل محطة سؤال، والطفل يختار الإجابة الصح عشان القطار يكمل.',
        'island_id' => $this->birds, 'stages' => ['kids'], 'kinds' => ['choice'],
    ])->assertHasNoActionErrors();
    $draft = GameDraft::query()->latest('created_at')->firstOrFail();
    expect($draft->status)->toBe('ready');

    $page = Livewire::test(GameDraftPage::class, ['draft' => $draft->id])->assertOk()->assertSee('Star Balloons');
    $page->set('changes', 'خلّي الخلفية ليل')->call('send');
    expect($draft->refresh()->rounds)->toBe(2);
    $page->callAction('publish', data: ['key' => 'night-balloons'])->assertHasNoActionErrors();
    expect(Game::query()->where('key', 'night-balloons')->value('status'))->toBe('live');
});

it('keeps the studio to the platform team', function () {
    $academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $owner = User::factory()->withRole(Role::Owner)->create();
    $this->clearTenant();

    $this->get('/console/games')->assertRedirect('/console/login');
    $this->actingAs($owner)->get('/console/games')->assertRedirect('/console/login');
    $this->actingAs($this->admin, 'platform')->get('/console/games')->assertOk();
    expect($academy->id)->not->toBeEmpty();
});

/** Claude's answer as the API sends it (the model's name stays out of the repository). */
function claudeAnswer(string $text, string $stop = 'end_turn'): BetaMessage
{
    return BetaMessage::fromArray([
        'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'the-designer',
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stop, 'stop_sequence' => null,
        'usage' => ['input_tokens' => 1500, 'output_tokens' => 20000, 'cache_read_input_tokens' => 1400, 'cache_creation_input_tokens' => 0],
    ]);
}

it('reads Claude\'s game: the page, the words in both languages, and only the looks and shapes it may use', function () {
    $made = ClaudeGameDesigner::read(claudeAnswer(json_encode([
        'title_ar' => 'قطار الحروف', 'title_en' => 'Letter Train', 'summary_ar' => 'قطار', 'summary_en' => 'A train',
        'how_to_play_ar' => 'اختار', 'how_to_play_en' => 'Pick', 'stages' => ['kids', 'adults'], 'kinds' => ['choice', 'essay'],
        'min_items' => 0, 'max_items' => 40, 'message_ar' => 'جاهزة', 'html' => "  <html><body></body></html>\n",
    ], JSON_UNESCAPED_UNICODE)));

    expect($made->titleEn)->toBe('Letter Train')
        ->and($made->stages)->toBe(['kids'])
        ->and($made->kinds)->toBe(['choice'])
        ->and([$made->minItems, $made->maxItems])->toBe([1, 12])
        ->and($made->html)->toBe('<html><body></body></html>')
        ->and($made->servedModel)->toBe('the-designer')
        ->and($made->usage)->toMatchArray(['input' => 1500, 'output' => 20000, 'cache_read' => 1400]);
});

it('says why Claude\'s answer cannot be used: refused, cut short, unreadable, or no key and model', function () {
    $reason = function (callable $call): string {
        try {
            $call();
        } catch (DesignFailed $e) {
            return $e->reason;
        }

        return 'none';
    };
    $read = fn (string $text, string $stop = 'end_turn') => fn () => ClaudeGameDesigner::read(claudeAnswer($text, $stop));

    expect($reason($read('{}', 'refusal')))->toBe('refused')
        ->and($reason($read('{"html": "<html>', 'max_tokens')))->toBe('too_long')
        ->and($reason($read('not json')))->toBe('unreadable')
        ->and($reason($read('{"title_ar": "x"}')))->toBe('unreadable');

    config(['platform.games.designer.key' => '', 'platform.games.designer.model' => '']);
    $brief = new DesignBrief('قصة', '', ['key' => 'birds', 'name_ar' => 'الطيور', 'name_en' => 'Birds', 'story_ar' => '', 'colors' => [], 'music' => '', 'creatures' => []], ['kids'], ['choice'], [], null, []);
    expect($reason(fn () => (new ClaudeGameDesigner)->design($brief)))->toBe('not_configured');
    config(['platform.games.designer.key' => 'a-key-for-the-test']);
    expect($reason(fn () => (new ClaudeGameDesigner)->design($brief)))->toBe('not_configured');
});
