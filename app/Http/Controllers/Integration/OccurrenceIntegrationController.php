<?php

namespace App\Http\Controllers\Integration;

use App\DTOs\OccurrenceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreExternalOccurrenceRequest;
use App\Services\Api\OccurrenceServices\IntegrationServices\RegisterOccurrenceCommandService;
use Illuminate\Support\Carbon;

class OccurrenceIntegrationController extends Controller
{
    public function __construct(
        private RegisterOccurrenceCommandService $registerOccurrenceCommandService,
    ){

    }

    public function store(StoreExternalOccurrenceRequest $request)
    {
        $idempotencyKey = $request->validated('idempotency_key');

        $newOccurrence = new OccurrenceDTO(
            $request->validated('externalId'),
            $request->validated('type'),
            $request->validated('description'),
            Carbon::parse($request->validated('reportedAt'))
        );

        $requestResult = $this->registerOccurrenceCommandService->receiveExternalOccurrence($newOccurrence, $idempotencyKey);

        return response()->json([
            'commandId' => $requestResult->getCommandId(),
            'status' => 'accepted'
        ], 202);
    }
}
