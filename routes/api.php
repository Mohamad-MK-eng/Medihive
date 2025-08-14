<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PatientController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecretaryController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Models\Doctor;
use App\Models\TimeSlot;
//107 in postman collection

// 8 middelware



// Public Routes (No Authentication Required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot_password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetLink']);
Route::post('/reset_password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword']); // not in postMAN COLLECTION YET


Route::middleware(['auth:api', ApiAuthMiddleware::class])->group(function () {
    Route::post('/change_password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);







    //home
    Route::get('/doctors/top', [DoctorController::class, 'getTopDoctors']);
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);








    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::put('/doctors/{doctor}/admin_update', [DoctorController::class, 'adminUpdate']);
        Route::delete('/doctors/{doctor}', [DoctorController::class, 'destroy']); // not in postman

        // Clinics
        Route::post('/clinics', [AdminController::class, 'createClinic']);
        Route::put('/clinics/{clinic}', [AdminController::class, 'updateclinic']);
        Route::post('/clinics/{clinic}/upload_icon', [AdminController::class, 'uploadClinicIcon']);

        Route::get('/search/secretaries', [SearchController::class, 'searchSecretaries']);



        Route::post('/doctors/{doctor}/generate_timeslots', [AdminController::class, 'generateTimeSlotsForDoctor']);
        Route::delete('/doctors/{doctor}/delete', [AdminController::class, 'deleteDoctor']);

        // Wallet Reports
        Route::prefix('admin/wallet')->group(function () {
            Route::get('/transactions', [AdminController::class, 'getWalletTransactions']);
            Route::get('/income_report', [AdminController::class, 'getClinicIncomeReport']);
        });



        Route::prefix('admin/profile')->group(function () {
            Route::put('/', [AdminController::class, 'updateAdminInfo']);  // done

            //   Route::get('/picture', [AdminController::class, 'getProfilePicture']);
            Route::post('/picture', [AdminController::class, 'uploadProfilePicture']);
            Route::delete('/picture', [AdminController::class, 'deleteProfilePicture']);
        });


        Route::get('/picture', [AdminController::class, 'getProfilePictureFile']);
        // Doctors
        Route::post('/admin/create_doctor', [AdminController::class, 'createDoctor']);
        Route::post('/admin/create_secretary', [AdminController::class, 'createSecretary']);
    });


    // Doctor profile updates (by doctor or admin)
    Route::put('/doctors/{doctor}', [DoctorController::class, 'update']);


    Route::get('/profile', [DoctorController::class, 'getProfile']);
    Route::put('/profile', [DoctorController::class, 'updateProfile']);
    Route::get('/profile_picture', [DoctorController::class, 'getProfilePicture']);
    Route::post('/profile_picture', [DoctorController::class, 'uploadProfilePicture']);

    // Schedule & Appointments
    Route::get('/schedule', [DoctorController::class, 'getSchedule']);



    // Correct (new version)
    Route::middleware(['auth:api', 'role:doctor'])->get('/doctor/appointments', [DoctorController::class, 'getAppointments']);
    Route::get('/time_slots', [DoctorController::class, 'getTimeSlots']);


    Route::get('clinics/{clinic}/wallet', [ClinicController::class, 'getWalletBalance']); // tamam
    Route::get('clinics/{clinic}/wallet/transactions', [ClinicController::class, 'getWalletTransactions']); //tamam
    Route::post('clinics/{clinic}/wallet/withdraw', [ClinicController::class, 'withdrawFromWallet']); // tamam

    // Updated refund route
    Route::post('appointments/{appointment}/refund', [AppointmentController::class, 'processRefund']); // tamam

    Route::get('/patient_transactions/{patient}', [WalletController::class, 'getTransactions']);





    Route::middleware(['role:patient,secretary,admin'])->group(function () {


        Route::get('/search/clinics', [SearchController::class, 'searchClinics']);
        Route::get('/search/doctors', [SearchController::class, 'searchDoctors']);

        Route::prefix('patient')->group(function () {
            Route::get('/profile', [PatientController::class, 'getProfile']);
            Route::put('/profile', [PatientController::class, 'updateProfile']);
            Route::post('/profile_picture', [PatientController::class, 'uploadProfilePicture']);
            Route::get('/profile_picture', [PatientController::class, 'getProfilePicture']);




            // hereeeeeeeeeeeee
            Route::get('/appointments', [PatientController::class, 'getPatientHistory']);
            Route::post('/ratings', [RatingController::class, 'store']);
        });

        // days done
        Route::get('/doctors/{doctor}/available_slots', [AppointmentController::class, 'getDoctorAvailableDaysWithSlots']);

        //times done

        Route::get('doctors/{doctor}/available_times/{date}', [AppointmentController::class, 'getAvailableTimes']);


        Route::prefix('appointments')->group(function () {
            //first route is for another time
            Route::get('/', [AppointmentController::class, 'getAppointments']);
            Route::post('/', [AppointmentController::class, 'bookAppointment']);
            Route::put('/{appointment}', [AppointmentController::class, 'updateAppointment']);
            Route::delete('/{appointment}', [AppointmentController::class, 'cancelAppointment']);
            // leave it for the secretary's side



            Route::get('/available_slots/{doctor}/{date}', [AppointmentController::class, 'getAvailableSlots']);
        });



        Route::get('/clinics/{clinic}/doctors', [ClinicController::class, 'getClinicDoctors']);
        Route::get('/clinics/{clinic}', [ClinicController::class, 'show']);
        Route::get('/clinics', [ClinicController::class, 'index']);

        //  Route::get('/clinics/{clinic}/doctors', [AppointmentController::class, 'getClinicDoctors']);
        Route::get('/clinics/{clinic}/doctors-with-slots', [AppointmentController::class, 'getClinicDoctorsWithSlots']);
        Route::get('/doctors/{doctor}', [AppointmentController::class, 'getDoctorDetails']);
    });


    // Appointments on secretary's side
    Route::middleware(['role:secretary'])->group(function () {

        Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'rescheduleAppointment']);
        Route::post('/appointments/{appointment}/refund', [AppointmentController::class, 'processRefund']);


        Route::post('appointments/Book', [SecretaryController::class, 'secretaryBookAppointment']);
        //3/8/2025

        Route::get('/secretary/blocked_patients', [SecretaryController::class, 'listBlockedPatients']);

        Route::post('/secretary/unblock_patient', [SecretaryController::class, 'unblockPatient']);

        // Wallet Management
        Route::prefix('wallet')->group(function () {
            Route::post('/add_funds', [WalletController::class, 'addFunds']);
        });


        // Payments
        Route::post('/payments', [SecretaryController::class, 'makePayment']);
    });

    Route::middleware('role:doctor,secretary')->group(function () {
        Route::get('/search/patients', [SearchController::class, 'searchPatients']);
        Route::patch('/appointments/{appointment}/absent', [DoctorController::class, 'markAsAbsent']);
        Route::patch('/appointments/{appointment}/complete', [DoctorController::class, 'markAsCompleted']);
    });

    Route::middleware(['role:patient,doctor'])->group(function () {

        //hereeeeeeeeeeeeeeeee
        Route::get('/appointments/{appointment}/reports', [PatientController::class, 'getAppointmentReports']);
    });

    Route::middleware(['role:secretary,patient'])->group(function () {

        // Wallet
        Route::prefix('wallet')->group(function () {
            Route::post('/setup', [WalletController::class, 'setupWallet']);
            Route::get('/balance', [WalletController::class, 'getBalance']);
            Route::get('/transactions', [WalletController::class, 'getTransactions']);
            Route::post('/transfer', [WalletController::class, 'transferToClinic']);

            Route::post('change_pin', [WalletController::class, 'changePin']);
        });

        // Payments
        Route::get('/payments/history', [PaymentController::class, 'getPaymentHistory']);
        Route::get('/payments_info', [PaymentController::class, 'PaymentInfo']);
    });

    Route::middleware(['role:doctor'])->group(function () {
        Route::prefix('doctor')->group(function () {
            Route::get('/availability', [DoctorController::class, 'getAvailability']);
        });

        Route::post('/appointments/{appointment}/reports', [DoctorController::class, 'SubmitMedicalReport']);

        Route::post('/reports/{report}/prescriptions', [DoctorController::class, 'addPrescriptions']);
    });


























    Route::get('/notifications', 'NotificationController@index');

    Route::post('/notifications/{id}/read', 'NotificationController@markAsRead');

    Route::post('/notifications/read-all', 'NotificationController@markAllAsRead');
});
