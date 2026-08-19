<?php
use App\Http\Controllers\Api\AmbulanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InfaqController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function(){
    Route::get('/bootstrap',[PublicController::class,'bootstrap'])->middleware('throttle:60,1');
    Route::post('/reports',[PublicController::class,'storeReport'])->middleware('throttle:8,1');
    Route::get('/reports/{code}',[PublicController::class,'track'])->middleware('throttle:20,1');
    Route::post('/bot',[PublicController::class,'bot'])->middleware('throttle:30,1');
    Route::get('/regions',[PublicController::class,'regions'])->middleware('throttle:60,1');
    Route::get('/infaq',[InfaqController::class,'publicInfo'])->middleware('throttle:60,1');
    Route::get('/infaq/qr',[InfaqController::class,'publicQr'])->middleware('throttle:60,1');
    Route::post('/infaq/payments',[InfaqController::class,'submitPayment'])->middleware('throttle:5,1');
});

Route::post('/auth/login',[AuthController::class,'login'])->middleware('audit.login.throttle');

Route::middleware('api.token')->group(function(){
    Route::get('/auth/me',[AuthController::class,'me']);
    Route::post('/auth/refresh',[AuthController::class,'refresh'])->middleware('throttle:12,1');
    Route::post('/auth/logout',[AuthController::class,'logout']);

    Route::middleware('permission:dashboard.view')->group(function(){
        Route::get('/dashboard',[DashboardController::class,'index']);
        Route::get('/sync',[SystemController::class,'sync']);
        Route::get('/activity',[SystemController::class,'activity']);
        Route::get('/notifications',[NotificationController::class,'index']);
        Route::get('/regions/allowed-kelurahan',[RegionController::class,'allowedKelurahan']);
        Route::post('/notifications/read-all',[NotificationController::class,'readAll']);
        Route::post('/notifications/{notification}/read',[NotificationController::class,'read']);
    });

    Route::middleware('permission:operations.view')->group(function(){
        Route::get('/reports',[ReportController::class,'index']);
        Route::get('/reports/{report}',[ReportController::class,'show']);
        Route::get('/reports/{report}/ktp',[ReportController::class,'ktp']);
        // Export pelayanan mengikuti scope wilayah user, sehingga aman dibuka untuk Kecamatan/Kelurahan.
        Route::get('/exports/pelayanan.csv',[ExportController::class,'serviceCsv']);
        Route::get('/exports/pelayanan.pdf',[ExportController::class,'servicePdf']);
    });

    Route::middleware(['role:kota','permission:operations.view'])->group(function(){
        Route::get('/ambulances',[AmbulanceController::class,'index']);
        Route::get('/programs',[ProgramController::class,'index']);
        Route::get('/exports/ambulans.csv',[ExportController::class,'ambulanceCsv']);
        Route::get('/exports/ambulans.pdf',[ExportController::class,'ambulancePdf']);
    });

    Route::post('/reports/manual',[ReportController::class,'storeManual'])->middleware('permission:reports.input');
    Route::post('/reports/{report}/forward-kecamatan',[ReportController::class,'forwardToKecamatan'])->middleware('permission:reports.forward');
    Route::post('/reports/{report}/kecamatan-decision',[ReportController::class,'kecamatanDecision'])->middleware('permission:reports.validate');
    Route::post('/reports/{report}/forward-opd',[ReportController::class,'forwardToOpd'])->middleware('permission:reports.city');
    Route::post('/reports/{report}/assign',[ReportController::class,'assign'])->middleware('permission:reports.city');
    Route::patch('/reports/{report}/status',[ReportController::class,'updateStatus'])->middleware('permission:reports.city');
    Route::post('/reports/{report}/verify',[ReportController::class,'verify'])->middleware('permission:operations.verify');

    Route::middleware('permission:ambulance.manage')->group(function(){
        Route::post('/ambulances',[AmbulanceController::class,'store']);
        Route::patch('/ambulances/{ambulance}',[AmbulanceController::class,'update']);
    });
    Route::middleware('permission:program.manage')->group(function(){
        Route::post('/programs',[ProgramController::class,'store']);
        Route::patch('/programs/{program}',[ProgramController::class,'update']);
    });
    Route::middleware('permission:users.manage')->group(function(){
        Route::get('/regions',[RegionController::class,'index']);
        Route::get('/users',[UserController::class,'index']);
        Route::post('/users',[UserController::class,'store']);
        Route::patch('/users/{user}',[UserController::class,'update']);
    });
    Route::patch('/regions/{region}/local-structure',[RegionController::class,'updateLocalStructure'])->middleware('permission:regions.local.manage');

    Route::middleware(['role:kota','permission:dashboard.view'])->group(function(){
        Route::get('/dashboard/kota/map',[DashboardController::class,'cityMap']);
        Route::get('/dashboard/kota/kelurahan/{region}',[DashboardController::class,'kelurahanDetail']);
    });

    Route::get('/system/health',[SystemController::class,'health'])->middleware('permission:system.health');

    Route::middleware('permission:finance.view')->group(function(){
        Route::get('/transactions',[FinanceController::class,'index']);
        Route::get('/transactions/{transaction}',[FinanceController::class,'show']);
        Route::get('/transactions/{transaction}/proof',[InfaqController::class,'proof']);
        Route::get('/exports/keuangan.csv',[ExportController::class,'financeCsv']);
        Route::get('/exports/keuangan.pdf',[ExportController::class,'financePdf']);
    });
    Route::middleware('permission:finance.manage')->group(function(){
        Route::post('/transactions',[FinanceController::class,'store']);
        Route::post('/transactions/{transaction}/verify',[FinanceController::class,'verify']);
        Route::post('/transactions/{transaction}/reject',[FinanceController::class,'reject']);
    });
    Route::middleware('permission:payment.manage')->group(function(){
        Route::get('/infaq/settings',[InfaqController::class,'settings']);
        Route::post('/infaq/settings',[InfaqController::class,'updateSettings']);
        Route::get('/infaq/qr',[InfaqController::class,'privateQr']);
    });
});
