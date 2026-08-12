<?php
use App\Http\Controllers\Api\AmbulanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InfaqController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function(){
    Route::get('/bootstrap',[PublicController::class,'bootstrap'])->middleware('throttle:60,1');
    Route::post('/reports',[PublicController::class,'storeReport'])->middleware('throttle:8,1');
    Route::get('/reports/{code}',[PublicController::class,'track'])->middleware('throttle:20,1');
    Route::post('/bot',[PublicController::class,'bot'])->middleware('throttle:30,1');
    Route::get('/infaq',[InfaqController::class,'publicInfo'])->middleware('throttle:60,1');
    Route::get('/infaq/qr',[InfaqController::class,'publicQr'])->middleware('throttle:60,1');
    Route::post('/infaq/payments',[InfaqController::class,'submitPayment'])->middleware('throttle:5,1');
});
Route::post('/auth/login',[AuthController::class,'login'])->middleware('throttle:6,1');

Route::middleware('api.token')->group(function(){
    Route::get('/auth/me',[AuthController::class,'me']);
    Route::post('/auth/logout',[AuthController::class,'logout']);
    Route::get('/dashboard',[DashboardController::class,'index']);
    Route::get('/reports',[ReportController::class,'index']);
    Route::get('/reports/{report}',[ReportController::class,'show']);
    Route::post('/reports/manual',[ReportController::class,'storeManual'])->middleware('role:petugas,admin');
    Route::post('/reports/{report}/assign',[ReportController::class,'assign'])->middleware('role:petugas,admin');
    Route::patch('/reports/{report}/status',[ReportController::class,'updateStatus'])->middleware('role:petugas,admin');
    Route::get('/reports/{report}/ktp',[ReportController::class,'ktp'])->middleware('role:petugas,admin');
    Route::post('/reports/{report}/verify',[ReportController::class,'verify'])->middleware('role:admin');
    Route::get('/ambulances',[AmbulanceController::class,'index']);
    Route::post('/ambulances',[AmbulanceController::class,'store'])->middleware('role:admin');
    Route::patch('/ambulances/{ambulance}',[AmbulanceController::class,'update'])->middleware('role:admin');
    Route::get('/programs',[ProgramController::class,'index']);
    Route::post('/programs',[ProgramController::class,'store'])->middleware('role:admin');
    Route::patch('/programs/{program}',[ProgramController::class,'update'])->middleware('role:admin');
    Route::get('/exports/ambulans.csv',[ExportController::class,'ambulanceCsv']);
    Route::get('/exports/ambulans.pdf',[ExportController::class,'ambulancePdf']);
    Route::get('/exports/pelayanan.csv',[ExportController::class,'serviceCsv']);
    Route::get('/exports/pelayanan.pdf',[ExportController::class,'servicePdf']);
    Route::middleware('role:admin')->group(function(){
        Route::get('/transactions',[FinanceController::class,'index']);
        Route::post('/transactions',[FinanceController::class,'store']);
        Route::post('/transactions/{transaction}/verify',[FinanceController::class,'verify']);
        Route::post('/transactions/{transaction}/reject',[FinanceController::class,'reject']);
        Route::get('/transactions/{transaction}/proof',[InfaqController::class,'proof']);
        Route::get('/infaq/settings',[InfaqController::class,'settings']);
        Route::post('/infaq/settings',[InfaqController::class,'updateSettings']);
        Route::get('/infaq/qr',[InfaqController::class,'privateQr']);
        Route::get('/exports/keuangan.csv',[ExportController::class,'financeCsv']);
        Route::get('/exports/keuangan.pdf',[ExportController::class,'financePdf']);
        Route::get('/users',[UserController::class,'index']);
        Route::post('/users',[UserController::class,'store']);
        Route::patch('/users/{user}',[UserController::class,'update']);
    });
});
