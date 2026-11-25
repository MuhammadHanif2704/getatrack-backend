<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $totalShipments = Shipment::count();
        $awbCreated = Shipment::where('status', 'AWB Created')->count();
        $pickedUp = Shipment::where('status', 'Picked Up')->count();
        $manifested = Shipment::where('status', 'Manifested')->count();
        $receivedAtDestination = Shipment::where('status', 'Received at Destination')->count();
        $delivered = Shipment::where('status', 'Delivered')->count();

        $totalCouriers = User::where('role', 'courier')->count();
        $activeCouriers = User::where('role', 'courier')->where('is_active', true)->count();
        $unassignedCount = Shipment::whereNull('courier_id')
            ->where('status', '!=', 'Delivered')
            ->count();

        $unassignedShipments = Shipment::query()
            ->whereNull('courier_id')
            ->where('status', '!=', 'Delivered')
            ->latest()
            ->limit(5)
            ->get([
                'id',
                'awb_number',
                'sender_name',
                'recipient_name',
                'destination_address',
                'service_type',
                'status',
                'created_at',
            ]);

        $recentShipments = Shipment::query()
            ->with('courier:id,name,courier_code')
            ->latest()
            ->limit(8)
            ->get([
                'id',
                'awb_number',
                'courier_id',
                'sender_name',
                'recipient_name',
                'destination_address',
                'service_type',
                'weight_kg',
                'status',
                'created_at',
            ]);

        $recentTracking = TrackingLog::query()
            ->with([
                'shipment:id,awb_number,recipient_name',
                'updater:id,name,role',
            ])
            ->latest()
            ->limit(8)
            ->get();

        // Statistik tren pengiriman 14 hari terakhir
        $days = 14;
        $startDate = Carbon::today()->subDays($days - 1);

        $dailyCreated = Shipment::query()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->whereDate('created_at', '>=', $startDate)
            ->groupBy('date')
            ->pluck('total', 'date');

        $dailyDelivered = Shipment::query()
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('COUNT(*) as total'))
            ->where('status', 'Delivered')
            ->whereDate('updated_at', '>=', $startDate)
            ->groupBy('date')
            ->pluck('total', 'date');

        $shipmentStatistics = [];
        for ($i = 0; $i < $days; $i++) {
            $dateObj = $startDate->copy()->addDays($i);
            $dateKey = $dateObj->format('Y-m-d');
            $shipmentStatistics[] = [
                'date' => $dateKey,
                'label' => $dateObj->format('d M'),
                'created' => (int) ($dailyCreated[$dateKey] ?? 0),
                'delivered' => (int) ($dailyDelivered[$dateKey] ?? 0),
            ];
        }

        $statusDistribution = [
            ['status' => 'AWB Created', 'count' => $awbCreated],
            ['status' => 'Picked Up', 'count' => $pickedUp],
            ['status' => 'Manifested', 'count' => $manifested],
            ['status' => 'Received at Destination', 'count' => $receivedAtDestination],
            ['status' => 'Delivered', 'count' => $delivered],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'total_shipments' => $totalShipments,
                    'awb_created' => $awbCreated,
                    'picked_up' => $pickedUp,
                    'manifested' => $manifested,
                    'received_at_destination' => $receivedAtDestination,
                    'delivered' => $delivered,
                    'total_couriers' => $totalCouriers,
                    'active_couriers' => $activeCouriers,
                    'unassigned_shipments' => $unassignedCount,
                ],
                'shipment_statistics' => $shipmentStatistics,
                'status_distribution' => $statusDistribution,
                'unassigned_shipments' => $unassignedShipments,
                'recent_shipments' => $recentShipments,
                'recent_tracking_activities' => $recentTracking,
            ],
        ]);
    }
}