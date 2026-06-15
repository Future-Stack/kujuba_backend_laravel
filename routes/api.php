<?php

use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Inspection_Assign\InspectionAssignsController;
use App\Http\Controllers\InspectionBookingController;
use App\Http\Controllers\Notification\NotificationPreferenceController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Reviews\HomeownerReviewsController;
use App\Http\Controllers\Reviews\InspectorReviewsController;
use App\Http\Controllers\Reviews\ReviewsController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Support\SupportRequestController;
use App\Http\Controllers\User\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InspectionTypeController;
use App\Http\Controllers\User\GoogleAuthController;


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
     // Google OAuth (public, no auth required)
    Route::post('google/token', [GoogleAuthController::class, 'tokenLogin']);


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
    //Pages
    Route::apiResource('pages', PageController::class)->names('pages.');

    //Settings
    Route::get('settings', [SettingsController::class, 'show']);
    Route::post('settings', [SettingsController::class, 'createOrUpdate']);

    //Reviews
    Route::apiResource('reviews', ReviewsController::class);
    Route::get('/review-toggle-admin/{id}', [ReviewsController::class, 'toggleStatusAdmin']);
    Route::get('/suspend-review-inspector/{id}', [ReviewsController::class, 'suspendReviewInspector']);
    Route::get('/review-matrics', [ReviewsController::class, 'reviewMatrics']);

    //Notification Preference
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/notification-preference-save', [NotificationPreferenceController::class, 'notificationPreference']);
        Route::get('/users/reviews', [InspectorReviewsController::class, 'index']);
        Route::get('/users/reviews/matrics', [InspectorReviewsController::class, 'reviewMatrics']);

        //inspector Review
        Route::post('/user/review/submit',[HomeownerReviewsController::class, 'store']);

        //Inspection Assigns (inspector self and Admin)
        Route::post('/assign-Inspection', [InspectionAssignsController::class, 'createOrUpdateInspectionAssign']);
    });
});
