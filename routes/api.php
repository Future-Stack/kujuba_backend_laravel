<?php

use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\InspectionBookingController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Support\SupportRequestController;
use App\Http\Controllers\User\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InspectionTypeController;


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

    Route::get('/faqs/{id}', [FaqController::class, 'show']);


    Route::post('/support-request', [SupportRequestController::class, 'store']);

    Route::prefix('admin')->group(function () {
        Route::get('/support', [SupportRequestController::class, 'index']);
        Route::get('/support/{id}', [SupportRequestController::class, 'show']);
        Route::post('/support/{id}/reply', [SupportRequestController::class, 'reply']);
        Route::delete('/support/{id}', [SupportRequestController::class, 'destroy']);
    });

    //Settings
    Route::get('settings', [SettingsController::class, 'show']);
    Route::post('settings', [SettingsController::class, 'createOrUpdate']);



    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        Route::post('/book-inspection', [InspectionBookingController::class, 'store']);

        Route::get('/my-inspections', [InspectionBookingController::class, 'index']);


       //FAQ
       Route::get('/faqs', [FaqController::class, 'index']);
       Route::post('/faqs', [FaqController::class, 'store']);
        Route::get('/faqs/{id}', [FaqController::class, 'show']);
        Route::put('/faqs/{id}', [FaqController::class, 'update']);
        Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);

            //Help and  Support Request Route

            Route::post('/support-request', [SupportRequestController::class, 'store']);

            Route::prefix('admin')->group(function () {
                Route::get('/support', [SupportRequestController::class, 'index']);
                Route::get('/support/{id}', [SupportRequestController::class, 'show']);
                Route::post('/support/{id}/reply', [SupportRequestController::class, 'reply']);
                Route::delete('/support/{id}', [SupportRequestController::class, 'destroy']);
            });

            //inspections types
            Route::get('/inspection-types', [InspectionTypeController::class, 'index']);
            Route::post('/inspection-types', [InspectionTypeController::class, 'store']);
            Route::get('/inspection-types/{id}', [InspectionTypeController::class, 'show']);
            Route::post('/inspection-types/{id}', [InspectionTypeController::class, 'update']); // POST update (your case)
            Route::delete('/inspection-types/{id}', [InspectionTypeController::class, 'destroy']);
        });

    //Rehana Mim
















































    //Sabbir
    Route::apiResource('pages', PageController::class)->names('pages.');

});