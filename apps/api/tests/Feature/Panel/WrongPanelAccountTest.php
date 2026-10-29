<?php

use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * The panels share one session on the academy's address: someone signed in to
 * one panel who opens another their account does not take reads who they are
 * signed in as, with a way to sign out and one to their own area, instead of a
 * bare 403 (App\Http\Middleware\AuthenticateForPanel).
 */

beforeEach(function () {
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->withoutTwoFactor()->create(['name' => 'هالة منصور'])->fresh();
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['name' => 'أ. خالد يوسف'])->fresh();
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['name' => 'عمرو سالم'])->fresh();
    $this->clearTenant();
});

it('tells a teacher opening the back office who they are signed in as, and the ways out', function () {
    $page = $this->actingAs($this->teacher)->get('http://noor.lms.test/admin')->assertForbidden();

    $page->assertSee(__('shared::panels.wrong.title', ['area' => __('shared::panels.areas.academy')]))
        ->assertSee('أ. خالد يوسف')
        ->assertSee('action="http://noor.lms.test/admin/logout"', false)
        ->assertSee('href="http://noor.lms.test/teach"', false)
        ->assertSee(__('shared::panels.wrong.sign_out'));
});

it('does the same for a guardian on the teacher\'s area and for staff on the family\'s', function () {
    $this->actingAs($this->guardian)->get('http://noor.lms.test/teach')->assertForbidden()
        ->assertSee('عمرو سالم')->assertSee('href="http://noor.lms.test/my"', false);

    $this->actingAs($this->owner)->get('http://noor.lms.test/my')->assertForbidden()
        ->assertSee('هالة منصور')->assertSee('href="http://noor.lms.test/admin"', false);
});

it('leaves the way in as it was without a session, and lets the right account in', function () {
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/login');

    $this->actingAs($this->teacher)->get('http://noor.lms.test/teach')->assertDontSee(__('shared::panels.wrong.sign_out'));
});

it('signs out from the page and opens the panel\'s sign-in again', function () {
    $this->actingAs($this->teacher)->post('http://noor.lms.test/admin/logout')->assertRedirect();
    $this->assertGuest();
    $this->get('http://noor.lms.test/admin/login')->assertOk();
});
