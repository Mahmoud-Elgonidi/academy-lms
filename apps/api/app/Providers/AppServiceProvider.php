<?php

namespace App\Providers;

use App\Http\Middleware\OpenFirstAllowedScreen;
use App\OpenApi\ApiDocumentation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Times are immutable everywhere; the database and PHP both run in UTC.
        Date::use(CarbonImmutable::class);

        // Catch lazy loading, silently discarded attributes and missing attributes in dev and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        ApiDocumentation::configure();

        // Signing in on a panel marks the landing: an academy not ready for families opens its owner and admins on "Start here".
        Event::listen(Login::class, [OpenFirstAllowedScreen::class, 'signedIn']);

        // Uploads through Livewire (the panels' file fields) may be as big as the server takes (50 MB, infra/deploy/php.ini);
        // each field still sets its own types and size (a lesson's video 50 MB, a PDF 20 MB). Set after Livewire merged its defaults.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:51200']]);

        // Behind a server's web server: trust the proxies it names, so links stay on https.
        $proxies = config('app.trusted_proxies');
        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }
}
