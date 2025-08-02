<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\AdminApprovalController;


Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/registrasi', [AuthController::class, 'register']);
        Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
        Route::get('/admin/approve/{token}', [AdminApprovalController::class, 'aprovedAdmin']);
        Route::post('/login', [AuthController::class , 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::get('/ping', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'API percetakan berjalan normal.',
            'timestamp' => now(),
        ]);
    });
});




// route test email hapus juga gpp

Route::get('/test-email', function () {
    \Illuminate\Support\Facades\Mail::raw('Test kirim email dari Laravel.', function ($msg) {
        $msg->to('alyanabilafatimatuzzahra@gmail.com')
            ->subject('Test SMTP Gmail');
    });

    return 'Jika berhasil, email akan dikirim!';
});

