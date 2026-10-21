<?php

namespace Modules\Platform\Ai\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\AiSlots;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;
use Throwable;

/**
 * One request to a provider over HTTP (GPT, Gemini, Ollama), as Claude's
 * goes: while someone waits on a screen, one try within AI_TIMEOUT and one
 * of the few slots; in the background, AI_JOB_TIMEOUT and two more tries
 * for a moment's trouble (never for a credit that ran out). What it took
 * goes to ai_usage; how the provider answered to ServiceHealth, why it would
 * not in our words; the academy is told only that the helper is unavailable.
 * Neither the prompt nor the answer is ever logged.
 */
final class ModelCall
{
    public function __construct(private readonly AiSlots $slots) {}

    /**
     * @param  Closure(PendingRequest): Response  $send
     * @param  Closure(array<string, mixed>): AiAnswer  $read  throws AiFailed for an answer that cannot be used
     * @param  Closure(array<string, mixed>): array<string, int>  $usage
     * @param  Closure(int, mixed): (ServiceFault|string)  $fault  why the service would not, or an AiFailed reason about this request alone
     */
    public function run(AiFeature $feature, AiProvider $provider, string $model, Closure $send, Closure $read, Closure $usage, Closure $fault): AiAnswer
    {
        $waiting = $this->slots->waiting();
        $timeout = (int) config($waiting ? 'platform.ai.timeout' : 'platform.ai.job_timeout');
        $service = $provider->service();

        return $this->slots->hold(function () use ($feature, $model, $send, $read, $usage, $fault, $waiting, $timeout, $service): AiAnswer {
            $http = Http::acceptJson()->timeout($timeout)->connectTimeout(10);
            if (! $waiting) {
                $http = $http->retry([1000, 3000], when: fn (Throwable $e) => self::passing($e, $fault), throw: false);
            }

            try {
                $response = $send($http);
            } catch (ConnectionException) {
                AiUsage::note($feature, $service, $model, [], 'unavailable');
                ServiceHealth::fault($service, ServiceFault::unreachable());
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            }

            if ($response->failed()) {
                $why = $fault($response->status(), $response->json());
                if (is_string($why)) {
                    AiUsage::note($feature, $service, $model, [], $why);
                    throw new AiFailed($why);
                }
                AiUsage::note($feature, $service, $model, [], 'unavailable');
                ServiceHealth::fault($service, $why, $why->problem === ServiceProblem::Model ? $model : '');
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            }

            ServiceHealth::ok($service, $model);
            $body = (array) $response->json();
            try {
                $answer = $read($body);
            } catch (AiFailed $e) {
                AiUsage::note($feature, $service, $model, $usage($body), $e->reason);
                throw $e;
            }
            AiUsage::note($feature, $service, $answer->model, $answer->usage, 'ok');

            return $answer;
        });
    }

    /** A moment's trouble worth another try in the background: not a key, a credit or a model. */
    private static function passing(Throwable $e, Closure $fault): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }
        if (! $e instanceof RequestException) {
            return false;
        }
        $why = $fault($e->response->status(), $e->response->json());

        return $why instanceof ServiceFault && in_array($why->problem, [ServiceProblem::Down, ServiceProblem::Rate], true);
    }
}
