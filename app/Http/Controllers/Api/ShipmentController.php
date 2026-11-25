<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Shipment::forCourier($request->user()->id);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('awb_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->query('status'), ShipmentStatus::values(), true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->query('date'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $paginator = $query->latest('updated_at')->paginate($perPage);

        return $this->paginatedResponse(
            $paginator,
            ShipmentResource::collection($paginator),
            'Daftar shipment berhasil diambil.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $shipment = Shipment::with([
            'courier',
            'manifests',
            'trackingLogs.updater',
            'proofOfDelivery.courier',
        ])->findOrFail($id);

        if ($shipment->courier_id !== $request->user()->id) {
            return $this->errorResponse('Anda tidak memiliki akses untuk melihat shipment milik kurir lain.', 403);
        }

        return $this->successResponse(
            new ShipmentResource($shipment),
            'Detail shipment berhasil diambil.'
        );
    }

    public function findByAwb(Request $request, string $awbNumber): JsonResponse
    {
        $normalizedAwb = strtoupper(trim($awbNumber));

        $shipment = Shipment::with([
            'courier',
            'manifests',
            'trackingLogs.updater',
            'proofOfDelivery.courier',
        ])->where('awb_number', $normalizedAwb)->first();

        if (! $shipment) {
            return $this->errorResponse("Nomor AWB '{$normalizedAwb}' tidak ditemukan di dalam sistem.", 404);
        }

        if ($shipment->courier_id !== $request->user()->id) {
            return $this->errorResponse("Paket dengan AWB '{$normalizedAwb}' tidak ditugaskan kepada Anda.", 403);
        }

        return $this->successResponse(
            new ShipmentResource($shipment),
            'Data shipment berhasil ditemukan.'
        );
    }
}