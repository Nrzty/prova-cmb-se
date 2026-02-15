<?php

namespace App\Services\Integration;
use App\Enums\OccurrenceIntegrationStatus;

class IntegrationResult
{
    public function __construct(
        private string $commandId,
        private OccurrenceIntegrationStatus $status
    ){ }

    public function isCreated() : bool
    {
        return $this->status === OccurrenceIntegrationStatus::CREATED;
    }

    public function isDuplicated() : bool
    {
        return $this->status === OccurrenceIntegrationStatus::DUPLICATED;
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function getStatus(): OccurrenceIntegrationStatus
    {
        return $this->status;
    }
}
