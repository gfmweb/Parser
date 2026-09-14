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
            'meta' => ['current_page', 'last_page', 'total', 'per_page'],
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
