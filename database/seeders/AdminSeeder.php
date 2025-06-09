<?php

namespace Database\Seeders;

use App\Models\{Appointment, Clinic, DoctorSchedule, User, Doctor, Patient, Role, Secretary, Specialty, TimeSlot};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            'admin' => Role::firstOrCreate(['name' => 'admin']),
            'doctor' => Role::firstOrCreate(['name' => 'doctor']),
            'secretary' => Role::firstOrCreate(['name' => 'secretary']),
            'patient' => Role::firstOrCreate(['name' => 'patient'])
        ];

        // Create clinic
        $clinic = Clinic::create([
            'name' => 'Central Clinic',
            'location' => '123 Health St',
            'opening_time' => '09:00',
            'closing_time' => '19:00'
        ]);



            \App\Models\Service::create([
        'name' => 'General Consultation',
        'price' => 50.00,
    ]);

    \App\Models\Service::create([
        'name' => 'Specialist Consultation',
        'price' => 100.00,
    ]);

        // Create patient
        $patientUser = User::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@example.com',
            'password' => Hash::make('password'),
            'role_id' => $roles['patient']->id,
        ]);

        $patient = Patient::create([
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
            'role_id' => $roles['admin']->id,
        ]);

        // Create doctor
        $doctorUser = User::create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'doctor@example.com',
            'password' => Hash::make('password'),
            'role_id' => $roles['doctor']->id,
        ]);

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
            'role_id' => $roles['secretary']->id,
        ]);

        Secretary::create([
            'user_id' => $secretaryUser->id,
            'salary' => 4000,
            'workdays' => json_encode(['Sunday', 'Monday', 'Tuesday']),
        ]);

        // Create time slots for the next 7 days
        $timeSlots = [];
        $appointmentDate = now()->addDays(7)->format('Y-m-d');

        // Create time slots (morning and afternoon)
        $morningSlots = [
            ['09:00:00', '10:00:00'],
            ['10:00:00', '11:00:00'],
            ['11:00:00', '12:00:00']
        ];

        $afternoonSlots = [
            ['13:00:00', '14:00:00'],
            ['14:00:00', '15:00:00'],
            ['15:00:00', '16:00:00']
        ];

        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');

            foreach ($morningSlots as $slot) {
                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            foreach ($afternoonSlots as $slot) {
                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Insert all time slots at once for better performance
        TimeSlot::insert($timeSlots);

        // Get a specific time slot for the appointment
        $appointmentSlot = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $appointmentDate)
            ->where('start_time', '09:00:00')
            ->first();

        // Mark the slot as booked
        if ($appointmentSlot) {
            $appointmentSlot->update(['is_booked' => true]);
        }

        // Create appointment
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'time_slot_id' => $appointmentSlot->id ?? null,
            'appointment_date' => $appointmentDate . ' 09:00:00',
            'reason' => 'Initial consultation',
            'status' => 'confirmed',
            'price' => 100.00,
            'fee' => 80.00 // Don't forget the fee field
        ]);


            Specialty::create(['name' => 'Ophthalmology', 'description' => 'Eye care specialists']);
    Specialty::create(['name' => 'Dermatology', 'description' => 'Skin care specialists']);
    Specialty::create(['name' => 'Oncology', 'description' => 'Cancer treatment specialists']);
    }
}
