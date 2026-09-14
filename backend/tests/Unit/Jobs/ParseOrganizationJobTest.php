<?php

declare(strict_types=1);

use App\DTOs\OrganizationDataDTO;
use App\Enums\ParseJobStatus;
use App\Enums\ParseStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\OrganizationSnapshot;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\WebSocket\WsNotifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks organization as done after a successful parse', function () {
    [$organization, $parseJob] = seedParseJob();
    $dto = parsedOrganizationData();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andReturnUsing(function (string $url, ?callable $onProgress = null) use ($dto): OrganizationDataDTO {
            expect($url)->toBe('https://yandex.ru/maps/org/cafe/12345678/');
            if ($onProgress !== null) {
                $onProgress(1, 1);
            }

            return $dto;
        });
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']);

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Done)
        ->and($organization->name)->toBe('Cafe Test')
        ->and($organization->yandex_id)->toBe('12345678')
        ->and($parseJob->status)->toBe(ParseJobStatus::Done);
});

it('marks organization as failed when the parser throws', function () {
    [$organization, $parseJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->once()->andThrow(new RuntimeException('API down'));
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    expect(fn () => app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']))
        ->toThrow(RuntimeException::class, 'API down');

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Failed)
        ->and($organization->parse_error)->toBe('API down')
        ->and($parseJob->status)->toBe(ParseJobStatus::Failed)
        ->and($parseJob->error_message)->toBe('API down');
});

it('creates an organization snapshot after a successful parse', function () {
    [$organization, $parseJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->once()->andReturn(parsedOrganizationData());
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->zeroOrMoreTimes();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']);

    $snapshot = OrganizationSnapshot::query()->where('organization_id', $organization->id)->first();

    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot?->rating)->toBe(4.8)
        ->and($snapshot?->rating_count)->toBe(12)
        ->and($snapshot?->review_count)->toBe(1)
        ->and($snapshot?->diff)->toBe([]);
});

it('stores a snapshot diff against the previous snapshot', function () {
    [$organization, $parseJob] = seedParseJob();

    OrganizationSnapshot::query()->create([
        'organization_id' => $organization->id,
        'rating' => 4.50,
        'rating_count' => 10,
        'review_count' => 8,
        'snapshot_at' => now()->subDay(),
        'diff' => [],
    ]);

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->once()->andReturn(parsedOrganizationData(4.8, 12, 1));
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->zeroOrMoreTimes();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']);

    $snapshot = OrganizationSnapshot::query()
        ->where('organization_id', $organization->id)
        ->orderByDesc('snapshot_at')
        ->orderByDesc('id')
        ->first();

    expect($snapshot?->diff)->toMatchArray([
        'rating' => ['from' => 4.5, 'to' => 4.8],
        'rating_count' => ['from' => 10, 'to' => 12],
        'review_count' => ['from' => 8, 'to' => 1],
    ]);
});

it('retries three times with a 60 second backoff', function () {
    $job = new ParseOrganizationJob(1, 1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(60);
});
