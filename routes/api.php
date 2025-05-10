<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Middleware\ApiAuthMiddleware;





Route::middleware(['auth:api', 'role:admin'])->group(function () {  // not created yet
    Route::post('/create-doctor', [AdminController::class, 'createDoctor']);
});







Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink']); // not created yet
Route::post('/reset-password', [AuthController::class, 'resetPassword']);// not created yet








//patient authentication :

Route::post('/register', [AuthController::class, 'register']); // tested successfully
Route::post('/login', [AuthController::class, 'login']); // tested successfully




// All of Patient  Management methods have been tested successfully
Route::middleware(['auth:api',ApiAuthMiddleware::class])->group(function () {
    // Patient Profile
    Route::get('/patient/profile', [PatientController::class, 'getProfile']);
    Route::put('/patient/profile', [PatientController::class, 'updateProfile']);

Route::get('/patient/clinics',[PatientController::class,'getClinics']);
Route::get('/patient/clinics/{id}/doctors',[PatientController::class,'getClinicDoctors']);


Route::get('/doctor/{id}/schedule', [PatientController::class, 'getDoctorSchedules']);


});






Route::middleware(['auth:api', 'role:doctor'])->group(function () {
    Route::get('/doctor/availability', [DoctorController::class, 'getAvailability']);// not created yet
});








// Appointments
Route::middleware(['auth:api'])->group(function () {// tested successfully

Route::get('/patient/appointments', [PatientController::class, 'getAppointments']);
    Route::post('/patient/appointments', [PatientController::class, 'createAppointment']);
    Route::put('/patient/appointments/{id}', [PatientController::class, 'updateAppointment']);
    Route::delete('/patient/appointments/{id}', [PatientController::class, 'cancelAppointment']);










      // Medical Records
      Route::get('/patient/medical-history', [PatientController::class, 'getMedicalHistory']);// tested successfully
      Route::get('/patient/prescriptions', [PatientController::class, 'getPrescriptions']);// not tested yet
Route::post('/patients/documents',[PatientController::class,'uploadDocument']);// not tested yet
      // Payments
      Route::get('/patient/payments', [PatientController::class, 'getPaymentHistory']);// not created yet
      Route::post('/patient/payments', [PatientController::class, 'makePayment']);// tested successfully

      // Notifications
      Route::get('/patient/notifications', [PatientController::class, 'getNotifications']); // not tested yet
      Route::put('/patient/notifications/{id}', [PatientController::class, 'markNotificationAsRead']); // not tested yet
});
