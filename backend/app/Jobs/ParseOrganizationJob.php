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
use App\Services\WebSocket\WsNotifierInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ParseOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

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
            $organizations->updateParseStatus(
                $this->organizationId,
                ParseStatus::Failed->value,
                $exception->getMessage(),
            );
            $parseJobs->markFailed($this->parseJobId, $exception->getMessage());
            $this->notify($notifier, 0, 0, 'failed', $exception->getMessage());

            throw $exception;
        }
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
