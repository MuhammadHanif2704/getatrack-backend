<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request): JsonResponse
    {
        $courierId = $request->user()->id;

        $total = Shipment::forCourier($courierId)->count();
        $delivered = Shipment::forCourier($courierId)
            ->where('status', ShipmentStatus::DELIVERED)
            ->count();
        $inProgress = Shipment::forCourier($courierId)
            ->whereIn('status', [
                ShipmentStatus::PICKED_UP,
                ShipmentStatus::MANIFESTED,
                ShipmentStatus::RECEIVED_AT_DESTINATION,
            ])
            ->count();
        $pending = Shipment::forCourier($courierId)
            ->where('status', ShipmentStatus::AWB_CREATED)
            ->count();

        $dailyActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateString = $date->toDateString();

            $deliveredCount = ProofOfDelivery::where('courier_id', $courierId)
                ->whereDate('delivered_at', $dateString)
                ->count();

            $assignedCount = Shipment::forCourier($courierId)
                ->whereDate('created_at', $dateString)
                ->count();

            $dailyActivity[] = [
                'date' => $dateString,
                'label' => $date->translatedFormat('d M'),
                'delivered' => $deliveredCount,
                'assigned' => $assignedCount,
            ];
        }

        return $this->successResponse([
            'stats' => [
                'total' => $total,
                'delivered' => $delivered,
                'in_progress' => $inProgress,
                'pending' => $pending,
            ],
            'daily_activity' => $dailyActivity,
        ], 'Ringkasan operasional kurir berhasil diambil.');
    }

    public function recentShipments(Request $request): JsonResponse
    {
        $shipments = Shipment::forCourier($request->user()->id)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return $this->successResponse(
            ShipmentResource::collection($shipments),
            'Daftar shipment terbaru berhasil diambil.'
        );
    }
}