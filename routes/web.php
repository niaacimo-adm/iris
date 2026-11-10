<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IaDeletionRequestController;
use App\Http\Controllers\IaLipaController;
use App\Http\Controllers\IaMapController;
use App\Http\Controllers\IaOfficerController;
use App\Http\Controllers\IaProfileController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginOtpController;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Login with OTP routes
    Route::get('/login/otp', [LoginOtpController::class, 'show'])->name('login.otp.show');
    Route::post('/login/otp', [LoginOtpController::class, 'verify'])->name('login.otp.verify');
    Route::post('/login/otp/resend', [LoginOtpController::class, 'resend'])->name('login.otp.resend');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    // IA profiles (permission checks live in IaProfileController: ia.view / ia.create / ia.edit / ia.delete)
    Route::get('ia-profiles/data', [IaProfileController::class, 'data'])->name('ia-profiles.data');
    // The archive (must stay above the resource so "archived" is not read as a profile id)
    Route::get('ia-profiles/archived', [IaProfileController::class, 'archived'])->name('ia-profiles.archived');
    Route::get('ia-profiles/archived/data', [IaProfileController::class, 'archivedData'])->name('ia-profiles.archived.data');
    Route::post('ia-profiles/{id}/restore', [IaProfileController::class, 'restore'])->whereNumber('id')->name('ia-profiles.restore');
    // Map of the IA service areas
    Route::get('ia-map', [IaMapController::class, 'index'])->name('ia-map.index');
    Route::get('ia-map/data', [IaMapController::class, 'data'])->name('ia-map.data');
    // Delete requests (must stay above the resource too)
    Route::get('ia-profiles/deletion-requests', [IaDeletionRequestController::class, 'index'])->name('ia-profiles.deletion-requests.index');
    Route::get('ia-profiles/deletion-requests/data', [IaDeletionRequestController::class, 'data'])->name('ia-profiles.deletion-requests.data');
    Route::post('ia-profiles/deletion-requests/{deletionRequest}/approve', [IaDeletionRequestController::class, 'approve'])->name('ia-profiles.deletion-requests.approve');
    Route::post('ia-profiles/deletion-requests/{deletionRequest}/reject', [IaDeletionRequestController::class, 'reject'])->name('ia-profiles.deletion-requests.reject');
    Route::post('ia-profiles/deletion-requests/{deletionRequest}/withdraw', [IaDeletionRequestController::class, 'withdraw'])->name('ia-profiles.deletion-requests.withdraw');
    Route::post('ia-profiles/{iaProfile}/deletion-request', [IaDeletionRequestController::class, 'store'])->name('ia-profiles.deletion-requests.store');
    // withTrashed(['show']) lets an archived profile still be opened (read-only) from the archive
    Route::resource('ia-profiles', IaProfileController::class)->parameters(['ia-profiles' => 'iaProfile'])->withTrashed(['show']);
    Route::post('ia-profiles/{iaProfile}/officers', [IaOfficerController::class, 'store'])->name('ia-profiles.officers.store');
    Route::put('ia-profiles/{iaProfile}/officers/{officer}', [IaOfficerController::class, 'update'])->name('ia-profiles.officers.update');
    Route::delete('ia-profiles/{iaProfile}/officers/{officer}', [IaOfficerController::class, 'destroy'])->name('ia-profiles.officers.destroy');

    // LIPA records of one IA profile (view: ia.view, add/edit/delete: ia.edit - checked in IaLipaController)
    Route::get('ia-profiles/{iaProfile}/lipa', [IaLipaController::class, 'index'])->withTrashed()->name('ia-profiles.lipa.index');
    Route::get('ia-profiles/{iaProfile}/lipa/data', [IaLipaController::class, 'data'])->withTrashed()->name('ia-profiles.lipa.data');
    Route::post('ia-profiles/{iaProfile}/lipa', [IaLipaController::class, 'store'])->name('ia-profiles.lipa.store');
    Route::put('ia-profiles/{iaProfile}/lipa/{entry}', [IaLipaController::class, 'update'])->name('ia-profiles.lipa.update');
    Route::delete('ia-profiles/{iaProfile}/lipa/{entry}', [IaLipaController::class, 'destroy'])->name('ia-profiles.lipa.destroy');

    // User management (permission checks live in UserController)
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');

    // Role management (permission checks live in RoleController)
    Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
    Route::resource('roles', RoleController::class)->except(['show']);

    // Permission management (permission checks live in PermissionController)
    Route::get('permissions/data', [PermissionController::class, 'data'])->name('permissions.data');
    Route::resource('permissions', PermissionController::class)->except(['show']);

    // Reference data: regions, provinces, offices (office.manage, super admin only)
    Route::get('regions/data', [RegionController::class, 'data'])->name('regions.data');
    Route::resource('regions', RegionController::class)->except(['show']);

    Route::get('provinces/data', [ProvinceController::class, 'data'])->name('provinces.data');
    Route::resource('provinces', ProvinceController::class)->except(['show']);

    Route::get('offices/data', [OfficeController::class, 'data'])->name('offices.data');
    Route::resource('offices', OfficeController::class)->except(['show', 'destroy']);
    Route::patch('offices/{office}/status', [OfficeController::class, 'toggleStatus'])->name('offices.status');
});

require __DIR__.'/settings.php';
