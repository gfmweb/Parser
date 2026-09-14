<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\ParserException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class YandexApiClient
{
    private const BASE_URI = 'https://yandex.ru';

    private const TIMEOUT_SECONDS = 15.0;

    private const MAX_ATTEMPTS = 3;

    /** @var list<string> */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    ];

    public function __construct(
        private readonly ClientInterface $http,
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
                'Accept' => 'application/json,text/plain,*/*',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Referer' => 'https://yandex.ru/maps/',
            ],
        ]);

        return new self($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrgInfo(string $orgId): array
    {
        return $this->getJson('/maps/api/business/fetchpointinfo', [
            'oid' => $orgId,
            'lang' => 'ru_RU',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getReviews(string $orgId, int $offset, int $limit = 10): array
    {
        return $this->getJson('/maps/api/business/reviews', [
            'oid' => $orgId,
            'offset' => $offset,
            'limit' => $limit,
            'lang' => 'ru_RU',
            'sort' => 'by_time',
        ]);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query): array
    {
        try {
            $response = $this->http->request('GET', $path, [
                'query' => $query,
                'headers' => [
                    'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new ParserException('Failed to reach Yandex Maps API.', 0, $exception);
        }

        $status = $response->getStatusCode();

        if ($status === 404) {
            throw new OrganizationNotFoundException('Organization not found.');
        }

        if ($status === 429) {
            throw new ParserException('Rate limited');
        }

        if ($status >= 400) {
            throw new ParserException("Yandex API request failed with HTTP {$status}.");
        }

        try {
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ParserException('Yandex API returned invalid JSON.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new ParserException('Yandex API returned invalid JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function retryMiddleware(): callable
    {
        return Middleware::retry(
            static function (int $retries, RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): bool {
                if ($retries >= self::MAX_ATTEMPTS - 1) {
                    return false;
                }

                if ($exception instanceof ConnectException || $exception !== null) {
                    return true;
                }

                if ($response === null) {
                    return false;
                }

                $status = $response->getStatusCode();

                return $status === 429 || $status >= 500;
            },
            static function (int $retries): int {
                return (2 ** ($retries + 1)) * 1000;
            },
        );
    }
}
