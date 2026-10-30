<?php

namespace Modules\Platform\Providers;

use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Actions\SavePlatformAdminTimezone;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\AiSlots;
use Modules\Platform\Ai\ClaudeLanguageModel;
use Modules\Platform\Ai\DemoLanguageModel;
use Modules\Platform\Ai\DemoSpeechToText;
use Modules\Platform\Ai\ElevenLabsSpeechToText;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Branding\BrandingService;
use Modules\Platform\Commands\CreatePlatformAdmin;
use Modules\Platform\Commands\HealthCommand;
use Modules\Platform\Commands\MailTestCommand;
use Modules\Platform\Commands\MeasureUsageCommand;
use Modules\Platform\Commands\ServicesCommand;
use Modules\Platform\Commands\ShowPlatformCode;
use Modules\Platform\Commands\SweepSignupRequests;
use Modules\Platform\Console\ConsoleService;
use Modules\Platform\Contracts\AccountDirectory;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Directory\EloquentTenantDirectory;
use Modules\Platform\Directory\PlatformAccountDirectory;
use Modules\Platform\Games\Designer\ClaudeGameDesigner;
use Modules\Platform\Games\Designer\DemoGameDesigner;
use Modules\Platform\Games\Designer\GameDesigner;
use Modules\Platform\Games\PlatformGameCatalog;
use Modules\Platform\Health\AssistBeat;
use Modules\Platform\Health\WorkerBeat;
use Modules\Platform\Http\Controllers\GamePageController;
use Modules\Platform\Http\Controllers\HealthController;
use Modules\Platform\Http\Middleware\ConsoleHost;
use Modules\Platform\Listeners\RecordConsoleSignIn;
use Modules\Platform\Live\GameStudioChanged;
use Modules\Platform\Models\AcademyBranding;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\Platform\PlatformService;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Setup\BrandingStep;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\LanguageModel;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Health\Heartbeats;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Realtime\LiveSignals;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Tenancy\TenantResolver;
use Modules\SharedKernel\Time\OwnTimezones;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/platform.php', 'platform');

        $this->app->singleton(TenantDirectory::class, EloquentTenantDirectory::class);
        $this->app->alias(TenantDirectory::class, TenantResolver::class);
        $this->app->singleton(PlatformApi::class, PlatformService::class);
        $this->app->singleton(ConsoleApi::class, ConsoleService::class);
        $this->app->singleton(AccountDirectory::class, PlatformAccountDirectory::class);

        // Scoped: built once per request for the request's academy.
        $this->app->scoped(BrandingService::class);
        $this->app->alias(BrandingService::class, BrandingApi::class);

        // The academies' SaaS plans, for every module through SharedKernel's AcademyPlan.
        $this->app->scoped(PlatformAcademyPlan::class);
        $this->app->alias(PlatformAcademyPlan::class, AcademyPlan::class);
        $this->app->scoped(PlanUsage::class);

        // The games: the catalog the academies see, and the studio's designer (Claude).
        $this->app->scoped(PlatformGameCatalog::class);
        $this->app->alias(PlatformGameCatalog::class, GameCatalog::class);
        $this->app->bind(GameDesigner::class, fn () => config('platform.games.designer.driver') === 'demo' && ! $this->app->isProduction()
            ? new DemoGameDesigner : new ClaudeGameDesigner);

        // The academies' AI helpers (Settings › AI): the chosen provider for words (Claude, GPT, Gemini, Ollama), ElevenLabs for recordings, fixed answers for the demo.
        $this->app->singleton(AiSlots::class);
        $this->app->singleton(PlatformSettings::class);
        $this->app->scoped(PlatformAcademyAi::class);
        $this->app->alias(PlatformAcademyAi::class, AcademyAi::class);
        $this->app->bind(LanguageModel::class, fn ($app) => PlatformAcademyAi::demo() ? new DemoLanguageModel
            : $app->make(AiProvider::current()?->languageModel() ?? ClaudeLanguageModel::class));
        $this->app->bind(SpeechToText::class, fn ($app) => PlatformAcademyAi::demo() ? new DemoSpeechToText : $app->make(ElevenLabsSpeechToText::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'platform');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'platform');
        // The new academy's checklist: its name and logo.
        $this->app->make(SetupChecklist::class)->add(BrandingStep::class);
        // The console's zone chip changes the platform team's own zone.
        $this->app->make(OwnTimezones::class)->add(PlatformAdmin::class, SavePlatformAdminTimezone::class);
        Event::listen(Login::class, RecordConsoleSignIn::class);
        // The platform's settings from the console over the server's .env: at each job and command too (requests:
        // ApplyPlatformSettings). Never here in boot, so caching the config never writes them out.
        Event::listen(JobProcessing::class, fn () => $this->app->make(PlatformSettings::class)->apply());
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! PlatformSettings::leavesAlone((string) $event->command)) {
                $this->app->make(PlatformSettings::class)->apply();
            }
        });
        if ($this->app->runningInConsole()) {
            $this->commands([CreatePlatformAdmin::class, ShowPlatformCode::class, MeasureUsageCommand::class, HealthCommand::class, MailTestCommand::class, ServicesCommand::class, SweepSignupRequests::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('platform:usage')->dailyAt('02:10')->withoutOverlapping()->onOneServer();
            // The stack's heartbeats (the scheduler's own, and one through the queue to the worker) and the alerts.
            $schedule->call(fn () => Heartbeats::beat('scheduler'))->everyMinute()->name('health:scheduler');
            $schedule->call(fn () => WorkerBeat::dispatch(now()->getTimestamp())->onQueue('default'))->everyMinute()->name('health:worker');
            $schedule->call(fn () => AssistBeat::dispatch(now()->getTimestamp()))->everyMinute()->name('health:assist');
            $schedule->command('platform:health --alert')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            // Each outside service tried once a day, so a credit run out or a key refused is heard on a quiet day too.
            $schedule->command('platform:services')->dailyAt('06:40')->withoutOverlapping()->onOneServer();
            $schedule->command('platform:site-sweep')->dailyAt('03:20')->onOneServer();
        });

        // For an uptime monitor (HEALTH_TOKEN): statuses only, no session, no academy.
        Route::middleware('throttle:30,1')->get('health', HealthController::class)->name('platform.health');

        // The plan's storage counts the academy's logo and icon too.
        $this->app->make(UsageMeters::class)->add(Limit::StorageMb, function (): int {
            $branding = AcademyBranding::query()->first();
            $disk = Storage::disk('public');

            return array_sum(array_map(fn (?string $path) => $path !== null && $disk->exists($path) ? $disk->size($path) : 0, [$branding?->logo_path, $branding?->favicon_path]));
        });

        // On any panel screen, something the plan stops says why instead of failing the page.
        \Livewire\on('exception', function ($component, $e, $stopPropagation): void {
            if ($e instanceof PlanLimitReached || $e instanceof FeatureNotInPlan) {
                Notification::make()->danger()->title(__('platform::plans.stopped'))->body($e->getMessage())->persistent()->send();
                $stopPropagation();
            }
        });

        // A live game's page (sandboxed, its own policy: Games\GamePage), and the studio's live screens.
        Route::middleware('throttle:240,1')->get('games/{version}', GamePageController::class)->whereUuid('version')->name('games.play');
        Route::middleware([ConsoleHost::class, 'web', 'auth:platform', 'throttle:120,1'])->post('console/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
            ->name('console.broadcasting.auth');
        Broadcast::channel(GameStudioChanged::CHANNEL, fn ($user) => $user instanceof PlatformAdmin && $user->is_active, ['guards' => ['platform']]);
        $this->app->make(LiveSignals::class)->on('game-studio', fn (string $draftId) => new GameStudioChanged($draftId));

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale'])
            ->group(__DIR__.'/../../routes/api.php');
        Route::prefix('api/v1')
            ->middleware(['api', 'locale'])
            ->group(__DIR__.'/../../routes/api_site.php');
    }
}
