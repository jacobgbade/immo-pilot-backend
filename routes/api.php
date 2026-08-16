<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemandLetterController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MaintenanceRequestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TenantAuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantPortalController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/tenant/register', [TenantAuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);

    // Tenant portal (role: tenant)
    Route::get('/tenant/home', [TenantPortalController::class, 'home']);

    // Properties
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::get('/properties/{property}', [PropertyController::class, 'show']);
    Route::put('/properties/{property}', [PropertyController::class, 'update']);
    Route::post('/properties/{property}/archive', [PropertyController::class, 'archive']);

    // Units (nested under a property to create; flat to update)
    Route::post('/properties/{property}/units', [UnitController::class, 'store']);
    Route::put('/units/{unit}', [UnitController::class, 'update']);

    // Leases (nested under a unit to create; flat to update/vacate)
    Route::post('/units/{unit}/lease', [LeaseController::class, 'store']);
    Route::put('/leases/{lease}', [LeaseController::class, 'update']);
    Route::post('/leases/{lease}/vacate', [LeaseController::class, 'vacate']);

    // Inspections (états des lieux)
    Route::get('/leases/{lease}/inspections', [InspectionController::class, 'index']);
    Route::post('/leases/{lease}/inspections', [InspectionController::class, 'store']);
    Route::get('/leases/{lease}/inspections/compare', [InspectionController::class, 'compare']);

    // Mises en demeure (impayés)
    Route::get('/demand-letters', [DemandLetterController::class, 'index']);
    Route::post('/leases/{lease}/demand-letters', [DemandLetterController::class, 'store']);
    Route::post('/demand-letters/{letter}/resolve', [DemandLetterController::class, 'resolve']);

    // Tenants
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/overview', [PaymentController::class, 'overview']);
    Route::post('/leases/{lease}/payments', [PaymentController::class, 'store']);

    // Artisans
    Route::get('/artisans', [ArtisanController::class, 'index']);
    Route::post('/artisans', [ArtisanController::class, 'store']);
    Route::put('/artisans/{artisan}', [ArtisanController::class, 'update']);
    Route::delete('/artisans/{artisan}', [ArtisanController::class, 'destroy']);

    // Maintenance
    Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index']);
    Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store']);
    Route::get('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'show']);
    Route::post('/maintenance-requests/{maintenanceRequest}/assign-artisan', [MaintenanceRequestController::class, 'assignArtisan']);
    Route::post('/maintenance-requests/{maintenanceRequest}/mark-in-progress', [MaintenanceRequestController::class, 'markInProgress']);
    Route::post('/maintenance-requests/{maintenanceRequest}/mark-resolved', [MaintenanceRequestController::class, 'markResolved']);

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::get('/expenses/summary', [ExpenseController::class, 'summary']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::put('/documents/{document}', [DocumentController::class, 'update']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);

    // Alerts
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts/{alert}/read', [AlertController::class, 'markRead']);
    Route::post('/alerts/read-all', [AlertController::class, 'markAllRead']);
});
