<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PatientController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController as AuthVerificationController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MedicalCenterWalletController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecretaryController;
use App\Http\Middleware\ApiAuthMiddleware;
use App\Models\Doctor;
use App\Models\TimeSlot;


Auth::routes(['verify'=>true]);

// Public Routes (No Authentication Required)
Route::post('/register', [AuthController::class, 'register']); //tested
Route::post('/login', [AuthController::class, 'login']); //tested
Route::post('/forgot_password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetLink']);
Route::post('/reset_password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword']);







/////////teeeeeeeesssssstttttttt////////////////
//EmailVerification
Route::post('user/email/check',[EmailVerificationController::class,'userCheckCode']);
Route::post('resendcode',[EmailVerificationController::class,'resendCode']);



Route::post('user/password/email',[ResetPasswordController::class,'userForgotPassword']);
Route::post('user/password/check', [ResetPasswordController::class, 'userCheckCode']);
Route::post('user/password/reset',[ResetPasswordController::class,'userResetPassword']);
Route::post('user/password/resendcode', [ResetPasswordController::class, 'resendCode']);

////////teeeeeeesssssstttttttt


Route::middleware(['auth:api', ApiAuthMiddleware::class])->group(function () {
    Route::post('/change_password', [AuthController::class, 'changePassword']); //tested

    Route::get('/user', [AdminController::class, 'authUser']); // tested

    Route::post('/logout', [AuthController::class, 'logout']); //tested



    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        Route::put('/doctors/{doctor}/admin_update', [AdminController::class, 'updateDoctor']); // tested
        Route::delete('/doctors/{doctor}', [AdminController::class, 'deleteDoctor']); //tested
        Route::get('/medical_center_wallet', [MedicalCenterWalletController::class, 'show']); //tested

    });


    // global
    Route::get('/profile', [DoctorController::class, 'getProfile']); // tested
    Route::put('/profile', [DoctorController::class, 'updateProfile']);  //tested
    Route::get('/profile_picture', [DoctorController::class, 'getProfilePicture']); // tested
    Route::post('/profile_picture', [DoctorController::class, 'uploadProfilePicture']); // teested

    // Schedule & Appointments
    Route::get('/schedule', [DoctorController::class, 'getSchedule']); // tested
    Route::get('/appointments', [DoctorController::class, 'getAppointments']); // tested
    Route::get('/time_slots', [DoctorController::class, 'getTimeSlots']); // tested
    Route::post('/doctor/appointments/emergency_cancel', [DoctorController::class, 'emergencyCancelAppointments']); // tested
    Route::get('/doctors/top', [DoctorController::class, 'getTopDoctors']); //tested
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show']); //tessted



    // general
    Route::middleware(['role:patient,secretary,admin'])->group(function () {

        Route::get('/search/clinics', [SearchController::class, 'searchClinics']); // tested
        Route::get('/search/doctors', [SearchController::class, 'searchDoctors']); // tested


        Route::prefix('patient')->group(function () {
            Route::get('/profile', [PatientController::class, 'getProfile']); //tessted
            Route::put('/profile', [PatientController::class, 'updateProfile']); //tested
            Route::post('/profile_picture', [PatientController::class, 'uploadProfilePicture']); //teested
            Route::get('/profile_picture', [PatientController::class, 'getProfilePicture']); // tested
            Route::get('/patient_transactions/{patient}', [WalletController::class, 'getTransactions']); // tested
            // hereeeeeeeeeeeee
            Route::post('/ratings', [RatingController::class, 'Rate']); //tested
            Route::get('appointments/history', [PatientController::class, 'getPatientHistory']); //tested
            Route::get('/appointments/{appointment}/reports', [PatientController::class, 'getAppointmentReports']); //tested

        });

        // days done

        //times done

        Route::get('/doctors/{doctor}/available_slots', [AppointmentController::class, 'getDoctorAvailableDaysWithSlots']); //tested
        Route::get('doctors/{doctor}/available_times/{date}', [AppointmentController::class, 'getAvailableTimes']); // tested

        Route::prefix('appointments')->group(function () {
            Route::get('/patient', [AppointmentController::class, 'getAppointments']); //tested
            Route::post('/', [AppointmentController::class, 'bookAppointment']); //tested
            Route::put('/{appointment}', [AppointmentController::class, 'updateAppointment']); //tested
            Route::delete('/{appointment}', [AppointmentController::class, 'cancelAppointment']); //tested
            Route::get('/available_slots/{doctor}/{date}', [AppointmentController::class, 'getAvailableSlots']); //tested
        });



        Route::get('/clinics/{clinic}/doctors', [ClinicController::class, 'getClinicDoctors']); //testeed
        Route::get('/clinics/{clinic}', [ClinicController::class, 'show']); //tested
        Route::get('/clinics', [ClinicController::class, 'index']);
        Route::get('/clinics/{clinic}/doctors-with-slots', [AppointmentController::class, 'getClinicDoctorsWithSlots']); //tested
        Route::get('/doctors/{doctor}', [AppointmentController::class, 'getDoctorDetails']); //tested

    });


    // Appointments
            Route::middleware(['role:secretary'])->group(function () {
        Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'rescheduleAppointment']); //tested
        Route::post('/appointments/{appointment}/refund', [AppointmentController::class, 'processWalletRefund']); //tested
        Route::get('secretary/appointments', [SecretaryController::class, 'getAppointments']); //tested
        Route::post('/patients', [SecretaryController::class, 'createPatient']); //tested
        Route::post('secbook/appointment', [SecretaryController::class, 'bookAppointment']); //tested
        Route::post('/appointments/{appointment}/cancel', [SecretaryController::class, 'cancelAppointment']); //tested

        // Wallet
        Route::get('/patients/{patient}/wallet', [SecretaryController::class, 'getPatientWalletInfo']); //testeed
        Route::post('/patients/{patient}/wallet/add', [SecretaryController::class, 'addToPatientWallet']); //tested

        // Blocked patients
        Route::get('/patients/blocked', [SecretaryController::class, 'listBlockedPatients']); //tested
        Route::post('/patients/{patient}/unblock', [SecretaryController::class, 'unblockPatient']); //tested

        // Wallet Management
        Route::prefix('wallet')->group(function () {
        Route::post('/add_funds', [WalletController::class, 'addFunds']); //tested

        Route::get('/medical_center_wallet/transactions', [MedicalCenterWalletController::class, 'transactions']); //tested //
        });


        // another try :
            Route::get('get_patients',[SecretaryController::class,'getPatients']);
            Route::post('secretary_book',[SecretaryController::class,'secretaryBookAppointment']);


    });


















        Route::middleware('role:doctor,secretary')->group(function () {
        Route::get('/search/patients', [SearchController::class, 'searchPatients']); // tested
        });






         Route::middleware(['role:secretary,patient'])->group(function () {

            // Wallet
            Route::prefix('wallet')->group(function () {
            Route::post('/setup', [WalletController::class, 'setupWallet']); //tested
            Route::get('/balance', [WalletController::class, 'getBalance']); //tested
            Route::get('/transactions', [WalletController::class, 'getTransactions']); //tested
            Route::post('/transfer', [WalletController::class, 'transferToClinic']); //later

            Route::post('change_pin', [WalletController::class, 'changePin']);
            });

           // Payments
           Route::get('/payments/history', [PaymentController::class, 'getPaymentHistory']); //tested
           Route::get('payments_info/{appointment_id}', [PaymentController::class, 'PaymentInfo']);
         }); //tested




                Route::middleware(['role:doctor'])->group(function () {
            Route::prefix('doctor')->group(function () {
            Route::patch('appointments/{appointment}/absent', [doctorController::class, 'markAsAbsent']); // tested
            Route::patch('appointments/{appointment}/complete', [DoctorController::class, 'markAsCompleted']); // tested
            Route::get('specific/patients', [DoctorController::class, 'getDoctorSpecificPatients']); // tested
            Route::get('patients/{patient}/profile', [DoctorController::class, 'getPatientDetails']); // tested
            Route::get('patients/{patient}/documents', [DoctorController::class, 'getPatientDocuments']); //tested
            Route::get('documents/{document}/prescriptions', [DoctorController::class, 'getPatientReport']); //tested
            Route::post('/appointments/{appointment}/reports', [DoctorController::class, 'SubmitMedicalReport']); //tessted
            Route::post('/reports/{report}/prescriptions', [DoctorController::class, 'addPrescriptions']); //tested

        });
    });












    Route::middleware(['role:admin'])->group(function () {
        // Clinics
        Route::post('/clinics', [AdminController::class, 'createClinic']); // tested
        Route::put('/clinics/{clinic}', [AdminController::class, 'updateclinic']);  // tested
        Route::post('/clinics/{clinic}/upload_icon', [AdminController::class, 'uploadClinicIcon']); //tested

        Route::get('/search/secretaries', [SearchController::class, 'searchSecretaries']); // tested
        Route::get('statistics', [AdminController::class, 'statistics']); // tested
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////

        //////////////////////////    clinic          ///////////////////////////////
        Route::post('/addClinic', [AdminController::class, 'addClinic']);
        Route::get('/allClinics', [AdminController::class, 'allClinics']); //tested
        Route::get('/gitClinicById/{clinic_id}', [AdminController::class, 'gitClinicById']); //tested
        Route::post('/editClinic/{clinic_id}', [AdminController::class, 'editClinic']); //tested
        Route::post('/deleteClinic/{clinic_id}', [AdminController::class, 'deleteClinic']); //tested

        //////////////////////////    doctors          ///////////////////////////////
        Route::get('/allDoctors', [AdminController::class, 'allDoctors']); //tested
        Route::get('/DoctorInfo/{doctor_id}', [AdminController::class, 'DoctorInfo']);
        Route::post('/editDoctor/{doctor_id}', [AdminController::class, 'editDoctor']); // tested
        Route::post('admin/create_doctor', [AdminController::class, 'createDoctor']);  // TESTED
        //////////////////////////    secretary          ///////////////////////////////
        Route::post('/admin/create_secretary', [AdminController::class, 'createSecretary']); // tested
        Route::get('/getSecretaryById/{id}', [AdminController::class, 'getSecretaryById']); // tested
        Route::post('/secretaries/update/{id}', [AdminController::class, 'updateSecretary']); // tested

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////


        Route::post('/doctors/{doctor}/generate_timeslots', [AdminController::class, 'generateTimeSlotsForDoctor']);

        Route::post('/doctors/{doctor}/generate_timeslots', [AdminController::class, 'generateTimeSlots']);

        // Wallet Reports
        Route::prefix('admin/wallet')->group(function () {
            Route::get('/transactions', [AdminController::class, 'getWalletTransactions']); // tested
            Route::get('/income_report', [AdminController::class, 'getClinicIncomeReport']); // tested
        });



        Route::prefix('admin/profile')->group(function () {
            Route::post('/updateAdminInfo', [AdminController::class, 'updateAdminInfo']);  // tested

            //   Route::get('/picture', [AdminController::class, 'getProfilePicture']);
            Route::post('/picture', [AdminController::class, 'uploadProfilePicture']); // tested
            Route::delete('/picture', [AdminController::class, 'deleteProfilePicture']); // tested
        });
        Route::get('/picture', [AdminController::class, 'getProfilePictureFile']); // tested


    });
});
