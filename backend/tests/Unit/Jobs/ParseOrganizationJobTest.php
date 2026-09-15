<?php

declare(strict_types=1);

use App\DTOs\OrganizationDataDTO;
use App\DTOs\OrganizationMetaDTO;
use App\Enums\ParseJobStatus;
use App\Enums\ParseStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\ParseJob;
use App\Models\Review;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\Exceptions\RateLimitedException;
use App\Services\Parser\Exceptions\SourceChangedException;
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

it('persists organization name from onMeta while parse is still running', function () {
    [$organization, $parseJob] = seedParseJob();
    $dto = parsedOrganizationData();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andReturnUsing(function (string $url, ?callable $onProgress = null, ?callable $onMeta = null) use ($dto, $organization): OrganizationDataDTO {
            if ($onMeta === null) {
                throw new RuntimeException('onMeta callback is required');
            }

            $onMeta(new OrganizationMetaDTO(
                yandexId: '12345678',
                name: 'Cafe Test',
                address: 'Moscow',
                rating: 4.8,
                ratingCount: 12,
                reviewCount: 1,
            ));

            $organization->refresh();

            expect($organization->name)->toBe('Cafe Test')
                ->and($organization->address)->toBe('Moscow')
                ->and((float) $organization->rating)->toBe(4.8)
                ->and($organization->parse_status)->toBe(ParseStatus::Parsing)
                ->and($organization->review_count)->toBe(0);

            return $dto;
        });
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']);
});

it('marks organization as failed when the parser throws', function () {
    [$organization, $parseJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->once()->andThrow(new RuntimeException('SQLSTATE[23505] duplicate'));
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    expect(fn () => app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']))
        ->toThrow(RuntimeException::class, 'SQLSTATE[23505] duplicate');

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Failed)
        ->and($organization->parse_error)->toBe('Парсинг не удался. Попробуйте позже.')
        ->and($parseJob->status)->toBe(ParseJobStatus::Failed)
        ->and($parseJob->error_message)->toBe('Парсинг не удался. Попробуйте позже.');
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
        ->and($job->backoff)->toBe(60)
        ->and($job->timeout)->toBe(240);
});

it('marks the organization failed without retrying SourceChangedException', function () {
    [$organization, $parseJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andThrow(new SourceChangedException('reviews[].id'));
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    $job = new ParseOrganizationJob($organization->id, $parseJob->id);
    app()->call([$job, 'handle']);

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Failed)
        ->and($organization->parse_error)->toBe('Не удалось разобрать страницу Яндекса. Попробуйте позже.')
        ->and($parseJob->status)->toBe(ParseJobStatus::Failed);
});

it('marks the organization failed without retrying RateLimitedException', function () {
    [$organization, $parseJob] = seedParseJob();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andThrow(new RateLimitedException);
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    $job = new ParseOrganizationJob($organization->id, $parseJob->id);
    app()->call([$job, 'handle']);

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Failed)
        ->and($organization->parse_error)->toBe('Слишком много запросов к Яндексу. Попробуйте позже.')
        ->and($parseJob->status)->toBe(ParseJobStatus::Failed);
});

it('saves reviews but marks failed when the parse is incomplete', function () {
    [$organization, $parseJob] = seedParseJob();
    $dto = new OrganizationDataDTO(
        yandexId: '12345678',
        name: 'Cafe Test',
        address: 'Moscow',
        rating: 4.8,
        ratingCount: 12,
        reviewCount: 50,
        reviews: [
            makeReviewDto('rev-1', 5, 'Great', '2024-01-15 12:00:00'),
        ],
        incomplete: true,
    );

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->once()->andReturn($dto);
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->atLeast()->once();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organization->id, $parseJob->id), 'handle']);

    $organization->refresh();
    $parseJob->refresh();

    expect($organization->parse_status)->toBe(ParseStatus::Failed)
        ->and($organization->parse_error)->toBe('Не удалось загрузить все отзывы. Попробуйте перепарсить.')
        ->and($organization->name)->toBe('Cafe Test')
        ->and($parseJob->status)->toBe(ParseJobStatus::Failed)
        ->and(Review::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('does not persist failure when the organization was deleted during parse', function () {
    [$organization, $parseJob] = seedParseJob();
    $organizationId = $organization->id;
    $parseJobId = $parseJob->id;
    $organization->delete();

    $parser = Mockery::mock(ParserInterface::class);
    $parser->shouldReceive('parse')->never();
    app()->instance(ParserInterface::class, $parser);

    $notifier = Mockery::mock(WsNotifierInterface::class);
    $notifier->shouldReceive('sendProgress')->never();
    app()->instance(WsNotifierInterface::class, $notifier);

    app()->call([new ParseOrganizationJob($organizationId, $parseJobId), 'handle']);

    expect(Organization::query()->find($organizationId))->toBeNull()
        ->and(ParseJob::query()->find($parseJobId))->toBeNull();
});
