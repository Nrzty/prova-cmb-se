<?php

namespace App\Services\Api\OccurrenceServices\IntegrationServices;
use App\Enums\OccurrenceIntegrationStatus;

class IntegrationResult
{
    public function __construct(
        private string $commandId,
        private OccurrenceIntegrationStatus $status
    ){ }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function getStatus(): OccurrenceIntegrationStatus
    {
        return $this->status;
    }
}
