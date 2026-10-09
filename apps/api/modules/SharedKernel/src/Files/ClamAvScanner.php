<?php

namespace Modules\SharedKernel\Files;

use Closure;
use Throwable;

/**
 * clamd over TCP, with its own protocol (no SDK): INSTREAM sends the file in
 * chunks, each after its length as four big-endian bytes, ended by an empty
 * one; clamd answers "stream: OK" or "stream: <signature> FOUND". A file
 * clamd cannot take (larger than its StreamMaxLength), or no answer, is
 * "unavailable", never clean.
 */
final class ClamAvScanner implements UploadScanner
{
    private const CHUNK = 65536;

    /** @param  ?Closure(): resource  $connect  how to reach clamd (tests hand over one end of a socket pair) */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout = 30,
        private readonly ?Closure $connect = null,
    ) {}

    public function scan(string $path): ScanVerdict
    {
        $file = @fopen($path, 'rb');
        if ($file === false) {
            return ScanVerdict::unavailable('the file cannot be read');
        }
        try {
            $answer = $this->talk(function ($socket) use ($file) {
                fwrite($socket, "zINSTREAM\0");
                while (! feof($file)) {
                    $chunk = (string) fread($file, self::CHUNK);
                    if ($chunk === '') {
                        break;
                    }
                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
                fwrite($socket, pack('N', 0));
            });
        } catch (Throwable $e) {
            return ScanVerdict::unavailable($e->getMessage());
        } finally {
            fclose($file);
        }

        return match (true) {
            str_ends_with($answer, ' OK') => ScanVerdict::clean(),
            str_ends_with($answer, ' FOUND') => ScanVerdict::infected(trim(substr($answer, (int) strpos($answer, ':') + 1, -6))),
            default => ScanVerdict::unavailable($answer === '' ? 'no answer' : $answer),
        };
    }

    public function ping(): ?string
    {
        try {
            $answer = $this->talk(fn ($socket) => fwrite($socket, "zPING\0"));

            return $answer === 'PONG' ? null : ($answer === '' ? 'no answer' : $answer);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function version(): ?string
    {
        try {
            $answer = $this->talk(fn ($socket) => fwrite($socket, "zVERSION\0"));

            return $answer === '' ? null : $answer;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  callable(resource): mixed  $send  @return string clamd's answer, without its terminating NUL */
    private function talk(callable $send): string
    {
        $socket = $this->connect !== null ? ($this->connect)() : @stream_socket_client("tcp://{$this->host}:{$this->port}", $code, $message, $this->timeout);
        if (! is_resource($socket)) {
            throw new \RuntimeException("clamd at {$this->host}:{$this->port} does not answer: ".($message ?? 'no connection'));
        }
        try {
            stream_set_timeout($socket, $this->timeout);
            $send($socket);
            $answer = '';
            while (! feof($socket)) {
                $read = fread($socket, 4096);
                if ($read === false || $read === '') {
                    if (stream_get_meta_data($socket)['timed_out']) {
                        throw new \RuntimeException('clamd took too long to answer');
                    }
                    break;
                }
                $answer .= $read;
                if (str_contains($answer, "\0")) {
                    break;
                }
            }

            return trim(str_replace("\0", '', $answer));
        } finally {
            fclose($socket);
        }
    }
}
