<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Import\Actions\StageImport;
use Modules\Platform\Health\SystemHealth;
use Modules\SharedKernel\Files\ClamAvScanner;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Files\NullUploadScanner;
use Modules\SharedKernel\Files\ScannerHealth;
use Modules\SharedKernel\Files\ScanVerdict;
use Modules\SharedKernel\Files\UploadScanner;

/*
 * Malware scanning of uploads (docs/architecture/03, section 11): every
 * upload carries the CleanFile rule, which asks the server's scanner (clamd
 * over its own protocol when UPLOAD_SCANNER=clamav, nothing otherwise). An
 * infected file is refused; with a scanner that does not answer, the upload
 * is refused too unless UPLOAD_SCAN_REQUIRED is off. The health page says
 * whether clamd answers.
 */

/** The EICAR test string every scanner knows, split so no scanner flags this file. */
function eicar(): string
{
    return 'X5O!P%@AP[4\PZX54(P^)7CC)7}$'.'EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
}

/** A scanner that finds EICAR, or answers nothing at all. */
function fakeScanner(bool $answers = true): UploadScanner
{
    return new class($answers) implements UploadScanner
    {
        public function __construct(private bool $answers) {}

        public function scan(string $path): ScanVerdict
        {
            if (! $this->answers) {
                return ScanVerdict::unavailable('connection refused');
            }

            return str_contains((string) file_get_contents($path), eicar()) ? ScanVerdict::infected('Eicar-Signature') : ScanVerdict::clean();
        }

        public function ping(): ?string
        {
            return $this->answers ? null : 'connection refused';
        }

        public function version(): ?string
        {
            return 'ClamAV 1.4.1/27000';
        }
    };
}

function tempFile(string $content, string $ext = 'txt'): string
{
    $path = tempnam(sys_get_temp_dir(), 'scan-').'.'.$ext;
    file_put_contents($path, $content);

    return $path;
}

it('speaks clamd\'s INSTREAM protocol: the file in length-prefixed chunks, then an empty one', function () {
    $verdict = function (string $reply, string $content) use (&$sent) {
        [$ours, $clamd] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($clamd, $reply);
        $scanner = new ClamAvScanner('clamav', 3310, 5, fn () => $ours);
        $result = $scanner->scan(tempFile($content));
        stream_set_blocking($clamd, false);
        $sent = (string) stream_get_contents($clamd);
        fclose($clamd);

        return $result;
    };

    $clean = $verdict("stream: OK\0", 'hello');
    expect($clean->status)->toBe(ScanVerdict::CLEAN)
        ->and($sent)->toBe("zINSTREAM\0".pack('N', 5).'hello'.pack('N', 0));

    $found = $verdict("stream: Win.Test.EICAR_HDB-1 FOUND\0", eicar());
    expect($found->status)->toBe(ScanVerdict::INFECTED)->and($found->signature)->toBe('Win.Test.EICAR_HDB-1');

    // Too large for clamd, or no answer at all: never "clean".
    expect($verdict("INSTREAM size limit exceeded. ERROR\0", 'x')->status)->toBe(ScanVerdict::UNAVAILABLE)
        ->and($verdict('', 'x')->status)->toBe(ScanVerdict::UNAVAILABLE)
        ->and((new ClamAvScanner('127.0.0.1', 1, 1))->scan(tempFile('x'))->status)->toBe(ScanVerdict::UNAVAILABLE);

    [$ours, $clamd] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    fwrite($clamd, "PONG\0");
    expect((new ClamAvScanner('clamav', 3310, 5, fn () => $ours))->ping())->toBeNull();
});

it('refuses an infected upload, and one it cannot check unless scanning is optional', function () {
    $check = fn (string $content) => Validator::make(['file' => tempFile($content)], ['file' => [new CleanFile]]);

    // No scanner on the server: everything passes.
    expect(app(UploadScanner::class))->toBeInstanceOf(NullUploadScanner::class)
        ->and($check(eicar())->passes())->toBeTrue();

    app()->instance(UploadScanner::class, fakeScanner());
    expect($check('a homework recording')->passes())->toBeTrue()
        ->and($check(eicar())->errors()->first('file'))->toBe(__('shared::uploads.infected'))
        ->and(ScannerHealth::last()['infected'] ?? 0)->toBe(1);

    app()->instance(UploadScanner::class, fakeScanner(answers: false));
    expect($check('anything')->errors()->first('file'))->toBe(__('shared::uploads.unavailable'))
        ->and(ScannerHealth::last()['error'] ?? '')->toContain('connection refused');
    config(['uploads.required' => false]);
    expect($check('anything')->passes())->toBeTrue();
});

it('checks an import file before reading it', function () {
    $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $admin = User::factory()->withRole(Role::Admin)->create();
    $this->actingAs($admin);
    app()->instance(UploadScanner::class, fakeScanner());

    expect(fn () => app(StageImport::class)->handle('families', tempFile("name,email\n".eicar().",x@y.z\n", 'csv'), 'families.csv', $admin->id))
        ->toThrow(ValidationException::class, __('shared::uploads.infected'));
});

it('tells the platform whether clamd answers', function () {
    expect(app(SystemHealth::class)->checks()['scanning']['status'])->toBe('off');

    config(['uploads.scanner' => 'clamav']);
    app()->instance(UploadScanner::class, fakeScanner());
    expect(app(SystemHealth::class)->checks()['scanning'])->status->toBe('ok')->value->toContain('ClamAV 1.4.1');

    app()->instance(UploadScanner::class, fakeScanner(answers: false));
    expect(app(SystemHealth::class)->checks()['scanning'])->status->toBe('fail')->value->toContain('connection refused');
});
