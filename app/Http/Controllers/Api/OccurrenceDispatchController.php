<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CloseDispatchRequest;
use App\Http\Requests\Api\CreateDispatchRequest;
use App\Services\Api\Dispatch\DispatchService;
use Illuminate\Http\JsonResponse;

class OccurrenceDispatchController extends Controller
{
    public function __construct(
        private DispatchService $dispatchService,
    ){ }

    public function dispatches($id, CreateDispatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $idempotencyKey = $validated['idempotency_key'];

        $resourceCode = $validated['resourceCode'];

       $result = $this->dispatchService->createDispatch($id, $resourceCode, $idempotencyKey);

        return response()->json([
            'commandId' => $result->getCommandId(),
            'status' => 'accepted',
        ], 202);
    }

    public function close($id, CloseDispatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->dispatchService->closeDispatch(
            $id,
            $validated['idempotency_key']
        );

        return response()->json([
            'commandId' => $result->getCommandId(),
            'status' => 'accepted',
        ], 202);
    }
}
