<?php

namespace App\Http\Controllers\Api;

use App\Enums\ManifestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manifest\ScanManifestAwbRequest;
use App\Http\Requests\Manifest\UpdateManifestStatusRequest;
use App\Http\Resources\ManifestDetailResource;
use App\Http\Resources\ManifestResource;
use App\Models\Manifest;
use App\Services\ManifestService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManifestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ManifestService $manifestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Manifest::forCourier($request->user()->id)
            ->withCount([
                'details',
                'details as scanned_details_count' => fn ($q) => $q->whereNotNull('scanned_at'),
            ]);

        if ($request->filled('status') && in_array($request->query('status'), ManifestStatus::values(), true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('manifest_date', $request->query('date'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $paginator = $query->latest('manifest_date')->latest('id')->paginate($perPage);

        return $this->paginatedResponse(
            $paginator,
            ManifestResource::collection($paginator),
            'Daftar manifest berhasil diambil.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $manifest = Manifest::with(['details.shipment'])
            ->withCount([
                'details',
                'details as scanned_details_count' => fn ($q) => $q->whereNotNull('scanned_at'),
            ])
            ->findOrFail($id);

        if ($manifest->courier_id !== $request->user()->id) {
            return $this->errorResponse('Anda tidak memiliki akses untuk melihat manifest milik kurir lain.', 403);
        }

        return $this->successResponse(
            new ManifestResource($manifest),
            'Detail manifest berhasil diambil.'
        );
    }

    public function scanAwb(ScanManifestAwbRequest $request, int $id): JsonResponse
    {
        $manifest = Manifest::findOrFail($id);

        try {
            $detail = $this->manifestService->scanAwb(
                $manifest,
                $request->user(),
                $request->validated('awb_number')
            );

            return $this->successResponse(
                new ManifestDetailResource($detail),
                'AWB berhasil dipindai dan terverifikasi dalam manifest.'
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }

    public function updateStatus(UpdateManifestStatusRequest $request, int $id): JsonResponse
    {
        $manifest = Manifest::findOrFail($id);
        $nextStatus = ManifestStatus::from($request->validated('status'));

        try {
            $updatedManifest = $this->manifestService->updateStatus(
                $manifest,
                $request->user(),
                $nextStatus
            );

            return $this->successResponse(
                new ManifestResource($updatedManifest),
                'Status manifest berhasil diperbarui.'
            );
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }
}