<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Ai\AiTestCase;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Files\CleanFile;

/**
 * An owner adds or edits an input for the comparison tool (console › AI
 * routing › Compare): for text a system prompt, a request and the answer's
 * schema (a JSON object; else a single "answer" string); for speech a
 * recording (an audio type by its bytes, scanned, on the private disk under
 * platform/ai-tests/). Written by hand or from consented samples, never a
 * learner's data: the form says so. Audited with the title only.
 */
final class SaveAiTestCase
{
    public const AUDIO = ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'video/webm'];

    public const MAX_KB = 20480;

    /** The schema a case without one is asked for. */
    public const PLAIN = ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']], 'required' => ['answer'], 'additionalProperties' => false];

    public function __construct(
        private readonly PlatformAudit $audit,
        private readonly Capabilities $capabilities,
    ) {}

    /** @param  array<string, mixed>  $data  capability, kind, title, system, prompt, schema (JSON text), language, notes */
    public function handle(PlatformAdmin $by, ?string $caseId, array $data, ?UploadedFile $audio = null): AiTestCase
    {
        OwnersOnly::check($by);
        $case = $caseId !== null ? AiTestCase::query()->findOrFail($caseId) : new AiTestCase;
        $valid = Validator::make($data + ['audio' => $audio], [
            'capability' => ['required', 'string'],
            'kind' => ['required', Rule::in(['text', 'speech'])],
            'title' => ['required', 'string', 'max:120'],
            'system' => ['nullable', 'string', 'max:20000'],
            'prompt' => ['nullable', 'required_if:kind,text', 'string', 'max:20000'],
            'schema' => ['nullable', 'string', 'max:20000', 'json'],
            'language' => ['nullable', Rule::in(['ara', 'eng'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'audio' => [Rule::requiredIf(($data['kind'] ?? null) === 'speech' && $case->audio_path === null), 'nullable', 'file', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', self::AUDIO), new CleanFile],
        ], [], __('platform::routes.cases.fields'))->validate();

        $known = $this->capabilities->get((string) $valid['capability']);
        if ($known === null || ! in_array($valid['kind'], $known->kinds, true)) {
            throw ValidationException::withMessages(['capability' => __('platform::routes.errors.capability')]);
        }
        $schema = filled($valid['schema'] ?? null) ? json_decode((string) $valid['schema'], true) : null;
        if ($schema !== null && (! is_array($schema) || ($schema['type'] ?? null) !== 'object')) {
            throw ValidationException::withMessages(['schema' => __('platform::routes.errors.schema')]);
        }

        $path = null;
        if ($audio !== null) {
            $path = $audio->storeAs(AiTestCase::FOLDER, Str::uuid().'.'.($audio->guessExtension() ?: 'bin'), AiTestCase::DISK);
        }

        return DB::connection('pgsql_platform')->transaction(function () use ($by, $case, $valid, $schema, $audio, $path) {
            $old = $case->audio_path;
            $case->fill([
                'capability' => $valid['capability'],
                'kind' => $valid['kind'],
                'title' => trim((string) $valid['title']),
                'system' => $valid['kind'] === 'text' ? ($valid['system'] ?? null) : null,
                'prompt' => $valid['kind'] === 'text' ? ($valid['prompt'] ?? null) : null,
                'schema' => $valid['kind'] === 'text' ? $schema : null,
                'language' => $valid['language'] ?? 'ara',
                'notes' => $valid['notes'] ?? null,
            ]);
            if ($valid['kind'] === 'speech' && $path !== null) {
                $case->audio_path = $path;
                $case->audio_mime = (string) $audio->getMimeType();
            } elseif ($valid['kind'] === 'text') {
                $case->audio_path = $case->audio_mime = null;
            }
            $case->created_by ??= $by->id;
            $case->save();
            if ($old !== null && $old !== $case->audio_path) {
                DB::afterCommit(fn () => Storage::disk(AiTestCase::DISK)->delete($old));
            }
            $this->audit->record(null, 'platform.ai_test_case_saved', 'ai_test_case', $case->id, ['title' => $case->title, 'capability' => $case->capability]);

            return $case;
        });
    }

    public function delete(PlatformAdmin $by, string $caseId): void
    {
        OwnersOnly::check($by);
        $case = AiTestCase::query()->findOrFail($caseId);
        $path = $case->audio_path;
        $case->delete();
        if ($path !== null) {
            Storage::disk(AiTestCase::DISK)->delete($path);
        }
        $this->audit->record(null, 'platform.ai_test_case_removed', 'ai_test_case', $caseId, ['title' => $case->title, 'capability' => $case->capability]);
    }
}
