<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Middleware\ApiAuthMiddleware;

// Public Routes (No Authentication Required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Authenticated Routes (All logged-in users)
Route::middleware(['auth:api', ApiAuthMiddleware::class])->group(function () {
    // Common Appointment Routes
    Route::get('/clinics-with-specialties', [PatientController::class, 'getClinicsWithSpecialties']);
    Route::get('/clinics/{clinicId}/doctors-with-slots', [PatientController::class, 'getClinicDoctorsWithSlots']);

    // Patient-Specific Routes
    Route::middleware(['role:patient'])->group(function () {
        // Profile Management
        Route::prefix('patient')->group(function () {
            Route::get('/profile', [PatientController::class, 'getProfile']);
            Route::put('/profile', [PatientController::class, 'updateProfile']);

            // Clinic/Doctor Info
            Route::get('/clinics', [PatientController::class, 'getClinics']);
            Route::get('/clinics/{id}/doctors', [PatientController::class, 'getClinicDoctors']);
            Route::get('/doctor/{id}/schedule', [PatientController::class, 'getDoctorSchedules']);

            // Appointments
            Route::prefix('appointments')->group(function () {
                Route::get('/', [PatientController::class, 'getAppointments']);
                Route::post('/', [PatientController::class, 'createAppointment']);
                Route::post('/book-from-slot', [PatientController::class, 'bookFromAvailableSlot']);
                Route::put('/{id}', [PatientController::class, 'updateAppointment']);
                Route::delete('/{id}', [PatientController::class, 'cancelAppointment']);
            });

            // Medical Records
            Route::get('/medical-history', [PatientController::class, 'getMedicalHistory']);
            Route::get('/prescriptions', [PatientController::class, 'getPrescriptions']);
            Route::post('/documents', [PatientController::class, 'uploadDocument']);

            // Payments
            Route::get('/payments', [PatientController::class, 'getPaymentHistory']);
            Route::post('/payments', [PatientController::class, 'makePayment']);

            // Notifications
            Route::get('/notifications', [PatientController::class, 'getNotifications']);
            Route::put('/notifications/{id}', [PatientController::class, 'markNotificationAsRead']);
            Route::put('/notifications/mark-all-read', [PatientController::class, 'markAllNotificationsAsRead']);
        });
    });

    // Doctor-Specific Routes
    Route::middleware(['role:doctor'])->group(function () {
        Route::prefix('doctor')->group(function () {
            Route::get('/availability', [DoctorController::class, 'getAvailability']);
            // Add more doctor-specific routes here
        });
    });

    // Admin-Specific Routes
    Route::middleware(['role:admin'])->group(function () {
        Route::prefix('admin')->group(function () {
            Route::post('/create-doctor', [AdminController::class, 'createDoctor']);
            // Add more admin-specific routes here
        });
    });
});
