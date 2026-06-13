<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectionBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [AuthController::class, 'register']);

    Route::post('login', [AuthController::class, 'login']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);


    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        Route::post('/book-inspection', [InspectionBookingController::class, 'store']);

        Route::get('/my-inspections', [InspectionBookingController::class, 'index']);

        });

    });
    // Route::get('/user', function (Request $request) {
    //     return $request->user();
    // })->middleware('auth:sanctum');
