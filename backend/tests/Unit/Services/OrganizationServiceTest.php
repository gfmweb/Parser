<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\ParseJobRepositoryInterface;
use App\Services\Organization\OrganizationService;
use App\Services\Parser\YandexUrlParser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('maps unique constraint violations to a duplicate validation error', function () {
    $organizations = Mockery::mock(OrganizationRepositoryInterface::class);
    $organizations->shouldReceive('upsert')
        ->once()
        ->andThrow(new UniqueConstraintViolationException(
            'pgsql',
            'insert into organizations',
            [],
            new RuntimeException('duplicate key value violates unique constraint'),
        ));

    $parseJobs = Mockery::mock(ParseJobRepositoryInterface::class);
    $parseJobs->shouldNotReceive('createForOrganization');

    $service = new OrganizationService($organizations, $parseJobs, new YandexUrlParser);
    $user = User::factory()->create();

    expect(fn () => $service->create($user, 'https://yandex.ru/maps/org/cafe/12345678/'))
        ->toThrow(ValidationException::class);
});
