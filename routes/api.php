<?php

use App\Http\Controllers\Booking\InspectionBookingRequestCotroller;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Inspection\InspectionController;
use App\Http\Controllers\Inspection_Assign\InspectionAssignsController;
use App\Http\Controllers\InspectionBookingController;
use App\Http\Controllers\Inspecttion_Decline\InspectionDeclinesController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationPreferenceController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Reviews\HomeownerReviewsController;
use App\Http\Controllers\Reviews\InspectorReviewsController;
use App\Http\Controllers\Reviews\ReviewsController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Support\SupportRequestController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\DeleteUsersController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InspectionTypeController;
use App\Http\Controllers\User\GoogleAuthController;
use App\Http\Controllers\InspectionReportController;
use App\Http\Controllers\Admin\AdminInspectionReportController;
use App\Http\Controllers\Admin\UserManagementController;




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



        //inspections types

   Route::get('/inspection-types', [InspectionTypeController::class, 'index']);
   Route::get('/inspection-types/{id}', [InspectionTypeController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('/user-profile', [AuthController::class, 'getProfile']);

        Route::post('/profile/update', [AuthController::class, 'updateProfile']);

        Route::post('/book-inspection', [InspectionBookingRequestCotroller::class, 'store']);

        Route::get('/my-inspections', [InspectionBookingController::class, 'index']);

        Route::post('/booking/complete/{bookingId}', [InspectionBookingController::class, 'completeInspectionAndPayout']);




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

        Route::post('/inspection-types', [InspectionTypeController::class, 'store']);

        Route::post('/inspection-types/{id}', [InspectionTypeController::class, 'update']); // POST update (your case)
        Route::delete('/inspection-types/{id}', [InspectionTypeController::class, 'destroy']);
    });

    //Rehana Mim
        //Inspection report routes
        Route::prefix('inspection-reports')->group(function () {

            // start inspection
            Route::post('/{id}/start', [InspectionReportController::class, 'start']);

            // save everything (notes + media + report)
            Route::post('/{id}/save', [InspectionReportController::class, 'save']);
            Route::get('/{id}/save', [InspectionReportController::class, 'show']);

            // final submit
            Route::post('/{id}/submit', [InspectionReportController::class, 'submit']);

            // cancel inspection
            Route::post('/{id}/cancel', [InspectionReportController::class, 'cancel']);
        });


 // admin Inspection report routes
        Route::prefix('admin/reports')->group(function () {

            Route::get('/stats', [AdminInspectionReportController::class, 'stats']);

            Route::get('/', [AdminInspectionReportController::class, 'index']);

            Route::get('/{id}', [AdminInspectionReportController::class, 'show']);

            Route::get('/{id}/download', [AdminInspectionReportController::class, 'download']);

            Route::post('/{id}/archive', [AdminInspectionReportController::class, 'archive']);
            Route::post('/{id}/favorite', [AdminInspectionReportController::class, 'toggleFavorite']);
        });

        //Homeowner report routes
            Route::get('/homeowner/reports/{id}', [InspectionReportController::class, 'homeownerReport']);

            Route::post('/homeowner/reports/{id}/note', [InspectionReportController::class, 'homeownerNote']);

            Route::get('/homeowner/reports/{id}/share', [InspectionReportController::class, 'shareReport']);



//Admin user  dashbaord route


        Route::prefix('admin/users')->group(function () {
            Route::get('/dashboard-stats', [UserManagementController::class, 'stats']);

            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);

            Route::post('/{id}/suspend', [UserManagementController::class, 'suspend']);
            Route::post('/{id}/unsuspend', [UserManagementController::class, 'unsuspend']);

        });





























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

    Route::middleware('auth:sanctum')->group(function () {
        //Notification Preference
        Route::post('/notification-preference-save', [NotificationPreferenceController::class, 'notificationPreference']);
        Route::get('/users/reviews', [InspectorReviewsController::class, 'index']);
        Route::get('/users/reviews/matrics', [InspectorReviewsController::class, 'reviewMatrics']);

        //inspector Review
        Route::post('/user/review/submit',[HomeownerReviewsController::class, 'store']);

        //Inspection Assigns (inspector self and Admin)
        Route::post('/assign-Inspection', [InspectionAssignsController::class, 'createOrUpdateInspectionAssign']);

        //Inspection Decline
        Route::post('/decline-Inspection', [InspectionDeclinesController::class, 'storeDecline']);

        //Delete User(self)
        Route::post('/delete-user',[DeleteUsersController::class, 'destroy']);

        //Status wise Inspections
        Route::get('/status-inspections',[InspectionController::class, 'statusInspections']);

        //Admin Notification Store & sent
        Route::post('/store-sent-notifications',[NotificationController::class, 'store']);

        //Fetch Userwise Notification
        Route::get('/user-notifications',[NotificationController::class, 'fetchUserNotification']);

        //Fetch All Notifications Record (Admin)
        Route::get('/all-notifications',[NotificationController::class, 'fetchAllNotification']);

        //Status wise Inspections
        Route::get('/status-bookings',[InspectionBookingRequestCotroller::class, 'statusBookingList']);

        //Inspection Details
        Route::get('/inspection-details/{id}', [InspectionBookingRequestCotroller::class, 'inspectionDetails']);

    });

    //Booking Request Webhook Payment
    Route::get('/booking/success', [InspectionBookingRequestCotroller::class, 'BookingSuccess'])->name('booking.success');
    Route::get('/booking/cancel', [InspectionBookingRequestCotroller::class, 'BookingCancel'])->name('booking.cancel');
    Route::post('/booking/webhook-handle', [InspectionBookingRequestCotroller::class, 'handleWebhook'])->name('booking.webhook-handle');



});
