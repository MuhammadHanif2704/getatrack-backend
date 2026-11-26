<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminCourierController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminManifestController;
use App\Http\Controllers\Api\Admin\AdminMasterDataController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminShipmentController;
use App\Http\Controllers\Api\Admin\AdminTrackingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ManifestController;
use App\Http\Controllers\Api\PodController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// PUBLIC ENDPOINTS (LOGIN KURIR & LOGIN ADMIN)
// =============================================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// Media POD (Foto & Tanda Tangan) agar bisa dimuat langsung oleh Flutter & Web Admin
Route::get('/pods/{pod}/photo/{index?}', [PodController::class, 'photo']);
Route::get('/pods/{pod}/signature', [PodController::class, 'signature']);

// =============================================================================
// PROTECTED ENDPOINTS (SANCTUM BEARER TOKEN)
// =============================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // -------------------------------------------------------------------------
    // ENDPOINT KHUSUS KURIR (MOBILE APP)
    // -------------------------------------------------------------------------
    Route::middleware('role:courier')->group(function () {
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/recent-shipments', [DashboardController::class, 'recentShipments']);

        Route::get('/shipments', [ShipmentController::class, 'index']);
        Route::get('/shipments/awb/{awbNumber}', [ShipmentController::class, 'findByAwb']);
        Route::get('/shipments/{id}', [ShipmentController::class, 'show'])->whereNumber('id');

        Route::get('/shipments/{id}/tracking', [TrackingController::class, 'index'])->whereNumber('id');
        Route::post('/shipments/{id}/tracking', [TrackingController::class, 'store'])->whereNumber('id');

        Route::get('/shipments/{id}/pod', [PodController::class, 'show'])->whereNumber('id');
        Route::post('/shipments/{id}/pod', [PodController::class, 'store'])->whereNumber('id');

        Route::get('/manifests', [ManifestController::class, 'index']);
        Route::get('/manifests/{id}', [ManifestController::class, 'show'])->whereNumber('id');
        Route::post('/manifests/{id}/scan-awb', [ManifestController::class, 'scanAwb'])->whereNumber('id');
        Route::patch('/manifests/{id}/status', [ManifestController::class, 'updateStatus'])->whereNumber('id');
    });

    // -------------------------------------------------------------------------
    // ENDPOINT ADMINISTRASI WEB ADMIN
    // -------------------------------------------------------------------------
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard/overview', [AdminDashboardController::class, 'overview']);

        // Profile Admin
        Route::get('/profile', [AdminProfileController::class, 'show']);
        Route::put('/profile', [AdminProfileController::class, 'update']);
        Route::put('/profile/password', [AdminProfileController::class, 'updatePassword']);

        // Shipments
        Route::get('/shipments', [AdminShipmentController::class, 'index']);
        Route::get('/shipments/form-options', [AdminShipmentController::class, 'formOptions']);
        Route::post('/shipments', [AdminShipmentController::class, 'store']);
        Route::get('/shipments/{id}', [AdminShipmentController::class, 'show'])->whereNumber('id');
        Route::put('/shipments/{id}', [AdminShipmentController::class, 'update'])->whereNumber('id');
        Route::match(['put', 'patch'], '/shipments/{id}/assign-courier', [AdminShipmentController::class, 'assignCourier'])->whereNumber('id');
        Route::delete('/shipments/{id}', [AdminShipmentController::class, 'destroy'])->whereNumber('id');

        // Manifests
        Route::get('/manifests', [AdminManifestController::class, 'index']);
        Route::get('/manifests/eligible-shipments', [AdminManifestController::class, 'eligibleShipments']);
        Route::post('/manifests', [AdminManifestController::class, 'store']);
        Route::get('/manifests/{id}', [AdminManifestController::class, 'show'])->whereNumber('id');
        Route::post('/manifests/{id}/shipments', [AdminManifestController::class, 'addShipments'])->whereNumber('id');
        Route::delete('/manifests/{id}/shipments/{shipmentId}', [AdminManifestController::class, 'removeShipment'])
            ->whereNumber(['id', 'shipmentId']);
        Route::patch('/manifests/{id}/assign-courier', [AdminManifestController::class, 'assignCourier'])->whereNumber('id');

        // Couriers
        Route::get('/couriers', [AdminCourierController::class, 'index']);
        Route::post('/couriers', [AdminCourierController::class, 'store']);
        Route::get('/couriers/{id}', [AdminCourierController::class, 'show'])->whereNumber('id');
        Route::put('/couriers/{id}', [AdminCourierController::class, 'update'])->whereNumber('id');
        Route::patch('/couriers/{id}/toggle-active', [AdminCourierController::class, 'toggleActive'])->whereNumber('id');

        // Tracking
        Route::get('/tracking', [AdminTrackingController::class, 'index']);

        // Reports
        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/export', [AdminReportController::class, 'export']);

        // Master Data (7 Modul)
        Route::get('/master-data/{resource}', [AdminMasterDataController::class, 'index']);
        Route::post('/master-data/{resource}', [AdminMasterDataController::class, 'store']);
        Route::put('/master-data/{resource}/{id}', [AdminMasterDataController::class, 'update'])->whereNumber('id');
        Route::delete('/master-data/{resource}/{id}', [AdminMasterDataController::class, 'destroy'])->whereNumber('id');
    });
});