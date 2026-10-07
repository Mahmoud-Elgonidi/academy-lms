<?php

namespace Modules\Import\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Import\Actions\CheckImport;
use Modules\Import\Actions\ImportFiles;
use Modules\Import\Actions\MapImportColumns;
use Modules\Import\Actions\RunImport;
use Modules\Import\Actions\StageImport;
use Modules\Import\Importers\Importers;
use Modules\Import\Models\ImportRow;
use Modules\Import\Models\ImportRun;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Bringing an academy's sheets in (docs/architecture/01: "استيراد من
 * CSV/Excel"), in four steps: the file, its columns, a dry run that says
 * what each row will do, and the import, with the rows that did not go in
 * to fix and bring again.
 */
final class ImportData extends Page
{
    use WithFileUploads;

    public const SHOWN = 300;

    protected string $view = 'import::filament.import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 79;

    protected static ?string $slug = 'settings/import';

    #[Url]
    public ?string $run = null;

    public string $kind = 'teachers';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var array<string, int|string|null> */
    public array $mapping = [];

    public string $callingCode = '';

    public bool $invite = true;

    public string $show = 'all';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ImportData->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('import::import.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('import::import.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('import::import.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('import::import.subtitle');
    }

    public function mount(): void
    {
        $this->load();
    }

    #[Computed]
    public function current(): ?ImportRun
    {
        return $this->run ? ImportRun::query()->find($this->run) : null;
    }

    public function step(): string
    {
        return match ($this->current?->status) {
            null => 'file',
            'staged' => 'columns',
            'checked' => 'review',
            default => 'done',
        };
    }

    /** @return list<array{key: string, label: string, hint: ?string, required: bool}> */
    public function fields(): array
    {
        $kind = $this->current?->kind ?? $this->kind;

        return array_map(fn ($f) => [
            'key' => $f->key,
            'label' => __("import::fields.{$kind}.{$f->key}"),
            'hint' => trans()->has("import::fields.hints.{$kind}.{$f->key}") ? __("import::fields.hints.{$kind}.{$f->key}") : null,
            'required' => $f->required,
        ], Importers::for($kind)->fields());
    }

    /** @return array<int, string> column index => "title — e.g. first value" */
    #[Computed]
    public function columnOptions(): array
    {
        $run = $this->current;
        if ($run === null) {
            return [];
        }
        $first = $run->rows()->first()?->cells ?? [];

        return collect($run->headers)->mapWithKeys(fn (string $title, int $i) => [$i => trim(($title !== '' ? $title : '#'.($i + 1)).(($first[$i] ?? '') !== '' ? ' — '.mb_strimwidth((string) $first[$i], 0, 28, '…') : ''))])->all();
    }

    /** @return list<array{line: int, label: string, status: string, message: ?string}> */
    #[Computed]
    public function rows(): array
    {
        $run = $this->current;
        if ($run === null || $run->status === 'staged') {
            return [];
        }
        $labelField = Importers::for($run->kind)->fields()[0]->key;

        return $run->rows()->when($this->show !== 'all', fn ($q) => $q->where('status', $this->show))->limit(self::SHOWN)->get()
            ->map(fn (ImportRow $r) => [
                'line' => $r->line,
                'label' => $r->values($run->mapping)[$labelField] ?? '',
                'status' => $r->status,
                'message' => $r->message,
            ])->all();
    }

    #[Computed]
    public function shownTotal(): int
    {
        return $this->current?->rows()->when($this->show !== 'all', fn ($q) => $q->where('status', $this->show))->count() ?? 0;
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function history(): array
    {
        return ImportRun::query()->latest()->limit(8)->get()->map(fn (ImportRun $r) => [
            'id' => $r->id,
            'kind' => __('import::import.kinds.'.$r->kind.'.title'),
            'file' => $r->file_name,
            'status' => $r->status,
            'counts' => $r->counts,
            'by' => __('import::import.history.by', [
                'date' => $r->created_at->locale(app()->getLocale())->translatedFormat('j M Y'),
                'name' => DB::table('users')->where('id', $r->created_by)->value('name'),
            ]),
        ])->all();
    }

    public function pick(string $kind): void
    {
        if (isset(Importers::KINDS[$kind])) {
            $this->kind = $kind;
        }
    }

    public function stage(): void
    {
        $this->attempt(function () {
            if (! $this->file instanceof TemporaryUploadedFile) {
                throw ValidationException::withMessages(['file' => __('import::import.errors.no_file')]);
            }
            $run = app(StageImport::class)->handle($this->kind, $this->file->getRealPath(), $this->file->getClientOriginalName(), (string) auth()->id());
            $this->file = null;
            $this->open($run->id);
        });
    }

    public function check(): void
    {
        $this->attempt(function () {
            $run = app(MapImportColumns::class)->handle($this->current, $this->mapping, ['calling_code' => $this->callingCode, 'invite' => $this->invite]);
            app(CheckImport::class)->handle($run, (string) auth()->id());
            $this->open($run->id);
        });
    }

    public function editColumns(): void
    {
        $this->attempt(function () {
            app(MapImportColumns::class)->handle($this->current, $this->mapping, ['calling_code' => $this->callingCode, 'invite' => $this->invite]);
            $this->open($this->run);
        });
    }

    public function import(): void
    {
        $this->attempt(function () {
            app(RunImport::class)->handle($this->current, (string) auth()->id());
            $this->open($this->run);
        });
    }

    public function open(?string $runId): void
    {
        $this->run = $runId;
        $this->show = 'all';
        unset($this->current, $this->rows, $this->columnOptions, $this->shownTotal, $this->history);
        $this->load();
    }

    public function filter(string $status): void
    {
        $this->show = $status;
        unset($this->rows, $this->shownTotal);
    }

    public function template(string $kind): BinaryFileResponse
    {
        abort_unless(isset(Importers::KINDS[$kind]), 404);
        $files = app(ImportFiles::class);

        return response()->download($files->template($kind), $files->templateName($kind))->deleteFileAfterSend();
    }

    public function problems(): ?BinaryFileResponse
    {
        $files = app(ImportFiles::class);
        $path = $this->current ? $files->problems($this->current) : null;

        return $path ? response()->download($path, $files->problemsName($this->current))->deleteFileAfterSend() : null;
    }

    private function load(): void
    {
        $run = $this->current;
        if ($run === null) {
            $this->run = null;

            return;
        }
        $this->kind = $run->kind;
        $this->mapping = array_map(fn ($v) => $v === null ? '' : (string) $v, $run->mapping);
        $this->callingCode = (string) $run->option('calling_code', '');
        $this->invite = (bool) $run->option('invite', true);
    }

    private function attempt(callable $change): void
    {
        try {
            $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
        }
    }
}
