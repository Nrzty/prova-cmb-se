<?php

namespace App\Http\Controllers\Api;

use App\DTOs\OccurrenceFilterDTO;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Enums\OccurrenceEnums\OccurrenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListOccurrencesRequest;
use App\Http\Requests\Api\ResolveOccurrenceRequest;
use App\Http\Requests\Api\StartOccurrenceRequest;
use App\Http\Resources\OccurrenceResource;
use App\Services\Api\OccurrenceServices\OccurrenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OccurrenceController extends Controller
{
    public function __construct(
       private OccurrenceService $occurrenceService,
    ){ }

    public function listAllOccurences(ListOccurrencesRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $status = isset($validated['status'])
            ? OccurrenceStatus::from($validated['status'])
            : null;

        $type = isset($validated['type'])
            ? OccurrenceType::from($validated['type'])
            : null;

        $filters = new OccurrenceFilterDTO($status, $type);

        $perPage = (int) $request->query('per_page', 15);

        $result = $this->occurrenceService->list($filters, $perPage);

        return OccurrenceResource::collection($result);
    }

    public function start($id, StartOccurrenceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->occurrenceService->startOccurrence(
            $id,
            $validated['idempotency_key']
        );

        return response()->json([
            'commandId' => $result->getCommandId(),
            'status' => 'accepted',
        ], 202);
    }

    public function resolve($id, ResolveOccurrenceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->occurrenceService->resolveOccurrence(
            $id,
            $validated['idempotency_key']
        );

        return response()->json([
            'commandId' => $result->getCommandId(),
            'status' => 'accepted',
        ], 202);
    }
}
