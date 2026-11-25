<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTrackingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TrackingLog::query()
            ->with([
                'shipment:id,awb_number,recipient_name,destination_address,courier_id,status',
                'shipment.courier:id,name,courier_code',
                'updater:id,name,role',
            ]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('shipment', function ($sq) use ($search) {
                        $sq->where('awb_number', 'like', "%{$search}%")
                            ->orWhere('recipient_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($courierId = $request->query('courier_id')) {
            $cId = (int) $courierId;
            $query->where(function ($q) use ($cId) {
                $q->where('updated_by', $cId)
                    ->orWhereHas('shipment', fn ($sq) => $sq->where('courier_id', $cId));
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginator = $query->latest('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(function (TrackingLog $log) {
            return [
                'id' => $log->id,
                'shipment_id' => $log->shipment_id,
                'status' => $log->status->value,
                'location' => $log->location,
                'description' => $log->description,
                'created_at' => $log->created_at?->toIso8601String(),
                'shipment' => $log->shipment ? [
                    'id' => $log->shipment->id,
                    'awb_number' => $log->shipment->awb_number,
                    'recipient_name' => $log->shipment->recipient_name,
                    'destination_address' => $log->shipment->destination_address,
                    'status' => $log->shipment->status->value,
                    'courier' => $log->shipment->courier ? [
                        'id' => $log->shipment->courier->id,
                        'name' => $log->shipment->courier->name,
                        'courier_code' => $log->shipment->courier->courier_code,
                    ] : null,
                ] : null,
                'updater' => $log->updater ? [
                    'id' => $log->updater->id,
                    'name' => $log->updater->name,
                    'role' => $log->updater->role->value,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat aktivitas tracking berhasil diambil.',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}