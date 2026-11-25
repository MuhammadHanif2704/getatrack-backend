<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\Country;
use App\Models\ServiceArea;
use App\Models\ServiceType;
use App\Models\ShipmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMasterDataController extends Controller
{
    private function resolveConfig(string $resource): ?array
    {
        return match ($resource) {
            'countries' => [
                'model' => Country::class,
                'table' => 'countries',
                'with' => [],
                'search' => ['code', 'name'],
                'orderBy' => 'name',
            ],
            'cities' => [
                'model' => City::class,
                'table' => 'cities',
                'with' => ['country'],
                'search' => ['code', 'name', 'province'],
                'orderBy' => 'name',
            ],
            'contact-categories' => [
                'model' => ContactCategory::class,
                'table' => 'contact_categories',
                'with' => [],
                'search' => ['code', 'name', 'description'],
                'orderBy' => 'name',
            ],
            'contacts' => [
                'model' => Contact::class,
                'table' => 'contacts',
                'with' => ['category', 'city'],
                'search' => ['code', 'name', 'company_name', 'phone', 'address'],
                'orderBy' => 'name',
            ],
            'service-types' => [
                'model' => ServiceType::class,
                'table' => 'service_types',
                'with' => [],
                'search' => ['code', 'name', 'sla_days'],
                'orderBy' => 'id',
            ],
            'shipment-types' => [
                'model' => ShipmentType::class,
                'table' => 'shipment_types',
                'with' => [],
                'search' => ['code', 'name', 'handling_note'],
                'orderBy' => 'id',
            ],
            'service-areas' => [
                'model' => ServiceArea::class,
                'table' => 'service_areas',
                'with' => ['city'],
                'search' => ['code', 'name', 'hub_name', 'coverage_districts'],
                'orderBy' => 'name',
            ],
            default => null,
        };
    }

    private function validatePayload(Request $request, string $resource, string $table, ?int $id = null): array
    {
        $codeRule = ['required', 'string', 'max:30', Rule::unique($table, 'code')->ignore($id)];

        return match ($resource) {
            'countries' => $request->validate([
                'code' => ['required', 'string', 'max:10', Rule::unique($table, 'code')->ignore($id)],
                'name' => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'cities' => $request->validate([
                'country_id' => ['nullable', 'exists:countries,id'],
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'contact-categories' => $request->validate([
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'contacts' => $request->validate([
                'contact_category_id' => ['nullable', 'exists:contact_categories,id'],
                'city_id' => ['nullable', 'exists:cities,id'],
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:120'],
                'company_name' => ['nullable', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:120'],
                'address' => ['required', 'string'],
                'postal_code' => ['nullable', 'string', 'max:15'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'service-types' => $request->validate([
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:100'],
                'sla_days' => ['nullable', 'string', 'max:50'],
                'base_rate' => ['nullable', 'numeric', 'min:0'],
                'description' => ['nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'shipment-types' => $request->validate([
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:100'],
                'handling_note' => ['nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
            'service-areas' => $request->validate([
                'city_id' => ['nullable', 'exists:cities,id'],
                'code' => $codeRule,
                'name' => ['required', 'string', 'max:120'],
                'hub_name' => ['required', 'string', 'max:120'],
                'coverage_districts' => ['nullable', 'string'],
                'is_active' => ['sometimes', 'boolean'],
            ]),
        };
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->resolveConfig($resource);
        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Modul master data tidak ditemukan'], 404);
        }

        $query = $config['model']::with($config['with']);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($config, $search) {
                foreach ($config['search'] as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($config['orderBy']);

        if ($request->boolean('all')) {
            return response()->json([
                'status' => 'success',
                'data' => $query->get(),
            ]);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $paginator->items(),
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

    public function store(Request $request, string $resource): JsonResponse
    {
        $config = $this->resolveConfig($resource);
        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Modul master data tidak ditemukan'], 404);
        }

        $validated = $this->validatePayload($request, $resource, $config['table']);
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $validated['is_active'] ?? true;

        $record = $config['model']::create($validated);
        if (!empty($config['with'])) {
            $record->load($config['with']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data master berhasil ditambahkan',
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $config = $this->resolveConfig($resource);
        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Modul master data tidak ditemukan'], 404);
        }

        $record = $config['model']::findOrFail($id);
        $validated = $this->validatePayload($request, $resource, $config['table'], $id);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $record->update($validated);
        if (!empty($config['with'])) {
            $record->load($config['with']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data master berhasil diperbarui',
            'data' => $record,
        ]);
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $config = $this->resolveConfig($resource);
        if (!$config) {
            return response()->json(['status' => 'error', 'message' => 'Modul master data tidak ditemukan'], 404);
        }

        $record = $config['model']::findOrFail($id);
        $record->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data master berhasil dihapus',
        ]);
    }
}