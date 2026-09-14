<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

readonly class ParseProgressDTO
{
    public function __construct(
        public int $organizationId,
        public int $parseJobId,
        public int $total,
        public int $parsed,
        public string $status,
        public ?string $error,
    ) {
        if (! in_array($this->status, ['parsing', 'done', 'failed'], true)) {
            throw new InvalidArgumentException('Status must be parsing, done or failed.');
        }
    }

    /**
     * @return array{organizationId: int, parseJobId: int, total: int, parsed: int, status: string, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'organizationId' => $this->organizationId,
            'parseJobId' => $this->parseJobId,
            'total' => $this->total,
            'parsed' => $this->parsed,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
