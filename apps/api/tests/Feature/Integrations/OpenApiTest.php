<?php

declare(strict_types=1);

use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Services\OpenApi\OpenApiDocument;
use Illuminate\Routing\Router;

/**
 * @return array<string, mixed>
 */
function integrationsOpenApi(): array
{
    return app(OpenApiDocument::class)->document();
}

it('serves the OpenAPI 3.1 document as YAML and JSON without authentication', function () {
    $yaml = test()->get('/api/public/v1/openapi.yaml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');

    expect($yaml->getContent())->toStartWith('openapi: "3.1.0"')
        ->toBe(file_get_contents(OpenApiDocument::yamlPath()));

    $json = test()->get('/api/public/v1/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    expect(json_decode((string) $json->getContent(), true))->toMatchArray(['openapi' => '3.1.0'])
        ->and(json_decode((string) $json->getContent(), true)['info']['version'])->toBe('v1');
});

it('keeps the YAML rendering in sync with the JSON source', function () {
    test()->artisan('integrations:openapi', ['--check' => true])->assertSuccessful();

    expect(app(OpenApiDocument::class)->renderYaml())->toBe(file_get_contents(OpenApiDocument::yamlPath()));
});

it('documents the 42 public operations of API.md §3 with unique operation ids', function () {
    $document = integrationsOpenApi();
    $operations = [];

    foreach ($document['paths'] as $path => $item) {
        foreach ($item as $method => $operation) {
            if ($method !== 'parameters') {
                $operations[strtoupper($method).' '.$path] = $operation['operationId'];
            }
        }
    }

    expect($operations)->toHaveCount(42)
        ->and(array_unique($operations))->toHaveCount(42)
        ->and(array_keys($operations))->toContain(
            'POST /oauth/token', 'GET /ping', 'GET /client', 'GET /openapi.yaml', 'GET /organization', 'GET /lookups/{type}',
            'PUT /vendors/external/{system}/{external_id}', 'POST /competitions/{competition}/publish',
            'POST /competitions/{competition}/invitations', 'GET /awards', 'POST /awards/{award}/erp-sync',
            'POST /webhook-deliveries/{delivery}/redeliver',
        )
        ->and(array_keys($document['webhooks']))->toHaveCount(13)->toContain('award.issued', 'webhook.test');
});

it('documents every Integrations public route', function () {
    $documented = [];

    foreach (integrationsOpenApi()['paths'] as $path => $item) {
        foreach (array_keys($item) as $method) {
            $documented[] = strtoupper($method).' '.$path;
        }
    }

    $routes = collect(app(Router::class)->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'App\\Modules\\Integrations\\Http\\Controllers\\PublicV1')
            && $route->getName() !== 'public.v1.openapi.json');

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
        $path = '/'.Str::after($route->uri(), 'api/public/v1/');

        expect($documented)->toContain($method.' '.$path);
    }
});

it('resolves every $ref and declares the OAuth scopes', function () {
    $document = integrationsOpenApi();
    $json = (string) json_encode($document);
    preg_match_all('#"\$ref":"\#\\\\/components\\\\/([a-zA-Z]+)\\\\/([A-Za-z0-9_]+)"#', $json, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $section, $name]) {
        expect($document['components'][$section])->toHaveKey($name);
    }

    expect(array_keys($document['components']['securitySchemes']['oauth2']['flows']['clientCredentials']['scopes']))
        ->toEqualCanonicalizing(array_map(fn ($s) => $s->value, ApiScope::cases()));
});

it('ships a Postman collection built on the same operations, without credentials', function () {
    $collection = json_decode((string) file_get_contents(OpenApiDocument::postmanPath()), true, flags: JSON_THROW_ON_ERROR);
    $requests = collect($collection['item'])->flatMap(fn (array $folder) => $folder['item']);

    expect($collection['info']['schema'])->toBe('https://schema.getpostman.com/json/collection/v2.1.0/collection.json')
        ->and($requests)->toHaveCount(42)
        ->and(collect($collection['variable'])->whereIn('key', ['client_id', 'client_secret', 'access_token', 'api_key'])->pluck('value')->unique()->all())->toBe([''])
        ->and($collection['auth']['bearer'][0]['value'])->toBe('{{access_token}}');

    $copy = base_path('../../docs/build/bafo-public-v1.postman_collection.json');

    if (is_file($copy)) {
        expect(file_get_contents($copy))->toBe(file_get_contents(OpenApiDocument::postmanPath()));
    }
});

it('renders the API reference page at /docs/api', function () {
    test()->get('/docs/api')
        ->assertOk()
        ->assertSee('cdn.jsdelivr.net/npm/@scalar/api-reference', false)
        ->assertSee(url('/api/public/v1/openapi.json'), false);
});
