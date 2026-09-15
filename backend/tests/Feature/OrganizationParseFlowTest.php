<?php

declare(strict_types=1);

use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\WebSocket\WsNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches parse job when organization is created', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/organizations', ['url' => 'https://yandex.ru/maps/org/test/12345678/']);

    $response->assertStatus(201);
    Queue::assertPushed(ParseOrganizationJob::class);
    expect($response->json('yandex_id'))->toBe('12345678');
});

it('creates organization with null yandex_id when url has no org id', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/organizations', ['url' => 'https://yandex.ru/maps/geo/moskva/53000094/']);

    $response->assertStatus(201);
    expect($response->json('yandex_id'))->toBeNull();
});

it('stores canonical yandex url without query string', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/?ll=55.989556%2C54.737533&z=17.69',
        ]);

    $response->assertCreated()
        ->assertJsonPath('yandex_url', 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');
});

it('rejects duplicate organization when only query string differs', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/?ll=1&z=2',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url'])
        ->assertJsonPath('errors.url.0', 'Эта организация уже добавлена.');
});

it('rejects duplicate organization when slug is omitted', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/1123212619/',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('rejects duplicate organization from yandex.com with the same id', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.com/maps/org/svoya_kompaniya/1123212619/',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('rejects duplicate organization by stored yandex_id', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/old_slug/1123212619/',
        'yandex_id' => '1123212619',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/new_slug/1123212619/reviews/',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('allows a maps org url when an existing geo url shares the same trailing number', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/geo/moskva/12345678/',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/cafe/12345678/',
        ])
        ->assertCreated();
});

it('allows a different yandex organization id', function () {
    Queue::fake();
    $user = User::factory()->create();
    Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
        'yandex_id' => '1123212619',
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/the_borshch/138203157812/',
        ])
        ->assertCreated();
});

it('allows another user to add the same organization', function () {
    Queue::fake();
    $owner = User::factory()->create();
    Organization::query()->create([
        'user_id' => $owner->id,
        'yandex_url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
        'yandex_id' => '1123212619',
    ]);
    $stranger = User::factory()->create();
    $token = $stranger->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
        ])
        ->assertCreated();
});

it('returns 422 for invalid yandex url', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/organizations', ['url' => 'https://google.com/maps/place/invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('returns 403 when accessing another users organization', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $owner->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
    ]);

    $token = $stranger->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id)
        ->assertForbidden()
        ->assertJsonStructure(['message', 'errors']);
});

it('deletes organization and cascaded reviews', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
    ]);
    seedOrganizationReviews($organization, 2);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/organizations/'.$organization->id)
        ->assertOk()
        ->assertJsonPath('message', 'Организация удалена.');

    expect(Organization::query()->find($organization->id))->toBeNull()
        ->and(Review::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

it('returns 403 when deleting another users organization', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $owner->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
    ]);
    seedOrganizationReviews($organization, 1);
    $token = $stranger->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/organizations/'.$organization->id)
        ->assertForbidden();

    expect(Organization::query()->find($organization->id))->not->toBeNull()
        ->and(Review::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('returns paginated reviews with correct structure', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
    ]);
    seedOrganizationReviews($organization, 3);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'author_name', 'rating', 'text', 'reviewed_at'],
            ],
            'meta' => ['current_page', 'last_page', 'total', 'per_page', 'rating_counts'],
        ])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 50);
});

it('upsert does not create duplicate reviews on re-parse', function () {
    [$organization, $firstJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->twice()->andReturn(parsedOrganizationData());
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $firstJob->id), 'handle']);

    $secondJob = $organization->parseJobs()->create([
        'status' => 'queued',
    ]);

    app()->call([new ParseOrganizationJob($organization->id, $secondJob->id), 'handle']);

    expect(Review::query()->where('organization_id', $organization->id)->count())->toBe(1)
        ->and(Review::query()->where('yandex_review_id', 'rev-1')->count())->toBe(1);
});
