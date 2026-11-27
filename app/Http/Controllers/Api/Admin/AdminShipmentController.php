<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ManifestStatus;
use App\Enums\ServiceType;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Contact;
use App\Models\Shipment;
use App\Models\ShipmentType;
use App\Models\TrackingLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminShipmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with('courier');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('awb_number', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%")
                    ->orWhere('item_description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($courierFilter = $request->query('courier_id')) {
            if ($courierFilter === 'unassigned') {
                $query->whereNull('courier_id');
            } else {
                $query->where('courier_id', (int) $courierFilter);
            }
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);
        $paginator = $query->latest('id')->paginate($perPage);

        return $this->paginatedResponse(
            $paginator,
            ShipmentResource::collection($paginator),
            'Seluruh data shipment berhasil diambil.'
        );
    }

    public function formOptions(): JsonResponse
    {
        $couriers = User::query()
            ->where('role', UserRole::COURIER)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'courier_code', 'vehicle_type', 'plate_number']);

        $serviceTypes = collect(ServiceType::cases())->map(fn (ServiceType $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->values();

        $shipmentTypes = ShipmentType::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'handling_note']);

        $contacts = Contact::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'company_name', 'phone', 'address']);

        return $this->successResponse([
            'couriers' => $couriers,
            'service_types' => $serviceTypes,
            'shipment_types' => $shipmentTypes,
            'contacts' => $contacts,
        ]);
    }

    public function store(StoreAdminShipmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $extra = $request->validate([
            'item_description' => ['nullable', 'string', 'max:255'],
            'shipment_type' => ['nullable', 'string', 'max:100'],
        ]);

        $admin = $request->user();

        $awbNumber = ! empty($validated['awb_number'])
            ? strtoupper(trim($validated['awb_number']))
            : 'GTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        $courierId = ! empty($validated['courier_id']) ? (int) $validated['courier_id'] : null;

        $shipment = DB::transaction(function () use ($validated, $extra, $awbNumber, $courierId, $admin) {
            $newShipment = Shipment::create([
                'awb_number' => $awbNumber,
                'courier_id' => $courierId,
                'sender_name' => $validated['sender_name'],
                'sender_phone' => $validated['sender_phone'],
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'origin_address' => $validated['origin_address'],
                'destination_address' => $validated['destination_address'],
                'item_description' => $extra['item_description'] ?? null,
                'service_type' => $validated['service_type'],
                'shipment_type' => $extra['shipment_type'] ?? 'Paket Reguler',
                'weight_kg' => $validated['weight_kg'],
                'status' => ShipmentStatus::AWB_CREATED,
            ]);

            $courier = $courierId ? User::find($courierId) : null;
            $description = $courier
                ? "Nomor resi AWB dibuat dan ditugaskan kepada kurir {$courier->name}."
                : 'Nomor resi AWB dibuat dan menunggu penugasan kurir.';

            TrackingLog::create([
                'shipment_id' => $newShipment->id,
                'updated_by' => $admin->id,
                'status' => ShipmentStatus::AWB_CREATED,
                'description' => $description,
                'location' => 'Sistem Pusat GeTaTrack',
            ]);

            return $newShipment;
        });

        return $this->successResponse(
            new ShipmentResource($shipment->load(['courier', 'trackingLogs.updater'])),
            'Shipment baru berhasil dibuat.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $relations = [
            'courier',
            'manifests',
            'trackingLogs.updater',
            'proofOfDelivery',
        ];

        $podModel = (new Shipment())->proofOfDelivery()->getRelated();
        if (method_exists($podModel, 'photos')) {
            $relations[] = 'proofOfDelivery.photos';
        }

        $shipment = Shipment::with($relations)->find($id);

        if (! $shipment) {
            return $this->errorResponse('Data shipment tidak ditemukan.', 404);
        }

        $data = (new ShipmentResource($shipment))->resolve($request);

        if ($shipment->proofOfDelivery && isset($data['proof_of_delivery']) && is_array($data['proof_of_delivery'])) {
            $pod = $shipment->proofOfDelivery;
            $rawItems = $pod->photo_urls;

            if (empty($rawItems)) {
                if (method_exists($pod, 'photos') && $pod->relationLoaded('photos')) {
                    $rawItems = $pod->photos->all();
                } elseif (is_array($pod->photos)) {
                    $rawItems = $pod->photos;
                } elseif (is_string($pod->photos) && $pod->photos !== '') {
                    $decoded = json_decode($pod->photos, true);
                    if (is_array($decoded)) {
                        $rawItems = $decoded;
                    }
                }
            }

            $photos = collect($rawItems)->map(function ($photo) {
                $raw = is_string($photo)
                    ? $photo
                    : (is_array($photo)
                        ? ($photo['photo_url'] ?? $photo['url'] ?? $photo['photo_path'] ?? $photo['file_path'] ?? $photo['path'] ?? null)
                        : ($photo->photo_url ?? $photo->url ?? $photo->photo_path ?? $photo->file_path ?? $photo->path ?? null));

                if (! $raw || ! is_string($raw)) {
                    return null;
                }

                $raw = preg_replace('#^/?storage/(https?://|data:)#i', '$1', trim($raw));

                if (str_starts_with($raw, 'data:') || preg_match('#^https?://#i', $raw)) {
                    return preg_replace('#^http://#i', 'https://', $raw);
                }

                $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $raw), '/');
                return asset('storage/' . $cleanPath);
            })->filter()->values()->all();

            if (empty($photos) && ! empty($data['proof_of_delivery']['photos']) && is_array($data['proof_of_delivery']['photos'])) {
                $photos = $data['proof_of_delivery']['photos'];
            }

            $data['proof_of_delivery']['photos'] = $photos;
            if (empty($data['proof_of_delivery']['photo_url']) && ! empty($photos[0])) {
                $data['proof_of_delivery']['photo_url'] = $photos[0];
            }

            if (! empty($pod->signature_url) && is_string($pod->signature_url)) {
                $sigRaw = preg_replace('#^/?storage/(https?://|data:)#i', '$1', trim($pod->signature_url));
                $data['proof_of_delivery']['signature_url'] = (str_starts_with($sigRaw, 'data:') || preg_match('#^https?://#i', $sigRaw))
                    ? preg_replace('#^http://#i', 'https://', $sigRaw)
                    : asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $sigRaw), '/'));
            }
        }

        return $this->successResponse(
            $data,
            'Detail shipment berhasil diambil.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (! $shipment) {
            return $this->errorResponse('Data shipment tidak ditemukan.', 404);
        }

        if ($shipment->status === ShipmentStatus::DELIVERED) {
            return $this->errorResponse(
                'Shipment yang sudah berstatus Delivered tidak dapat diubah lagi.',
                422
            );
        }

        $validated = $request->validate([
            'courier_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::COURIER->value)->where('is_active', true)),
            ],
            'sender_name' => ['required', 'string', 'max:100'],
            'sender_phone' => ['required', 'string', 'max:20'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'origin_address' => ['required', 'string'],
            'destination_address' => ['required', 'string'],
            'item_description' => ['nullable', 'string', 'max:255'],
            'service_type' => ['required', 'string', Rule::in(ServiceType::values())],
            'shipment_type' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['required', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        $admin = $request->user();
        $oldCourierId = $shipment->courier_id;
        $newCourierId = ! empty($validated['courier_id']) ? (int) $validated['courier_id'] : null;

        DB::transaction(function () use ($shipment, $validated, $oldCourierId, $newCourierId, $admin) {
            $shipment->update([
                'courier_id' => $newCourierId,
                'sender_name' => $validated['sender_name'],
                'sender_phone' => $validated['sender_phone'],
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'origin_address' => $validated['origin_address'],
                'destination_address' => $validated['destination_address'],
                'item_description' => $validated['item_description'] ?? null,
                'service_type' => $validated['service_type'],
                'shipment_type' => $validated['shipment_type'] ?? $shipment->shipment_type ?? 'Paket Reguler',
                'weight_kg' => $validated['weight_kg'],
            ]);

            if ($oldCourierId !== $newCourierId && $newCourierId !== null) {
                $courier = User::find($newCourierId);
                TrackingLog::create([
                    'shipment_id' => $shipment->id,
                    'updated_by' => $admin->id,
                    'status' => $shipment->status,
                    'description' => "Pengiriman ditugaskan ulang kepada kurir {$courier?->name}.",
                    'location' => 'Sistem Pusat GeTaTrack',
                ]);
            }
        });

        return $this->successResponse(
            new ShipmentResource($shipment->fresh(['courier', 'manifests', 'trackingLogs.updater', 'proofOfDelivery'])),
            'Data shipment berhasil diperbarui.'
        );
    }

    public function assignCourier(Request $request, int $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (! $shipment) {
            return $this->errorResponse('Data shipment tidak ditemukan.', 404);
        }

        if ($shipment->status === ShipmentStatus::DELIVERED) {
            return $this->errorResponse(
                'Shipment yang sudah Delivered tidak dapat diubah kurirnya.',
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

        $admin = $request->user();
        $courier = User::findOrFail((int) $validated['courier_id']);

        DB::transaction(function () use ($shipment, $courier, $admin) {
            $shipment->update(['courier_id' => $courier->id]);

            TrackingLog::create([
                'shipment_id' => $shipment->id,
                'updated_by' => $admin->id,
                'status' => $shipment->status,
                'description' => "Resi ditugaskan kepada kurir {$courier->name}" . ($courier->courier_code ? " ({$courier->courier_code})" : '') . '.',
                'location' => 'Sistem Pusat GeTaTrack',
            ]);
        });

        return $this->successResponse(
            new ShipmentResource($shipment->fresh(['courier'])),
            "Shipment berhasil ditugaskan ke {$courier->name}."
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (! $shipment) {
            return $this->errorResponse('Data shipment tidak ditemukan.', 404);
        }

        if ($shipment->status !== ShipmentStatus::AWB_CREATED) {
            return $this->errorResponse(
                'Hanya shipment dengan status AWB Created yang dapat dibatalkan/dihapus.',
                422
            );
        }

        $inActiveManifest = $shipment->manifests()
            ->whereIn('status', [ManifestStatus::OPEN->value, ManifestStatus::IN_TRANSIT->value])
            ->exists();

        if ($inActiveManifest) {
            return $this->errorResponse(
                'Shipment masih terhubung dengan manifest aktif. Lepaskan dari manifest terlebih dahulu.',
                422
            );
        }

        DB::transaction(function () use ($shipment) {
            $shipment->trackingLogs()->delete();
            $shipment->manifestDetails()->delete();
            $shipment->delete();
        });

        return $this->successResponse(null, 'Shipment berhasil dibatalkan dan dihapus.');
    }
}