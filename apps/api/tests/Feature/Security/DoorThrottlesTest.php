<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * Every door that writes, or is costly to answer, has a tight limit of its
 * own on top of the named ceilings (docs/architecture/03, section 11:
 * "rate limits على كل باب مفتوح"): the journey and its chest, rounds on a
 * station, cheers, the games, messages, support, safeguarding, privacy
 * requests, changing an email or number, and signing in.
 */

const TIGHT_DOORS = [
    'identity.login', 'identity.two-factor', 'identity.contact.send', 'identity.contact.confirm',
    'learning.journey.map', 'learning.journey.chest', 'learning.challenges.claim', 'learning.cheers.send', 'learning.cheers.seen',
    'games.plays.start', 'games.plays.finish', 'games.hatch', 'games.limits', 'games.creatures.update',
    'messaging.conversations.open', 'messaging.messages.send',
    'support.tickets.open', 'support.tickets.reply', 'support.tickets.close', 'support.tickets.reopen',
    'safeguarding.concerns.report',
    'privacy.requests.store', 'privacy.requests.cancel', 'privacy.details.correct',
    'platform.site.requests', 'site.requests',
];

it('gives each newer door a limit of its own', function () {
    $routes = Route::getRoutes();
    $byUri = collect($routes->getRoutes())->keyBy(fn ($r) => implode('|', $r->methods()).' '.$r->uri());
    // Doors named elsewhere under other names: find them by address.
    $doors = [
        'messaging.conversations.open' => 'POST api/v1/messages/conversations',
        'messaging.messages.send' => 'POST api/v1/messages/conversations/{conversation}/messages',
        'support.tickets.open' => 'POST api/v1/support/tickets',
        'support.tickets.reply' => 'POST api/v1/support/tickets/{ticket}/messages',
        'support.tickets.close' => 'POST api/v1/support/tickets/{ticket}/close',
        'support.tickets.reopen' => 'POST api/v1/support/tickets/{ticket}/reopen',
        'safeguarding.concerns.report' => 'POST api/v1/safeguarding/concerns',
        'privacy.requests.store' => 'POST api/v1/me/data-requests',
        'privacy.requests.cancel' => 'POST api/v1/me/data-requests/{id}/cancel',
        'privacy.details.correct' => 'PATCH api/v1/me/details/{subject}',
    ];

    $loose = [];
    foreach (TIGHT_DOORS as $door) {
        $route = isset($doors[$door]) ? $byUri->get($doors[$door]) : $routes->getByName($door);
        expect($route)->not->toBeNull("the door {$door}");
        $limits = array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && preg_match('/^throttle:\d+,\d+$/', $m));
        if (count($limits) !== 1) {
            $loose[] = $door.' ('.count($limits).')';
        }
    }

    expect($loose)->toBe([]);
});

it('stops one address trying many accounts, beyond the limit for each account', function () {
    $this->actAsTenant($this->createAcademy(['slug' => 'huda']));
    User::factory()->withRole(Role::Guardian)->create(['email' => 'known@huda.test']);
    $this->clearTenant();

    $try = fn (int $i) => $this->postJson('http://huda.lms.test/api/v1/auth/login', ['login' => "guess{$i}@huda.test", 'password' => 'guess', 'device_name' => 'phone']);
    for ($i = 0; $i < 30; $i++) {
        $try($i)->assertStatus(422);
    }
    $try(31)->assertStatus(429);
    // Another address still signs in.
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->postJson('http://huda.lms.test/api/v1/auth/login', ['login' => 'known@huda.test', 'password' => 'guess', 'device_name' => 'phone'])->assertStatus(422);
});
