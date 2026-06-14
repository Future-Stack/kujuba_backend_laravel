<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectionBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Support\SupportRequestController;
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
   



    Route::middleware('auth:sanctum')->group(function () {

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        Route::post('/book-inspection', [InspectionBookingController::class, 'store']);

        Route::get('/my-inspections', [InspectionBookingController::class, 'index']);


       //FAQ
       Route::post('/faqs', [FaqController::class, 'store']);

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

            //Inspection types
            

            Route::resource('inspection-types', InspectionTypeController::class);

        });




    });
    // Route::get('/user', function (Request $request) {
    //     return $request->user();
    // })->middleware('auth:sanctum');
