<?php

namespace Modules\Site\Filament\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Modules\SharedKernel\Files\CleanFile;
use Modules\Site\Actions\UploadSiteMedia;
use Modules\Site\Models\SiteMedia;

/**
 * A block's picture or video in the page builder (site-builder.md, 4.2): the
 * chosen file's thumbnail with its size, "choose from the library" (a grid
 * of the academy's files of that kind), "upload" (the image is shrunk in the
 * browser first, as the favicon box does) and "remove". The state is the
 * file's id only; the page builder saves the draft after each change.
 */
final class MediaPicker extends Field
{
    protected string $view = 'site::filament.media-picker';

    protected string $kind = 'image';

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerActions([
            fn (self $component): Action => $component->chooseAction(),
            fn (self $component): Action => $component->uploadAction(),
            fn (self $component): Action => $component->clearAction(),
        ]);
    }

    public function kind(string $kind): static
    {
        $this->kind = $kind === 'video' ? 'video' : 'image';

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /** @return array{id: string, url: string, name: string, kind: string, mime: string, meta: string}|null */
    public function getChosen(): ?array
    {
        $id = $this->getState();
        $media = is_string($id) && Str::isUuid($id) ? SiteMedia::query()->find($id) : null;

        return $media === null ? null : self::describe($media);
    }

    /** @return array{id: string, url: string, name: string, kind: string, mime: string, meta: string} */
    public static function describe(SiteMedia $media): array
    {
        $meta = [];
        if ($media->width !== null) {
            $meta[] = $media->width.'×'.$media->height;
        }
        $meta[] = strtoupper(SiteMedia::IMAGES[$media->mime] ?? SiteMedia::VIDEOS[$media->mime] ?? '');
        $meta[] = Number::fileSize($media->bytes, 1);

        return ['id' => $media->id, 'url' => $media->localUrl(), 'name' => $media->name, 'kind' => $media->kind, 'mime' => $media->mime, 'meta' => implode(' · ', $meta)];
    }

    public function chooseAction(): Action
    {
        return Action::make('choose')
            ->label(fn () => $this->getState() ? __('site::builder.media.change') : __('site::builder.media.choose'))
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('site::builder.media.library'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('site::builder.media.pick'))
            ->fillForm(fn () => ['media' => $this->getState()])
            ->schema([
                ViewField::make('media')->hiddenLabel()->view('site::filament.media-grid')->viewData(fn () => [
                    'files' => self::library($this->kind),
                    'kind' => $this->kind,
                ]),
            ])
            ->action(function (array $data): void {
                $id = is_string($data['media'] ?? null) && Str::isUuid($data['media']) ? $data['media'] : null;
                $this->state($id);
                $this->callAfterStateUpdated();
            });
    }

    public function uploadAction(): Action
    {
        $image = $this->kind === 'image';
        $upload = FileUpload::make('file')
            ->label(__('site::builder.media.file'))
            ->helperText($image ? __('site::builder.media.image_hint') : __('site::builder.media.video_hint'))
            ->required()
            ->storeFiles(false)
            ->acceptedFileTypes(array_keys($image ? SiteMedia::IMAGES : SiteMedia::VIDEOS))
            ->maxSize($image ? SiteMedia::MAX_IMAGE_KB : SiteMedia::MAX_VIDEO_KB)
            ->rules([new CleanFile]);
        if ($image) {
            $upload->image()->imageResizeMode('contain')->imageResizeTargetWidth('2560')->imageResizeTargetHeight('2560')->imageResizeUpscale(false);
        }

        return Action::make('upload')
            ->label(__('site::builder.media.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('site::builder.media.upload_title'))
            ->modalWidth(Width::Large)
            ->schema(array_values(array_filter([
                $upload,
                $image ? Grid::make(2)->schema([
                    TextInput::make('alt.ar')->label(__('site::builder.media.alt'))->hint('ع')->maxLength(200),
                    TextInput::make('alt.en')->label(__('site::builder.media.alt'))->hint('EN')->maxLength(200)->extraInputAttributes(['dir' => 'ltr']),
                ]) : null,
            ])))
            ->action(function (array $data): void {
                $media = app(UploadSiteMedia::class)->handle($data['file'], is_array($data['alt'] ?? null) ? $data['alt'] : []);
                $this->state($media->id);
                $this->callAfterStateUpdated();
                Notification::make()->success()->title(__('site::builder.media.uploaded'))->send();
            });
    }

    public function clearAction(): Action
    {
        return Action::make('clear')
            ->label(__('site::builder.media.remove'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->size('sm')
            ->link()
            ->visible(fn () => filled($this->getState()))
            ->action(function (): void {
                $this->state(null);
                $this->callAfterStateUpdated();
            });
    }

    /** @return list<array{id: string, url: string, name: string, kind: string, mime: string, meta: string}> the library's files of a kind, newest first */
    public static function library(string $kind): array
    {
        $out = [];
        foreach (SiteMedia::query()->where('kind', $kind)->latest()->limit(SiteMedia::MAX_FILES)->get() as $media) {
            $out[] = self::describe($media);
        }

        return $out;
    }
}
