<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\ParseJobRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\OrganizationRepository;
use App\Repositories\ParseJobRepository;
use App\Repositories\ReviewRepository;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\YandexApiClient;
use App\Services\Parser\YandexMapsParser;
use App\Services\WebSocket\HttpWsNotifier;
use App\Services\WebSocket\WsNotifierInterface;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, ReviewRepository::class);
        $this->app->bind(ParseJobRepositoryInterface::class, ParseJobRepository::class);
        $this->app->singleton(YandexApiClient::class, static fn (): YandexApiClient => YandexApiClient::make());
        $this->app->bind(ParserInterface::class, YandexMapsParser::class);
        $this->app->bind(WsNotifierInterface::class, static fn (): HttpWsNotifier => new HttpWsNotifier(new Client([
            'timeout' => 5,
            'http_errors' => false,
        ])));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
