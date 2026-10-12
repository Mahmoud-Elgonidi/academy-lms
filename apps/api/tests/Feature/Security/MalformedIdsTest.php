<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;

/*
 * Something that is not an id where an id goes (a cut link, a typo in the
 * portal's address, a probe) names a record that is not there: every API
 * route answers it as such, never with a server error from the database
 * refusing the text as a uuid (docs/architecture/03, section 11).
 */
it('answers a route given something that is not an id as a record that is not there, never 5xx', function () {
    $academy = $this->actAsTenant($this->createAcademy(['slug' => 'huda']));
    $people = [
        'teacher' => User::factory()->withRole(Role::Teacher)->create(),
        'guardian' => User::factory()->withRole(Role::Guardian)->create(),
        'student' => User::factory()->withRole(Role::Student)->create(),
    ];
    app(PeopleApi::class)->linkGuardian($people['guardian']->id, $people['student']->id);
    $this->clearTenant();

    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && str_contains($r->uri(), '{'));
    expect($routes)->not->toBeEmpty();

    $failed = [];
    foreach ($people as $who => $user) {
        foreach ($routes as $route) {
            $uri = preg_replace('/\{[^}]+\}/', 'not-an-id', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $status = $this->actingAs($user, 'sanctum')
                    ->withHeaders(['X-Academy' => $academy->slug, 'Idempotency-Key' => "{$who}-{$method}-{$uri}"])
                    ->json($method, "/{$uri}", ['body' => 'checking', 'reason' => 'illness'])->getStatusCode();
                if ($status >= 500) {
                    $failed[] = "{$who}: {$method} /{$uri} ({$status})";
                }
            }
        }
    }

    expect($failed)->toBe([]);
});
