<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\OrganizationMetaDTO;
use App\DTOs\ParseProgressDTO;
use App\Enums\ParseStatus;
use App\Events\ParseProgressEvent;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\ParseJobRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Organization\OrganizationSnapshotRecorder;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\RateLimitedException;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\WebSocket\WsNotifierInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ParseOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 240;

    private bool $failurePersisted = false;

    private ?OrganizationMetaDTO $meta = null;

    public function __construct(
        private readonly int $organizationId,
        private readonly int $parseJobId,
    ) {}

    public function handle(
        ParserInterface $parser,
        OrganizationRepositoryInterface $organizations,
        ReviewRepositoryInterface $reviews,
        ParseJobRepositoryInterface $parseJobs,
        WsNotifierInterface $notifier,
        OrganizationSnapshotRecorder $snapshots,
    ): void {
        try {
            $organization = $organizations->findById($this->organizationId);
            $parseJobs->markRunning($this->parseJobId);
            $organizations->updateParseStatus($organization->id, ParseStatus::Parsing->value);

            $parsed = $parser->parse(
                $organization->yandex_url,
                function (int $parsedCount, int $total) use ($parseJobs, $notifier): void {
                    $parseJobs->updateProgress($this->parseJobId, $parsedCount, $total);
                    $this->notify($notifier, $parsedCount, $total, 'parsing', null);
                },
                function (OrganizationMetaDTO $meta) use ($organizations, $notifier): void {
                    $this->meta = $meta;
                    $organizations->applyParsedMeta($this->organizationId, $meta);
                    $this->notify($notifier, 0, $meta->reviewCount, 'parsing', null);
                },
            );

            $reviews->upsertBatch($this->organizationId, $parsed->reviews);
            $snapshots->record($this->organizationId, $parsed);
            $organizations->applyParsedData($this->organizationId, $parsed);

            if ($parsed->incomplete) {
                $message = 'Не удалось загрузить все отзывы. Попробуйте перепарсить.';
                $organizations->updateParseStatus(
                    $this->organizationId,
                    ParseStatus::Failed->value,
                    $message,
                );
                $parseJobs->updateProgress($this->parseJobId, count($parsed->reviews), $parsed->reviewCount);
                $parseJobs->markFailed($this->parseJobId, $message);
                $this->notify($notifier, count($parsed->reviews), $parsed->reviewCount, 'failed', $message);

                return;
            }

            $organizations->updateParseStatus($this->organizationId, ParseStatus::Done->value);
            $parseJobs->updateProgress($this->parseJobId, count($parsed->reviews), $parsed->reviewCount);
            $parseJobs->markDone($this->parseJobId);
            $this->notify($notifier, count($parsed->reviews), $parsed->reviewCount, 'done', null);
        } catch (Throwable $exception) {
            if ($exception instanceof ModelNotFoundException) {
                $this->fail($exception);

                return;
            }

            if ($this->isNonRetryable($exception) || $this->isLastAttempt()) {
                $this->persistFailure($organizations, $parseJobs, $notifier, $exception);
            }

            if ($this->isNonRetryable($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof ModelNotFoundException) {
            return;
        }

        $this->persistFailure(
            app(OrganizationRepositoryInterface::class),
            app(ParseJobRepositoryInterface::class),
            app(WsNotifierInterface::class),
            $exception ?? new RuntimeException('Парсинг не удался.'),
        );
    }

    private function isNonRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ModelNotFoundException) {
            return true;
        }

        if ($exception instanceof SourceChangedException || $exception instanceof OrganizationNotFoundException) {
            return true;
        }

        return $exception instanceof RateLimitedException;
    }

    private function isLastAttempt(): bool
    {
        return $this->job === null || $this->attempts() >= $this->tries;
    }

    private function persistFailure(
        OrganizationRepositoryInterface $organizations,
        ParseJobRepositoryInterface $parseJobs,
        WsNotifierInterface $notifier,
        Throwable $exception,
    ): void {
        if ($this->failurePersisted) {
            return;
        }

        $this->failurePersisted = true;
        $message = $this->userFacingFailureMessage($exception);

        $organizations->updateParseStatus(
            $this->organizationId,
            ParseStatus::Failed->value,
            $message,
        );
        $parseJobs->markFailed($this->parseJobId, $message);
        $this->meta = null;
        $this->notify($notifier, 0, 0, 'failed', $message);
    }

    private function userFacingFailureMessage(Throwable $exception): string
    {
        if (
            $exception instanceof SourceChangedException
            || $exception instanceof OrganizationNotFoundException
            || $exception instanceof RateLimitedException
        ) {
            return $exception->getMessage();
        }

        Log::error('Parse job failed', [
            'organization_id' => $this->organizationId,
            'parse_job_id' => $this->parseJobId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return 'Парсинг не удался. Попробуйте позже.';
    }

    private function notify(
        WsNotifierInterface $notifier,
        int $parsed,
        int $total,
        string $status,
        ?string $error,
    ): void {
        $event = new ParseProgressEvent(new ParseProgressDTO(
            organizationId: $this->organizationId,
            parseJobId: $this->parseJobId,
            total: $total,
            parsed: $parsed,
            status: $status,
            error: $error,
            name: $this->meta?->name,
            rating: $this->meta?->rating,
            address: $this->meta?->address,
        ));

        $notifier->sendProgress($event->progress);
    }
}
