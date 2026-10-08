<?php

namespace App\OpenApi;

use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Errors in the OpenAPI document as the API sends them: RFC 7807 problem
 * details (Modules\SharedKernel\Http\ProblemDetails), not Laravel's default
 * {message, errors}. Overrides Scramble's own exception responses.
 */
final class ProblemResponses extends HttpExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType && collect([ValidationException::class, AuthenticationException::class, AuthorizationException::class, RecordsNotFoundException::class, HttpException::class])
            ->contains(fn (string $class) => $type->isInstanceOf($class));
    }

    /** @param  ObjectType  $type */
    public function toResponse(Type $type)
    {
        [$status, $description] = match (true) {
            $type->isInstanceOf(ValidationException::class) => [422, 'Validation error: `errors` names each field'],
            $type->isInstanceOf(AuthenticationException::class) => [401, 'Not signed in, or the token expired'],
            $type->isInstanceOf(AuthorizationException::class) => [403, 'Not allowed'],
            $type->isInstanceOf(RecordsNotFoundException::class) => [404, 'Not found'],
            $type->isInstanceOf(ThrottleRequestsException::class) => [429, 'Too many requests: wait Retry-After seconds'],
            default => [$this->getResponseCode(count($type->templateTypes ?? []) > 3 ? ($type->templateTypes[7] ?? null) : ($type->templateTypes[0] ?? null), $type), $this->getDescription($type)],
        };
        if ($status === null) {
            return null;
        }

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/problem+json', $this->problem());
    }

    public function reference(ObjectType $type)
    {
        // A bare HttpException (abort()) has its status per call: described in place.
        return $type->name === HttpException::class ? null : new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }

    private function problem(): Reference
    {
        if ($this->components->hasSchema('ProblemDetails')) {
            return $this->components->getSchemaReference('ProblemDetails');
        }

        $body = (new OpenApiTypes\ObjectType)
            ->addProperty('type', (new OpenApiTypes\StringType)->setDescription('What went wrong, as a key: validation, unauthenticated, forbidden, not-found, conflict, too-many-requests, academy-suspended, plan-limit-reached (with limit and max), feature-not-in-plan (with feature), …')->example('validation'))
            ->addProperty('title', (new OpenApiTypes\StringType)->setDescription('The same, in the reader\'s language.'))
            ->addProperty('status', (new OpenApiTypes\IntegerType)->example(422))
            ->addProperty('detail', (new OpenApiTypes\StringType)->setDescription('More about this case, when there is more to say.'))
            ->addProperty('errors', (new OpenApiTypes\ObjectType)
                ->setDescription('422 only: each field and what is wrong with it, in the reader\'s language.')
                ->additionalProperties((new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType)))
            ->setRequired(['type', 'title', 'status']);

        return $this->components->addSchema('ProblemDetails', Schema::fromType($body));
    }
}
