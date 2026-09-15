<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\ParserException;
use App\Services\Parser\Exceptions\RateLimitedException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class YandexApiClient
{
    private const BASE_URI = 'https://yandex.ru';

    private const TIMEOUT_SECONDS = 40.0;

    private const MAX_ATTEMPTS = 3;

    private const INCOMPLETE_SSR_ATTEMPTS = 3;

    private const INCOMPLETE_SSR_DELAY_MICROSECONDS = 200_000;

    /** @var list<string> */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    ];

    public function __construct(
        private readonly ClientInterface $http,
        private readonly YandexMapsStateExtractor $extractor = new YandexMapsStateExtractor,
    ) {}

    public static function make(): self
    {
        $stack = HandlerStack::create();
        $stack->push(self::retryMiddleware());

        $client = new Client([
            'base_uri' => self::BASE_URI,
            'timeout' => self::TIMEOUT_SECONDS,
            'handler' => $stack,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Referer' => 'https://yandex.ru/maps/',
            ],
        ]);

        return new self($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrgInfo(string $orgId, ?string $slug = null): array
    {
        return $this->getReviewsPage($orgId, 1, $slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReviews(string $orgId, int $offset, int $limit = 50, ?string $slug = null): array
    {
        $pageSize = max($limit, 1);
        $page = intdiv(max($offset, 0), $pageSize) + 1;

        return $this->getReviewsPage($orgId, $page, $slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReviewsPage(string $orgId, int $page, ?string $slug = null): array
    {
        return $this->getHtmlState($this->reviewsPath($orgId, $slug), [
            'page' => max($page, 1),
        ]);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function getHtmlState(string $path, array $query): array
    {
        $state = $this->requestHtmlState($path, $query);

        for ($attempt = 1; $attempt < self::INCOMPLETE_SSR_ATTEMPTS; $attempt++) {
            if (! $this->shouldRetryIncompleteSsr($state)) {
                return $state;
            }

            usleep(self::INCOMPLETE_SSR_DELAY_MICROSECONDS);
            $state = $this->requestHtmlState($path, $query);
        }

        return $state;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function requestHtmlState(string $path, array $query): array
    {
        try {
            $response = $this->http->request('GET', $path, [
                'query' => $query,
                'headers' => [
                    'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new ParserException('Не удалось получить данные с Яндекс Карт.', 0, $exception);
        }

        $status = $response->getStatusCode();

        if ($status === 404) {
            throw new OrganizationNotFoundException('Организация не найдена в Яндекс Картах.');
        }

        if ($status === 429) {
            throw new RateLimitedException;
        }

        if ($status >= 400) {
            throw new ParserException('Не удалось получить данные с Яндекс Карт.');
        }

        return $this->extractor->extract((string) $response->getBody());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function shouldRetryIncompleteSsr(array $state): bool
    {
        if (YandexMapsStateExtractor::hasReviewResults($state)) {
            return false;
        }

        $item = YandexMapsStateExtractor::businessItem($state);
        $reviewCount = is_array($item) ? ($item['ratingData']['reviewCount'] ?? 0) : 0;

        return is_numeric($reviewCount) && (int) $reviewCount > 0;
    }

    private function reviewsPath(string $orgId, ?string $slug): string
    {
        if ($slug !== null && $slug !== '' && ! ctype_digit($slug)) {
            return '/maps/org/'.$slug.'/'.$orgId.'/reviews/';
        }

        return '/maps/org/'.$orgId.'/reviews/';
    }

    public static function retryMiddleware(): callable
    {
        return Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): bool {
                if ($retries >= self::MAX_ATTEMPTS - 1) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                if ($response === null) {
                    return false;
                }

                $status = $response->getStatusCode();

                return $status >= 500;
            },
            static function (int $retries): int {
                return (2 ** $retries) * 1000;
            },
        );
    }
}
