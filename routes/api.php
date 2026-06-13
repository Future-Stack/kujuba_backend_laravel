<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Faq\FaqController;


Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'message' => 'Welcome to API v1',
            'status' => 'ok',
            'version' => '1.0'
        ]);
    });

    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [AuthController::class, 'register']);

    Route::post('login', [AuthController::class, 'login']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);




    //Faq Route
  

Route::get('/faqs', [FaqController::class, 'index']);
Route::post('/faqs', [FaqController::class, 'store']);
Route::get('/faqs/{id}', [FaqController::class, 'show']);
Route::put('/faqs/{id}', [FaqController::class, 'update']);
Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);


    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);

    });

});
    // Route::get('/user', function (Request $request) {
    //     return $request->user();
    // })->middleware('auth:sanctum');
