<?php

namespace Modules\Games\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Games\Listeners\HatchOnMastery;
use Modules\Games\Privacy\GamesData;
use Modules\Games\Queries\GamesForJourney;
use Modules\Games\Queries\PracticePool;
use Modules\Learning\Contracts\Events\NodeMastered;
use Modules\Learning\Contracts\JourneyGames;
use Modules\SharedKernel\Privacy\PersonalData;

/**
 * The games in an academy (docs/architecture/02, section 18): its learners
 * play the platform's games (SharedKernel's GameCatalog) on their own
 * practice items (Curriculum), what to practise comes from Learning
 * (homework, the last lesson, reviews), and every round goes back to
 * Learning as practice. Sits on top: nothing depends on it.
 */
final class GamesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PracticePool::class);
        // The games on the learner's journey (Learning's contract).
        $this->app->singleton(JourneyGames::class, GamesForJourney::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'games');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'games');
        $this->app->make(PersonalData::class)->add(GamesData::class);

        Route::prefix('api/v1')
            ->middleware(['api', 'tenant', 'locale', 'timezone'])
            ->group(__DIR__.'/../../routes/api.php');

        // A lesson the teacher marks mastered hatches the learner's next creature.
        Event::listen(NodeMastered::class, HatchOnMastery::class);
    }
}
