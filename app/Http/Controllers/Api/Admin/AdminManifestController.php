<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ManifestStatus;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminManifestRequest;
use App\Http\Resources\ManifestResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Manifest;
use App\Models\ManifestDetail;
use App\Models\Shipment;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminManifestController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Manifest::query()
            ->with('courier')
            ->withCount([
                'details',
                'details as scanned_details_count' => fn ($q) => $q->whereNotNull('scanned_at'),
            ]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('manifest_number', 'like', "%{$search}%")
                    ->orWhere('origin_hub', 'like', "%{$search}%")
                    ->orWhere('destination_hub', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($courierId = $request->query('courier_id')) {
            $query->where('courier_id', (int) $courierId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('manifest_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('manifest_date', '<=', $dateTo);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);
        $paginator = $query->latest('id')->paginate($perPage);

        return $this->paginatedResponse(
            $paginator,
            ManifestResource::collection($paginator),
            'Daftar manifest berhasil diambil.'
        );
    }

    public function eligibleShipments(Request $request): JsonResponse
    {
        $courierId = (int) $request->query('courier_id', 0);

        $query = Shipment::query()
            ->with('courier')
            ->where('status', '!=', ShipmentStatus::DELIVERED->value)
            ->whereDoesntHave('manifestDetails', function ($q) {
                $q->whereHas('manifest', fn ($mq) => $mq->whereIn('status', [
                    ManifestStatus::OPEN->value,
                    ManifestStatus::IN_TRANSIT->value,
                ]));
            });

        if ($courierId > 0) {
            $query->where('courier_id', $courierId);
        } else {
            $query->whereNotNull('courier_id');
        }

        $shipments = $query->latest('id')->limit(100)->get();

        return $this->successResponse(
            ShipmentResource::collection($shipments),
            'Daftar shipment yang memenuhi syarat manifest berhasil diambil.'
        );
    }

    public function store(StoreAdminManifestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $courierId = (int) $validated['courier_id'];
        $shipmentIds = $validated['shipment_ids'];

        $shipments = Shipment::whereIn('id', $shipmentIds)->get();
        $invalidOwnerCount = $shipments->where('courier_id', '!=', $courierId)->count();

        if ($invalidOwnerCount > 0) {
            return $this->errorResponse(
                'Terdapat shipment yang bukan merupakan tugas kurir terpilih.',
                422,
                ['shipment_ids' => ['Pastikan seluruh shipment_ids sudah ditugaskan ke courier_id yang sama.']]
            );
        }

        $alreadyInActiveManifest = ManifestDetail::whereIn('shipment_id', $shipmentIds)
            ->whereHas('manifest', fn ($q) => $q->whereIn('status', [
                ManifestStatus::OPEN->value,
                ManifestStatus::IN_TRANSIT->value,
            ]))
            ->exists();

        if ($alreadyInActiveManifest) {
            return $this->errorResponse(
                'Satu atau lebih shipment sudah terdaftar pada manifest aktif lainnya.',
                422,
                ['shipment_ids' => ['Shipment tidak boleh diduplikasi ke dalam manifest aktif lainnya.']]
            );
        }

        $manifestNumber = ! empty($validated['manifest_number'])
            ? strtoupper(trim($validated['manifest_number']))
            : 'MNF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

        $manifest = DB::transaction(function () use ($validated, $manifestNumber, $courierId, $shipmentIds) {
            $newManifest = Manifest::create([
                'manifest_number' => $manifestNumber,
                'courier_id' => $courierId,
                'origin_hub' => $validated['origin_hub'],
                'destination_hub' => $validated['destination_hub'],
                'manifest_date' => $validated['manifest_date'],
                'status' => ManifestStatus::OPEN,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($shipmentIds as $shipmentId) {
                ManifestDetail::create([
                    'manifest_id' => $newManifest->id,
                    'shipment_id' => $shipmentId,
                ]);
            }

            return $newManifest;
        });

        return $this->successResponse(
            new ManifestResource($manifest->load(['courier', 'details.shipment.courier'])),
            'Manifest baru berhasil dibuat dan ditugaskan kepada kurir.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $manifest = Manifest::with(['courier', 'details.shipment.courier'])->find($id);

        if (! $manifest) {
            return $this->errorResponse('Data manifest tidak ditemukan.', 404);
        }

        return $this->successResponse(
            new ManifestResource($manifest),
            'Detail manifest berhasil diambil.'
        );
    }

    public function addShipments(Request $request, int $id): JsonResponse
    {
        $manifest = Manifest::find($id);

        if (! $manifest) {
            return $this->errorResponse('Data manifest tidak ditemukan.', 404);
        }

        if ($manifest->status !== ManifestStatus::OPEN) {
            return $this->errorResponse(
                'Shipment hanya dapat ditambahkan pada manifest yang berstatus Open.',
                422
            );
        }

        $validated = $request->validate([
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['required', 'integer', 'distinct', 'exists:shipments,id'],
        ]);

        $shipmentIds = $validated['shipment_ids'];
        $shipments = Shipment::whereIn('id', $shipmentIds)->get();

        if ($shipments->where('courier_id', '!=', $manifest->courier_id)->count() > 0) {
            return $this->errorResponse(
                'Seluruh shipment yang ditambahkan harus ditugaskan ke kurir manifest ini terlebih dahulu.',
                422
            );
        }

        $alreadyInActive = ManifestDetail::whereIn('shipment_id', $shipmentIds)
            ->whereHas('manifest', fn ($q) => $q->whereIn('status', [
                ManifestStatus::OPEN->value,
                ManifestStatus::IN_TRANSIT->value,
            ]))
            ->exists();

        if ($alreadyInActive) {
            return $this->errorResponse(
                'Salah satu shipment sudah masuk dalam manifest aktif.',
                422
            );
        }

        DB::transaction(function () use ($manifest, $shipmentIds) {
            foreach ($shipmentIds as $shipmentId) {
                ManifestDetail::create([
                    'manifest_id' => $manifest->id,
                    'shipment_id' => $shipmentId,
                ]);
            }
        });

        return $this->successResponse(
            new ManifestResource($manifest->fresh(['courier', 'details.shipment.courier'])),
            'Shipment berhasil ditambahkan ke dalam manifest.'
        );
    }

    public function removeShipment(int $id, int $shipmentId): JsonResponse
    {
        $manifest = Manifest::find($id);

        if (! $manifest) {
            return $this->errorResponse('Data manifest tidak ditemukan.', 404);
        }

        if ($manifest->status !== ManifestStatus::OPEN) {
            return $this->errorResponse(
                'Shipment hanya dapat dilepas saat manifest masih berstatus Open.',
                422
            );
        }

        $detail = ManifestDetail::where('manifest_id', $manifest->id)
            ->where('shipment_id', $shipmentId)
            ->first();

        if (! $detail) {
            return $this->errorResponse('Shipment tidak ditemukan di dalam manifest ini.', 404);
        }

        if ($detail->scanned_at !== null) {
            return $this->errorResponse(
                'Shipment yang sudah dipindai (scan) oleh kurir tidak dapat dikeluarkan dari manifest.',
                422
            );
        }

        if ($manifest->details()->count() <= 1) {
            return $this->errorResponse(
                'Manifest minimal harus memiliki 1 shipment di dalamnya.',
                422
            );
        }

        $detail->delete();

        return $this->successResponse(
            new ManifestResource($manifest->fresh(['courier', 'details.shipment.courier'])),
            'Shipment berhasil dikeluarkan dari manifest.'
        );
    }

    public function assignCourier(Request $request, int $id): JsonResponse
    {
        $manifest = Manifest::with('details')->find($id);

        if (! $manifest) {
            return $this->errorResponse('Data manifest tidak ditemukan.', 404);
        }

        if ($manifest->status !== ManifestStatus::OPEN) {
            return $this->errorResponse(
                'Kurir hanya dapat diganti saat manifest berstatus Open.',
                422
            );
        }

        $hasScannedItems = $manifest->details->whereNotNull('scanned_at')->isNotEmpty();
        if ($hasScannedItems) {
            return $this->errorResponse(
                'Manifest sudah memiliki paket yang di-scan kurir sehingga kurir tidak dapat diganti.',
                422
            );
        }

        $validated = $request->validate([
            'courier_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::COURIER->value)->where('is_active', true)),
            ],
        ]);

        $newCourier = User::findOrFail((int) $validated['courier_id']);

        DB::transaction(function () use ($manifest, $newCourier) {
            $manifest->update(['courier_id' => $newCourier->id]);
            $shipmentIds = $manifest->details->pluck('shipment_id')->all();
            if (! empty($shipmentIds)) {
                Shipment::whereIn('id', $shipmentIds)->update(['courier_id' => $newCourier->id]);
            }
        });

        return $this->successResponse(
            new ManifestResource($manifest->fresh(['courier', 'details.shipment.courier'])),
            "Manifest berhasil ditugaskan kepada kurir {$newCourier->name}."
        );
    }
}