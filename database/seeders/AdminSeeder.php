<?php
namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DoctorSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Secretary;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name','admin')->first();
        $doctorRole = Role::where('name','doctor')->first();
        $secretaryRole = Role::where('name','secretary')->first();
        $patientRole = Role::where('name','patient')->first();

        // Create clinic first
        $clinic = Clinic::create([
            'name' => 'Central Clinic',
            'location' => '123 Health St',
            'opening_time'=> '09:00',
            'closing_time'=>'19:00'
        ]);

        // Create patient
        $patientUser = User::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@example.com',
            'password' => Hash::make('password'),
            'role_id' => $patientRole->id,
        ]);

        Patient::create([
            'user_id' => $patientUser->id,
            'phone_number' => '1234567890',
            'date_of_birth' => '1990-01-01',
            'address' => '123 Test St',
            'gender' => 'male',
            'blood_type' => 'O+',
            'emergency_contact' => '9876543210'
        ]);

        // Create admin
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
        ]);

        // Create doctor user
        $doctorUser = User::create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'doctor@example.com',
            'password' => Hash::make('password'),
            'role_id' => $doctorRole->id,
        ]);

        // Create doctor record
        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'clinic_id' => $clinic->id,
            'specialty' => 'Cardiology',
            'workdays' => json_encode(['Monday', 'Wednesday', 'Friday']),
        ]);

        // Create doctor schedules
        $scheduleData = [
            ['day' => 'monday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['day' => 'wednesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['day' => 'friday', 'start_time' => '09:00:00', 'end_time' => '17:00:00']
        ];

        foreach ($scheduleData as $schedule) {
            DoctorSchedule::create(array_merge($schedule, ['doctor_id' => $doctor->id]));
        }

        // Create secretary
        $secretaryUser = User::create([
            'first_name' => 'Sara',
            'last_name' => 'Secretary',
            'email' => 'secretary@example.com',
            'password' => Hash::make('password'),
            'role_id' => $secretaryRole->id,
        ]);

        Secretary::create([
            'user_id' => $secretaryUser->id,
            'salary' => 4000,
            'workdays' => json_encode(['Sunday', 'Monday', 'Tuesday']),
        ]);


        $appointment = Appointment::create([
            'patient_id' => $patientUser->patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'appointment_date' => now()->addDays(7),
            'reason' => 'Initial consultation',
            'status' => 'confirmed',
            'price' => 100.00
        ]);
    }



}
