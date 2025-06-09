<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PatientController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DoctorController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\SpecialtyController;

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
        Route::get('/profile-picture', [PatientController::class, 'getProfilePicture']);
        Route::put('/profile', [PatientController::class, 'updateProfile']);
        Route::post('/profile-picture', [PatientController::class, 'updateProfilePicture']); // Fixed this line

        // Clinic/Doctor Info
        Route::get('/clinics', [PatientController::class, 'getClinics']);
        Route::get('/clinics/{id}/doctors', [PatientController::class, 'getClinicDoctors']);
        Route::get('/doctor/{id}/schedule', [PatientController::class, 'getDoctorSchedules']);

            // Appointments
            Route::prefix('appointments')->group(function () {
                Route::get('/', [PatientController::class, 'getAppointments']);
              // flexible implementation required   Route::post('/', [PatientController::class, 'createAppointment']);
                Route::post('/book-from-slot', [PatientController::class, 'bookFromAvailableSlot']);
                Route::put('/{id}', [PatientController::class, 'updateAppointment']);
                Route::delete('/{id}', [PatientController::class, 'cancelAppointment']);
            });









            // Specialty routes
Route::get('/specialties', [SpecialtyController::class, 'index']);
Route::post('/specialties/{id}/upload-icon', [SpecialtyController::class, 'uploadIcon']);
Route::get('/specialties/{id}/icon', [SpecialtyController::class, 'getIcon']);




//for clinic branches viewing
// Clinic image routes
Route::post('/clinics/{id}/upload-image', [ClinicController::class, 'uploadImage']);
Route::get('/clinics/{id}/image', [ClinicController::class, 'getImage']);








            // Medical Records
            Route::get('/medical-history', [PatientController::class, 'getMedicalHistory']);
            Route::get('/prescriptions', [PatientController::class, 'getPrescriptions']);
            Route::post('/documents', [PatientController::class, 'uploadDocument']);

            // Payments
            Route::get('/payments', [PatientController::class, 'getPaymentHistory']);
            Route::post('/payments', [PatientController::class, 'makePayment']);


            Route::prefix('wallet')->group(function () {
    Route::post('/setup', [WalletController::class, 'setupWallet']); // Activate wallet with PIN
    Route::get('/balance', [WalletController::class, 'getBalance']); // Check balance
    Route::get('/transactions', [WalletController::class, 'getTransactions']); // View transactions
    Route::post('/transfer', [WalletController::class, 'transferToClinic']); // Make payment from wallet
});
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



               // Secretary-Specific Routes
    Route::middleware(['role:secretary'])->group(function () {
        // Wallet Management
        Route::prefix('wallet')->group(function () {
            Route::post('/add-funds', [WalletController::class, 'addFunds']);
            Route::get('/patient-transactions/{patientId}', [WalletController::class, 'getTransactions']);
        });

        // Refunds
        Route::post('/appointments/{appointmentId}/refund', [SecretaryController::class, 'processRefund']);

        // Other existing secretary routes...
    });

    // Admin-Specific Routes
    Route::middleware(['role:admin'])->group(function () {
        // Wallet Reports
           Route::prefix('admin')->group(function () {
            Route::post('/create-doctor', [AdminController::class, 'createDoctor']);
           });



        Route::prefix('admin/wallet')->group(function () {
            Route::get('/transactions', [AdminController::class, 'getWalletTransactions']);
            Route::get('/income-report', [AdminController::class, 'getClinicIncomeReport']);
            Route::get('/patient/{patientId}', [AdminController::class, 'getPatientWalletInfo']);
        });

        // Other existing admin routes...
    });
});
