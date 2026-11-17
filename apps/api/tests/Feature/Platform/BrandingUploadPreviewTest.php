<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\Platform\Filament\Pages\BrandingSettings;

/*
 * The saved logo and icon in Settings › Branding: the upload box fetches the
 * file to show it, and the back office runs on each academy's host, so the
 * address it gets is on the page's own host (/storage/...), never APP_URL's
 * (the browser refuses that fetch, and the box spins forever).
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->actingAs(User::factory()->withRole(Role::Owner)->create(['locale' => 'ar']));
    Filament::setCurrentPanel('academy');
    config(['app.url' => 'https://lms.example.test']);
    Storage::fake('public', ['url' => 'https://lms.example.test/storage']);
});

it('gives the upload box the saved logo and icon on the page\'s own host', function () {
    $directory = UpdateAcademyIdentity::directory($this->academy->id);
    Storage::disk('public')->put("{$directory}/logo.png", 'logo');
    Storage::disk('public')->put("{$directory}/icon.png", 'icon');
    app(UpdateAcademyIdentity::class)->handle('نور', 'Noor', "{$directory}/logo.png", "{$directory}/icon.png");

    $page = Livewire::test(BrandingSettings::class)->instance();
    $field = fn (string $name): FileUpload => collect($page->form->getFlatComponents())
        ->first(fn ($c) => $c instanceof FileUpload && $c->getName() === $name);

    expect(collect($field('logo')->getUploadedFiles())->pluck('url')->all())->toBe(["/storage/{$directory}/logo.png"])
        ->and(collect($field('favicon')->getUploadedFiles())->pluck('url')->all())->toBe(["/storage/{$directory}/icon.png"]);
});
