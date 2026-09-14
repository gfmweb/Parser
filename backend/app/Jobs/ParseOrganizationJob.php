<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParseProgressDTO;
use App\Enums\ParseStatus;
use App\Events\ParseProgressEvent;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\ParseJobRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Services\Organization\OrganizationSnapshotRecorder;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\ParserException;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\WebSocket\WsNotifierInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class ParseOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 240;

    private bool $failurePersisted = false;

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
        $parseJobs->markRunning($this->parseJobId);

        try {
            $organization = $organizations->findById($this->organizationId);
            $organizations->updateParseStatus($organization->id, ParseStatus::Parsing->value);

            $parsed = $parser->parse(
                $organization->yandex_url,
                function (int $parsedCount, int $total) use ($parseJobs, $notifier): void {
                    $parseJobs->updateProgress($this->parseJobId, $parsedCount, $total);
                    $this->notify($notifier, $parsedCount, $total, 'parsing', null);
                },
            );

            $reviews->upsertBatch($this->organizationId, $parsed->reviews);
            $snapshots->record($this->organizationId, $parsed);
            $organizations->applyParsedData($this->organizationId, $parsed);
            $parseJobs->updateProgress($this->parseJobId, count($parsed->reviews), $parsed->reviewCount);
            $parseJobs->markDone($this->parseJobId);
            $this->notify($notifier, count($parsed->reviews), $parsed->reviewCount, 'done', null);
        } catch (Throwable $exception) {
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
        $this->persistFailure(
            app(OrganizationRepositoryInterface::class),
            app(ParseJobRepositoryInterface::class),
            app(WsNotifierInterface::class),
            $exception ?? new RuntimeException('Job failed'),
        );
    }

    private function isNonRetryable(Throwable $exception): bool
    {
        if ($exception instanceof SourceChangedException || $exception instanceof OrganizationNotFoundException) {
            return true;
        }

        return $exception instanceof ParserException && $exception->getMessage() === 'Rate limited';
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
        $message = $exception->getMessage();

        $organizations->updateParseStatus(
            $this->organizationId,
            ParseStatus::Failed->value,
            $message,
        );
        $parseJobs->markFailed($this->parseJobId, $message);
        $this->notify($notifier, 0, 0, 'failed', $message);
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
        ));

        $notifier->sendProgress($event->progress);
    }
}
