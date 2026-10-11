<?php

use Modules\Platform\Models\PlatformAdmin;

/*
 * The console on its own host (docs/architecture/03, section 11): with
 * PLATFORM_CONSOLE_DOMAIN set, as on a server, its pages, its sign-in and
 * its live channel answer on that host alone, and an academy's host (or the
 * platform's) has no console at all. Without the setting (local
 * development) it answers at /console on any host, as before.
 */

beforeEach(function () {
    $this->createAcademy(['slug' => 'noor']);
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->admin->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
});

it('answers only on its own host once it has one', function () {
    config(['platform.console_domain' => 'console.lms.test']);

    $this->get('http://console.lms.test/console/login')->assertOk();
    foreach (['http://noor.lms.test', 'http://lms.test'] as $host) {
        $this->get($host.'/console/login')->assertNotFound();
        $this->get($host.'/console')->assertNotFound();
        $this->post($host.'/console/logout')->assertNotFound();
    }

    // Signed in, the team still finds no console on an academy's host, and no live channel there.
    $this->actingAs($this->admin, 'platform')->get('http://console.lms.test/console/academies')->assertOk();
    $this->actingAs($this->admin, 'platform')->get('http://noor.lms.test/console/academies')->assertNotFound();
    $this->actingAs($this->admin, 'platform')->post('http://noor.lms.test/console/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-game-studio'])
        ->assertNotFound();
});

it('keeps answering at /console on any host without a host of its own', function () {
    config(['platform.console_domain' => null]);

    $this->get('http://noor.lms.test/console/login')->assertOk();
    $this->get('http://lms.test/console/login')->assertOk();
    $this->actingAs($this->admin, 'platform')->get('http://lms.test/console/academies')->assertOk();
});
