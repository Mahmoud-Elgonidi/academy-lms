<?php

namespace App\OpenApi;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * The OpenAPI document (packages/openapi/openapi.json, `composer openapi`):
 * the headers every call may carry, a tag per module, and a server that
 * does not depend on where it was generated, so the committed file only
 * changes when the API does.
 */
final class ApiDocumentation
{
    public static function configure(): void
    {
        Scramble::resolveTagsUsing(fn (RouteInfo $route) => [Str::of($route->className() ?? '')->after('Modules\\')->before('\\')->toString() ?: 'App']);

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->servers = [Server::make('/api')->setDescription('The academy\'s host, or the shared API host with X-Academy')];
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $route): void {
                $middleware = $route->route->gatherMiddleware();
                $operation->addParameters(array_values(array_filter([
                    in_array('tenant', $middleware, true)
                        ? self::header('X-Academy', 'The academy\'s slug, until a token names it (a token for another academy is refused).', 'noor')
                        : null,
                    self::header('Accept-Language', 'ar or en: the language of labels and errors.', 'ar'),
                    in_array('timezone', $middleware, true)
                        ? self::header('X-Timezone', 'The device\'s IANA zone: times come back on its clock.', 'Asia/Riyadh')
                        : null,
                    in_array('idempotent', $middleware, true)
                        ? self::header('Idempotency-Key', 'Any unique string per action: a retry with it answers the first response instead of acting twice.', '7f0c1d0e-2b8e-4c1f-9a51-3a1d5e2f8b10')
                        : null,
                ])));
            });
    }

    private static function header(string $name, string $description, string $example): Parameter
    {
        return Parameter::make($name, 'header')->setSchema(Schema::fromType(new StringType))->description($description)->example($example);
    }
}
