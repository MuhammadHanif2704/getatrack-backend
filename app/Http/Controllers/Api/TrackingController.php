<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StoreTrackingRequest;
use App\Http\Resources\TrackingLogResource;
use App\Models\Shipment;
use App\Services\ShipmentTrackingService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ShipmentTrackingService $trackingService
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);

        if ($shipment->courier_id !== $request->user()->id) {
            return $this->errorResponse('Anda tidak berwenang melihat riwayat tracking shipment ini.', 403);
        }

        $logs = $shipment->trackingLogs()->with('updater')->get();

        return $this->successResponse(
            TrackingLogResource::collection($logs),
            'Riwayat perjalanan shipment berhasil diambil.'
        );
    }

    public function store(StoreTrackingRequest $request, int $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);
        $validated = $request->validated();
        $nextStatus = ShipmentStatus::from($validated['status']);

        try {
            $log = $this->trackingService->addTrackingLog(
                $shipment,
                $request->user(),
                $nextStatus,
                $validated['description'],
                $validated['location'] ?? null,
                isset($validated['latitude']) ? (float) $validated['latitude'] : null,
                isset($validated['longitude']) ? (float) $validated['longitude'] : null
            );

            return $this->successResponse(
                new TrackingLogResource($log->load('updater')),
                "Status pengiriman berhasil diperbarui menjadi '{$nextStatus->value}'.",
                201
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }
}