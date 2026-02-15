<?php

namespace App\DTOs;

use Carbon\Carbon;
class OccurrenceDTO
{
    public function __construct(
        private string $externalId,
        private string $type,
        private string $description,
        private Carbon $reportedAt
    ) { }

    public function toArray(): array
    {
        return [
            'externalId' => $this->getExternalId(),
            'type' => $this->getType(),
            'description' => $this->getDescription(),
            'reportedAt' => $this->getReportedAt()->toIso8601String(),
        ];
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }
    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getReportedAt(): Carbon
    {
        return $this->reportedAt;
    }
}
