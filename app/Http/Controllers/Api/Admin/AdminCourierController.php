<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCourierController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', UserRole::COURIER)
            ->withCount([
                'shipments',
                'shipments as delivered_shipments_count' => fn ($q) => $q->where('status', ShipmentStatus::DELIVERED->value),
                'shipments as active_shipments_count' => fn ($q) => $q->where('status', '!=', ShipmentStatus::DELIVERED->value),
            ]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('courier_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);
        $paginator = $query->latest('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (User $courier) => $this->serializeCourier($courier));
        $paginator->setCollection($items);

        return response()->json([
            'success' => true,
            'message' => 'Daftar kurir berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'courier_code' => ['nullable', 'string', 'max:30', 'unique:users,courier_code'],
            'phone' => ['nullable', 'string', 'max:25'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'plate_number' => ['nullable', 'string', 'max:25'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $courierCode = ! empty($validated['courier_code'])
            ? strtoupper(trim($validated['courier_code']))
            : 'KUR-' . strtoupper(Str::random(4));

        $courier = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
            'role' => UserRole::COURIER,
            'courier_code' => $courierCode,
            'phone' => $validated['phone'] ?? null,
            'vehicle_type' => $validated['vehicle_type'] ?? null,
            'plate_number' => ! empty($validated['plate_number']) ? strtoupper(trim($validated['plate_number'])) : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->successResponse(
            $this->serializeCourier($courier),
            'Akun kurir baru berhasil dibuat.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $courier = User::where('role', UserRole::COURIER)
            ->withCount([
                'shipments',
                'shipments as delivered_shipments_count' => fn ($q) => $q->where('status', ShipmentStatus::DELIVERED->value),
                'shipments as active_shipments_count' => fn ($q) => $q->where('status', '!=', ShipmentStatus::DELIVERED->value),
                'manifests',
            ])
            ->find($id);

        if (! $courier) {
            return $this->errorResponse('Data kurir tidak ditemukan.', 404);
        }

        $assignedShipments = $courier->shipments()
            ->latest('id')
            ->limit(25)
            ->get();

        $total = (int) $courier->shipments_count;
        $delivered = (int) $courier->delivered_shipments_count;
        $successRate = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;

        return $this->successResponse([
            'courier' => $this->serializeCourier($courier),
            'performance' => [
                'total_shipments' => $total,
                'delivered_shipments' => $delivered,
                'active_shipments' => (int) $courier->active_shipments_count,
                'total_manifests' => (int) $courier->manifests_count,
                'success_rate' => $successRate,
            ],
            'assignments' => ShipmentResource::collection($assignedShipments),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $courier = User::where('role', UserRole::COURIER)->find($id);

        if (! $courier) {
            return $this->errorResponse('Data kurir tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($courier->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'courier_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'courier_code')->ignore($courier->id)],
            'phone' => ['nullable', 'string', 'max:25'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'plate_number' => ['nullable', 'string', 'max:25'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'courier_code' => ! empty($validated['courier_code']) ? strtoupper(trim($validated['courier_code'])) : $courier->courier_code,
            'phone' => $validated['phone'] ?? null,
            'vehicle_type' => $validated['vehicle_type'] ?? null,
            'plate_number' => ! empty($validated['plate_number']) ? strtoupper(trim($validated['plate_number'])) : null,
        ];

        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $courier->update($payload);

        if (! $courier->is_active) {
            $courier->tokens()->delete();
        }

        return $this->successResponse(
            $this->serializeCourier($courier->fresh()),
            'Informasi kurir berhasil diperbarui.'
        );
    }

    public function toggleActive(int $id): JsonResponse
    {
        $courier = User::where('role', UserRole::COURIER)->find($id);

        if (! $courier) {
            return $this->errorResponse('Data kurir tidak ditemukan.', 404);
        }

        $courier->update(['is_active' => ! $courier->is_active]);

        if (! $courier->is_active) {
            $courier->tokens()->delete();
        }

        $statusLabel = $courier->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return $this->successResponse(
            $this->serializeCourier($courier),
            "Akun kurir {$courier->name} berhasil {$statusLabel}."
        );
    }

    private function serializeCourier(User $courier): array
    {
        return [
            'id' => $courier->id,
            'name' => $courier->name,
            'email' => $courier->email,
            'courier_code' => $courier->courier_code,
            'phone' => $courier->phone,
            'vehicle_type' => $courier->vehicle_type,
            'plate_number' => $courier->plate_number,
            'is_active' => (bool) $courier->is_active,
            'shipments_count' => (int) ($courier->shipments_count ?? 0),
            'delivered_shipments_count' => (int) ($courier->delivered_shipments_count ?? 0),
            'active_shipments_count' => (int) ($courier->active_shipments_count ?? 0),
            'created_at' => $courier->created_at?->toIso8601String(),
        ];
    }
}