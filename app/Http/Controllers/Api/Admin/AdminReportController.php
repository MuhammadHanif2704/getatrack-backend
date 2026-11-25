<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use App\Traits\ApiResponse;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $baseQuery = $this->buildFilteredQuery($request);

        // 1. Ringkasan KPI
        $totalShipments = (clone$baseQuery)->count();
        $deliveredShipments = (clone$baseQuery)->where('status', 'delivered')->count();
        $inProgressShipments = (clone$baseQuery)
            ->whereIn('status', ['picked_up', 'manifested', 'received_at_destination'])
            ->count();
        $pendingPickupShipments = (clone$baseQuery)->where('status', 'awb_created')->count();
        $totalWeightKg = round((float) (clone$baseQuery)->sum('weight_kg'), 2);
        $deliveryRate =$totalShipments > 0
            ? round(($deliveredShipments / $totalShipments) * 100, 1)
            : 0.0;

        // 2. Rekap Per Layanan
        $serviceRows = (clone$baseQuery)
            ->select('service_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(weight_kg) as total_weight'))
            ->groupBy('service_type')
            ->get();

        $serviceBreakdown = $serviceRows->map(function ($row) use ($totalShipments) {$rawService = $this->enumValue($row->service_type);
            $count = (int)$row->total;
            return [
                'service_type' => $rawService,
                'label' => $this->formatServiceLabel($rawService),
                'total' => $count,
                'total_weight_kg' => round((float) $row->total_weight, 2), 'percentage' =>$totalShipments > 0 ? round(($count / $totalShipments) * 100, 1) : 0.0,
            ];
        })->values();

        // 3. Rekap Performa Per Kurir
        $courierStatsRaw = (clone$baseQuery)
            ->whereNotNull('courier_id')
            ->select(
                'courier_id',
                DB::raw('COUNT(*) as total_shipments'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count"),
                DB::raw("SUM(CASE WHEN status IN ('awb_created', 'picked_up', 'manifested', 'received_at_destination') THEN 1 ELSE 0 END) as active_count"),
                DB::raw('SUM(weight_kg) as total_weight')
            )
            ->groupBy('courier_id')
            ->get()
            ->keyBy('courier_id');

        $couriersQuery = User::query()
            ->where('role', UserRole::COURIER)
            ->orderBy('name');

        if ($request->filled('courier_id') &&$request->query('courier_id') !== 'unassigned') {
            $couriersQuery->where('id', (int)$request->query('courier_id'));
        }

        $courierBreakdown =$couriersQuery->get(['id', 'name', 'courier_code', 'vehicle_type', 'plate_number'])
            ->map(function (User $courier) use ($courierStatsRaw) {
                $stat =$courierStatsRaw->get($courier->id);$total = $stat ? (int) $stat->total_shipments : 0;
                $delivered = $stat ? (int) $stat->delivered_count : 0;
                $active = $stat ? (int) $stat->active_count : 0;
                $weight = $stat ? round((float) $stat->total_weight, 2) : 0.0;

                return [
                    'courier_id' => $courier->id,
                    'courier_name' => $courier->name,
                    'courier_code' => $courier->courier_code,
                    'vehicle_info' => trim(($courier->vehicle_type ?? '') . ' ' . ($courier->plate_number ? "({$courier->plate_number})" : '')),
                    'total_shipments' => $total,
                    'delivered_count' => $delivered,
                    'active_count' => $active,
                    'total_weight_kg' => $weight,
                    'success_rate' => $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total_shipments')
            ->values();

        // 4. Tabel Detail Pengiriman (Paginated)
        $perPage = min(max((int)$request->query('per_page', 15), 5), 100);
        $paginator = (clone$baseQuery)
            ->with([
                'courier:id,name,courier_code',
                'proofOfDelivery:id,shipment_id,delivered_at,created_at',
            ])
            ->latest('id')
            ->paginate($perPage);

        $shipments = collect($paginator->items())->map(fn (Shipment$s) => $this->transformShipmentRow($s))->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan pengiriman berhasil diambil.',
            'data' => [
                'summary' => [
                    'total_shipments' => $totalShipments,
                    'delivered_shipments' => $deliveredShipments,
                    'in_progress_shipments' => $inProgressShipments,
                    'pending_pickup_shipments' => $pendingPickupShipments,
                    'total_weight_kg' => $totalWeightKg,
                    'delivery_rate' => $deliveryRate,
                ],
                'service_breakdown' => $serviceBreakdown,
                'courier_breakdown' => $courierBreakdown,
                'shipments' => $shipments,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->buildFilteredQuery($request)
            ->with([
                'courier:id,name,courier_code',
                'proofOfDelivery:id,shipment_id,delivered_at,created_at',
            ])
            ->latest('id')
            ->get();

        $filename = 'laporan-pengiriman-getatrack-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {$handle = fopen('php://output', 'w');
            // UTF-8 BOM agar kolom terbaca rapi saat dibuka di Microsoft Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No. Resi (AWB)',
                'Tanggal Dibuat',
                'Pengirim',
                'Telepon Pengirim',
                'Penerima',
                'Telepon Penerima',
                'Alamat Tujuan',
                'Isi Paket',
                'Jenis Pengiriman',
                'Layanan',
                'Berat (kg)',
                'Kurir',
                'Status',
                'Waktu Terkirim',
            ]);

            foreach ($rows as $shipment) {$item = $this->transformShipmentRow($shipment);
                fputcsv($handle, [
                    $item['awb_number'],$shipment->created_at ? $shipment->created_at->format('Y-m-d H:i:s') : '-',$item['sender_name'],
                    $item['sender_phone'],$item['recipient_name'],
                    $item['recipient_phone'],$item['destination_address'],
                    $item['item_description'] ?? '-',$item['shipment_type'],
                    $item['service_type_label'],$item['weight_kg'],
                    $item['courier'] ?$item['courier']['name'] : 'Belum Ditugaskan',
                    $item['status_label'],$item['delivered_at_formatted'] ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $query = Shipment::query();

        if ($dateFrom =$request->query('date_from')) {
            $query->whereDate('created_at', '>=',$dateFrom);
        }

        if ($dateTo =$request->query('date_to')) {
            $query->whereDate('created_at', '<=',$dateTo);
        }

        if ($status = trim((string)$request->query('status', ''))) {
            $query->where('status',$status);
        }

        if ($serviceType = trim((string)$request->query('service_type', ''))) {
            $query->where('service_type',$serviceType);
        }

        if ($request->filled('courier_id')) {
            $courierFilter = (string)$request->query('courier_id');
            if ($courierFilter === 'unassigned') {$query->whereNull('courier_id');
            } else {
                $query->where('courier_id', (int)$courierFilter);
            }
        }

        return $query;
    }

    private function transformShipmentRow(Shipment $shipment): array
    {
        $statusVal =$this->enumValue($shipment->status);$serviceVal = $this->enumValue($shipment->service_type);
        $pod =$shipment->proofOfDelivery;
        $deliveredDate = $pod?->delivered_at ?? $pod?->created_at;

        return [
            'id' => $shipment->id,
            'awb_number' => $shipment->awb_number,
            'sender_name' => $shipment->sender_name,
            'sender_phone' => $shipment->sender_phone,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'destination_address' => $shipment->destination_address,
            'item_description' => $shipment->item_description,
            'shipment_type' => $shipment->shipment_type ?? 'Paket Reguler',
            'service_type' => $serviceVal,
            'service_type_label' => $this->formatServiceLabel($serviceVal),
            'weight_kg' => (float) $shipment->weight_kg,
            'status' => $statusVal,
            'status_label' => $this->formatStatusLabel($statusVal),
            'courier' => $shipment->courier ? [
                'id' => $shipment->courier->id,
                'name' => $shipment->courier->name,
                'courier_code' => $shipment->courier->courier_code,
            ] : null,
            'created_at' => optional($shipment->created_at)->toISOString(),
            'delivered_at' => optional($deliveredDate)->toISOString(),
            'delivered_at_formatted' => $deliveredDate ? $deliveredDate->format('Y-m-d H:i:s') : null,
        ];
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        return (string) $value;
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'awb_created' => 'AWB Created',
            'picked_up' => 'Picked Up',
            'manifested' => 'Manifested',
            'received_at_destination' => 'Received at Destination',
            'delivered' => 'Delivered',
            default => $status,
        };
    }

    private function formatServiceLabel(string $service): string
    {
        return match ($service) {
            'regular' => 'Regular',
            'express' => 'Express',
            'same_day' => 'Same Day',
            default => $service,
        };
    }
}