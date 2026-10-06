<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Filament\NotificationBell;
use Modules\Communication\Filament\Pages\MessageLog;
use Modules\Communication\Filament\Pages\NotificationTemplates;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\Notification;
use Modules\Communication\Models\PushDevice;
use Modules\Communication\Models\TemplateWording;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Tests\TestCase;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));
    Mail::fake();
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['email' => 'owner@noor.test']);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['email' => 'mona@noor.test', 'locale' => 'en']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['name' => 'Sara']);
    app(NotificationsApi::class)->send(new Message('billing.invoice_paid', [$this->guardian->id], ['number' => 'NR-000007', 'total' => '400 SAR'], ['screen' => 'invoice', 'id' => 'x']));
    app(NotificationsApi::class)->send(new Message('scheduling.proposal_submitted', [$this->owner->id], ['teacher' => 'Sara'], ['screen' => 'shift_proposal', 'id' => 'p-1']));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function apiAs(User $user): TestCase
{
    app('auth')->forgetGuards();
    test()->flushHeaders();
    test()->clearTenant();
    $token = test()->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => 'password', 'device_name' => 'phone'])->json('token');

    return test()->withHeader('X-Academy', 'noor')->withToken($token);
}

it('gives the app the inbox, with the unread count, and marks messages read', function () {
    $inbox = apiAs($this->guardian)->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.title', 'Payment received, thank you')
        ->assertJsonPath('data.0.action.screen', 'invoice')
        ->assertJsonPath('data.0.created_at.timezone', 'Africa/Cairo');

    apiAs($this->guardian)->postJson('/api/v1/notifications/read', ['ids' => [$inbox->json('data.0.id')]])->assertOk()->assertJsonPath('data.marked', 1);
    apiAs($this->guardian)->getJson('/api/v1/notifications?unread=1')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.unread', 0);
    apiAs($this->teacher)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
});

it('lets the app read and change the notification choices, and register a push device', function () {
    apiAs($this->guardian)->getJson('/api/v1/notifications/preferences')->assertOk()
        ->assertJsonPath('data.0.key', 'notifications.lessons')->assertJsonPath('data.0.value', 'all')
        ->assertJsonPath('data.4.key', 'notifications.quiet_hours')->assertJsonPath('data.4.value', '22-07');

    apiAs($this->guardian)->putJson('/api/v1/notifications/preferences', ['preferences' => ['notifications.learning' => 'app', 'notifications.quiet_hours' => 'off']])
        ->assertOk()->assertJsonPath('data.1.value', 'app')->assertJsonPath('data.4.value', 'off');
    apiAs($this->guardian)->putJson('/api/v1/notifications/preferences', ['preferences' => ['notifications.learning' => 'loud']])->assertStatus(422);
    apiAs($this->guardian)->putJson('/api/v1/notifications/preferences', ['preferences' => ['live_board.layout' => 'focus']])->assertStatus(422);

    apiAs($this->guardian)->postJson('/api/v1/devices', ['platform' => 'android', 'token' => 'fcm-abc', 'app_version' => '1.2.0'])->assertCreated();
    $this->actAsTenant($this->academy);
    expect(PushDevice::query()->where('user_id', $this->guardian->id)->count())->toBe(1);
    apiAs($this->guardian)->deleteJson('/api/v1/devices', ['token' => 'fcm-abc'])->assertNoContent();
    $this->actAsTenant($this->academy);
    expect(PushDevice::query()->count())->toBe(0);
});

it('opens a notification from the bell where it belongs, and marks it read', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');
    $n = Notification::query()->where('user_id', $this->owner->id)->sole();

    Livewire::test(NotificationBell::class)
        ->assertSee('Sara')
        ->call('open', $n->id)
        ->assertRedirect(ReviewShiftProposal::getUrl(['proposal' => 'p-1'], panel: 'academy'));

    expect($n->refresh()->read_at)->not->toBeNull();
});

it('shows staff the message log and sends a failed message again', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');
    $paid = Notification::query()->where('user_id', $this->guardian->id)->sole();
    $email = $paid->deliveries()->where('channel', 'email')->sole();
    $email->update(['status' => 'failed', 'error' => 'SMTP 550']);

    Livewire::test(MessageLog::class)
        ->assertCanSeeTableRecords([$paid])
        ->filterTable('problem', 'failed')->assertCanSeeTableRecords([$paid])
        ->callTableAction('resend', $paid);

    expect($email->refresh()->status)->toBe('sent');
});

it('opens the message log to supervisors and up, not to the accountant', function () {
    $this->clearTenant();
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin/messages')->assertOk()->assertSee('Payment received, thank you');
});

it('keeps the accountant out of the message log', function () {
    $this->clearTenant();
    $this->actingAs($this->accountant)->get('http://noor.lms.test/admin/messages')->assertForbidden();
});

it('lets the owner reword a template and switch WhatsApp on', function () {
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');

    Livewire::test(NotificationTemplates::class)
        ->assertSee('billing.invoice_paid')
        ->callAction('edit', data: ['title_ar' => 'استلمنا المبلغ', 'body_ar' => 'شكرًا، فاتورة :number اتدفعت.', 'title_en' => 'Payment received, thank you', 'body_en' => 'Invoice :number is paid in full (:total) and the lessons are confirmed.'], arguments: ['key' => 'billing.invoice_paid'])
        ->assertHasNoActionErrors();
    Livewire::test(NotificationTemplates::class)
        ->callAction('edit', data: ['title_ar' => 'x', 'body_ar' => 'Hi :teacher', 'title_en' => 'x', 'body_en' => 'y'], arguments: ['key' => 'billing.invoice_paid'])
        ->assertHasActionErrors();
    Livewire::test(NotificationTemplates::class)
        ->callAction('channels', data: ['whatsapp_enabled' => true, 'push_enabled' => true, 'email_enabled' => true]);

    // Only the Arabic wording changed; English stays the module's own.
    expect(TemplateWording::query()->pluck('locale')->all())->toBe(['ar'])
        ->and(TemplateWording::query()->value('title'))->toBe('استلمنا المبلغ')
        ->and(CommunicationSettings::current()->whatsapp_enabled)->toBeTrue();
});
