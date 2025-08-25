<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalCenterWallet;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Salary;
use App\Models\SalarySetting;
use App\Models\Secretary;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WalletTransaction;
use Auth;
use Carbon\Carbon;
use DB;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Storage;
use Str;
use Validator;

class AdminController extends Controller
{
    public function authUser()
    {
        return Auth::user();
    }

    protected $profilePictureConfig = [
        'directory' => 'admin_profile_pictures',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size' => 3072, // 3MB
    ];

    // not tested yet until another time
    public function getClinicIncomeReport(Request $request)
    {
        $validated = $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from'
        ]);

        $query = Payment::query();

        if ($request->has('from')) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $report = $query->selectRaw('
            method,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_no
        ')
            ->groupBy('method')
            ->get();

        return response()->json($report);
    }

    // optional and not tested yet :


    // في كل شي بخص الصور عملتو image_path



    public function getWalletTransactions(Request $request)
    {
        $transactions = WalletTransaction::with(['patient.user', 'admin'])
            ->when($request->has('type'), function ($q) use ($request) {
                return $q->where('type', $request->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }




    public function createClinic(Request $request)
    {
        // هون ما بعرف اذا بدك تحط انو يتأكد انه ادمن


        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $clinic = Clinic::create($validated);

        if ($request->hasFile('image_path')) {
            $clinic->uploadIcon($request->file('image_path'));
        }

        return response()->json($clinic, 201);
    }


    public function updateclinic(Request $request, $id)
    {
        $clinic = Clinic::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i|after:opening_time',
            'icon' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('icon')) {
            try {
                $clinic->uploadIcon($request->file('icon'));
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to upload clinic icon',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $clinic->update($validator->validated());

        return response()->json([
            'clinic' => $clinic,
            'icon_url' => $clinic->getIconUrl(),
            'message' => 'Clinic updated successfully'
        ]);
    }

    public function uploadClinicIcon(Request $request, $id)
    {


        $clinic = Clinic::find($id);
        if (!$clinic) {
            return response()->json(['error' => 'Clinic not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'icon' => 'required|image|mimes:jpg,jpeg,png|max:2048' // Changed from image_path to icon
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $clinic = Clinic::findOrFail($id);

        try {
            if ($clinic->uploadIcon($request->file('icon'))) {
                return response()->json([
                    'success' => true,
                    'image_url' => $clinic->getIconUrl(),
                    'message' => 'Clinic image updated successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to save clinic image'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed: ' . $e->getMessage()
            ]);
        }
    }





    // did not serve the system, this function has two separates fucntion , one is for creating the doctor and the other is for generqating slots . so in  the next function i will do it unified
    //     public function createDoctor(Request $request)
    //     {
    //         // Validate the request
    //         $validator = Validator::make($request->all(), [
    //             // User data
    //             'first_name' => 'required|string|max:255',
    //             'last_name' => 'required|string|max:255',
    //             'email' => 'required|email|unique:users,email',
    //             'phone_number' => 'required|string|max:20',

    //             // Doctor data
    //             'specialty' => 'required|string|max:255',
    //             'bio' => 'nullable|string',
    //             'consultation_fee' => 'required|numeric|min:0',
    //             'experience_years' => 'required|integer|min:0',
    //             'clinic_id' => 'required|exists:clinics,id',

    //             // Schedule data
    //             'schedules' => 'required|array|min:1',
    //             'schedules.*.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
    //             'schedules.*.start_time' => 'required|date_format:H:i',
    //             'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

    //             // Time slot configuration
    //             'slot_duration' => 'required|integer|in:30,60',
    //             'generate_slots_for_days' => 'required|integer|min:1|max:365',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['errors' => $validator->errors()], 422);
    //         }

    //         try {
    //             // Start transaction
    //             return DB::transaction(function () use ($request) {
    //                 // Get the doctor role
    //                 $doctorRole = Role::where('name', 'doctor')->first();
    //                 if (!$doctorRole) {
    //                     throw new \Exception('Doctor role not found in database');
    //                 }

    //                 // Get a secretary for salary assignment
    //                 $secretary = Secretary::first();
    //                 if (!$secretary) {
    //                     throw new \Exception('No secretary found in database');
    //                 }

    //                 // Verify clinic exists
    //                 $clinic = Clinic::find($request->clinic_id);
    //                 if (!$clinic) {
    //                     throw new \Exception('Specified clinic not found');
    //                 }

    //                 // Create salary settings and salary record
    //                 $salarySettings = SalarySetting::firstOrCreate([]);
    //                 $salary = Salary::create([
    //                     'secretary_id' => $secretary->id,
    //                     'base_amount' => 100,
    //                     'bonus_amount' => 0.5,
    //                     'total_amount' => 100.5,
    //                     'salary_setting_id' => $salarySettings->id,
    //                     'status' => 'pending'
    //                 ]);

    //                 // Create user account
    //                 $user = User::create([
    //                     'first_name' => $request->first_name,
    //                     'last_name' => $request->last_name,
    //                     'email' => $request->email,
    //                     'password' => Hash::make('temporary_password'),
    //                     'role_id' => $doctorRole->id,
    //                     'gender'=>$request->gender,
    //                 ]);

    //                 // Create doctor profile
    //                 $doctor = Doctor::create([
    //                     'user_id' => $user->id,
    //                     'clinic_id' => $request->clinic_id,
    //                     'specialty' => $request->specialty,
    //                     'bio' => $request->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
    //                     'consultation_fee' => $request->consultation_fee,
    //                     'experience_years' => $request->experience_years,
    //                     'salary_id' => $salary->id,
    //                     'workdays' => collect($request->schedules)->pluck('day')->toArray()
    //                 ]);

    //                 // Create schedules
    //                 foreach ($request->schedules as $scheduleData) {
    //                     DoctorSchedule::create([
    //                         'doctor_id' => $doctor->id,
    //                         'day' => $scheduleData['day'],
    //                         'start_time' => $scheduleData['start_time'],
    //                         'end_time' => $scheduleData['end_time']
    //                     ]);
    //                 }
    //  $this->generateTimeSlotsForDoctor(
    //                 $doctor,
    //                 $request->generate_slots_for_days,
    //                 $request->slot_duration
    //             );
    //                 // Generate time slots
    //               /*   $timeSlots = [];
    //                 $slotDuration = $request->slot_duration;

    //                 for ($i = 1; $i <= $request->generate_slots_for_days; $i++) {
    //                     $date = now()->addDays($i)->format('Y-m-d');
    //                     $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

    //                     $schedule = $doctor->schedules()->where('day', $dayOfWeek)->first();
    //                     if (!$schedule) continue;

    //                     $start = Carbon::parse($schedule->start_time);
    //                     $end = Carbon::parse($schedule->end_time);

    //                     $current = $start->copy();
    //                     while ($current->addMinutes($slotDuration)->lte($end)) {
    //                         $slotStart = $current->copy()->subMinutes($slotDuration);
    //                         $slotEnd = $current->copy();

    //                         $timeSlots[] = [
    //                             'doctor_id' => $doctor->id,
    //                             'date' => $date,
    //                             'start_time' => $slotStart->format('H:i:s'),
    //                             'end_time' => $slotEnd->format('H:i:s'),
    //                             'is_booked' => false,
    //                             'created_at' => now(),
    //                             'updated_at' => now()
    //                         ];
    //                     }
    //                 } */

    //                 // Insert time slots if any were generated
    //                 if (!empty($timeSlots)) {
    //                     TimeSlot::insert($timeSlots);
    //                 }

    //                 // Refresh the doctor model with relationships
    //                 $doctor = $doctor->fresh(['user', 'clinic', 'schedules']);

    //                 return response()->json([
    //                     'message' => 'Doctor created successfully',
    //                     'doctor' => $doctor,
    //                     'login_credentials' => [
    //                         'email' => $user->email,
    //                     ]
    //                 ], 201);
    //             });
    //         } catch (\Exception $e) {
    //             return response()->json([
    //                 'message' => 'Failed to create doctor',
    //                 'error' => $e->getMessage()
    //             ], 500);
    //         }
    //     }


    public function createDoctor(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:20',
            'gender' => 'required|in:male,female',

            'specialty' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'consultation_fee' => 'required|numeric|min:0',
            'experience_years' => 'required|integer|min:0',
            'clinic_id' => 'required|exists:clinics,id',

            'schedules' => 'required|array|min:1',
            'schedules.*.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

            'slot_duration' => 'required|integer|in:30,60',
            'generate_slots_for_days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {

            return DB::transaction(function () use ($request) {
                $doctorRole = Role::where('name', 'doctor')->first();
                if (!$doctorRole) {
                    throw new \Exception('Doctor role not found in database');
                }

                $secretary = Secretary::first();
                if (!$secretary) {
                    throw new \Exception('No secretary found in database');
                }

                $clinic = Clinic::find($request->clinic_id);
                if (!$clinic) {
                    throw new \Exception('Specified clinic not found');
                }

                $salarySettings = SalarySetting::firstOrCreate([]);
                $salary = Salary::create([
                    'secretary_id' => $secretary->id,
                    'base_amount' => 100,
                    'bonus_amount' => 0.5,
                    'total_amount' => 100.5,
                    'salary_setting_id' => $salarySettings->id,
                    'status' => 'pending'
                ]);

                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make('temporary_password'),
                    'role_id' => $doctorRole->id,
                    'phone' => $request->phone_number,
                    'gender' => $request->gender,
                ]);

                $doctor = Doctor::create([
                    'user_id' => $user->id,
                    'clinic_id' => $request->clinic_id,
                    'specialty' => $request->specialty,
                    'bio' => $request->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
                    'consultation_fee' => $request->consultation_fee,
                    'experience_years' => $request->experience_years,
                    'salary_id' => $salary->id,
                    'workdays' => collect($request->schedules)->pluck('day')->toArray()
                ]);

                foreach ($request->schedules as $scheduleData) {
                    DoctorSchedule::create([
                        'doctor_id' => $doctor->id,
                        'day' => $scheduleData['day'],
                        'start_time' => $scheduleData['start_time'],
                        'end_time' => $scheduleData['end_time']
                    ]);
                }

                $this->generateTimeSlotsForDoctor(
                    $doctor,
                    $request->generate_slots_for_days,
                    $request->slot_duration
                );

                $doctor = $doctor->fresh(['user', 'clinic', 'schedules']);

                return response()->json([
                    'message' => 'Doctor created successfully',
                    'doctor' => $doctor,
                    'login_credentials' => [
                        'email' => $user->email,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create doctor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editDoctor(Request $request, $doctorId)
    {
        $doctor = Doctor::with('user')->find($doctorId);
        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found'], 404);
        }

        \Log::info('Edit Doctor Request Data:', $request->all());

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'unique:users,email,' . $doctor->user_id
            ],
            'phone_number' => 'sometimes|string|max:20',
            'gender' => 'sometimes|in:male,female',

            'specialty' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'consultation_fee' => 'sometimes|numeric|min:0',
            'experience_years' => 'sometimes|integer|min:0',
            'clinic_id' => 'sometimes|exists:clinics,id',

            'schedules' => 'sometimes|array|min:1',
            'schedules.*.day' => 'required_with:schedules|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',


            'slot_duration' => 'sometimes|integer|in:30,60',
            'generate_slots_for_days' => 'sometimes|integer|min:1|max:365',

            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed for edit doctor:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        try {
            return DB::transaction(function () use ($request, $doctor, $validated) {
                if (
                    isset($validated['first_name']) ||
                    isset($validated['last_name']) ||
                    isset($validated['email']) ||
                    isset($validated['phone_number']) ||
                    isset($validated['gender'])
                ) {

                    $userData = [];
                    if (isset($validated['first_name'])) $userData['first_name'] = $validated['first_name'];
                    if (isset($validated['last_name'])) $userData['last_name'] = $validated['last_name'];
                    if (isset($validated['email'])) $userData['email'] = $validated['email'];
                    if (isset($validated['phone_number'])) $userData['phone'] = $validated['phone_number']; // تحويل إلى phone
                    if (isset($validated['gender'])) $userData['gender'] = $validated['gender'];

                    \Log::info('Updating user data:', $userData);
                    $doctor->user->update($userData);
                }

                $doctorData = collect($validated)
                    ->except(['first_name', 'last_name', 'email', 'phone_number', 'gender', 'schedules'])
                    ->toArray();

                if (isset($validated['schedules'])) {
                    $doctorData['workdays'] = collect($validated['schedules'])->pluck('day')->toArray();
                }

                if (!empty($doctorData)) {
                    \Log::info('Updating doctor data:', $doctorData);
                    $doctor->update($doctorData);
                }

                if (isset($validated['schedules'])) {
                    \Log::info('Updating schedules:', $validated['schedules']);

                    $doctor->schedules()->delete();

                    foreach ($validated['schedules'] as $schedule) {
                        $doctor->schedules()->create([
                            'day' => $schedule['day'],
                            'start_time' => $schedule['start_time'],
                            'end_time' => $schedule['end_time'],
                        ]);
                    }

                    $doctor->timeSlots()->where('date', '>=', now()->format('Y-m-d'))->delete();

                    $slotDuration = $validated['slot_duration'] ?? 60;
                    $generateDays = $validated['generate_slots_for_days'] ?? 30;

                    $this->generateTimeSlotsForDoctor($doctor, $generateDays, $slotDuration);
                }

                return response()->json([
                    'message' => 'Doctor updated successfully',
                    'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Error updating doctor: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update doctor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateTimeSlotsForDoctor(Doctor $doctor, $daysToGenerate, $slotDuration)
    {
        $timeSlots = [];
        $now = Carbon::now('Asia/Damascus'); // this is time zone for calculating sensitive date choices in emad's interface

        \Log::info("Starting slot generation for doctor {$doctor->id}");

        $schedules = $doctor->schedules()->get();

        if ($schedules->isEmpty()) {
            \Log::error("No schedules found for doctor {$doctor->id}");
            return 0;
        }

        for ($i = 1; $i <= $daysToGenerate; $i++) {
            $date = $now->copy()->addDays($i);
            $dayName = strtolower($date->englishDayOfWeek);

            $schedule = $schedules->firstWhere('day', $dayName);
            if (!$schedule) {
                \Log::info("No schedule for {$dayName}, skipping");
                continue;
            }

            $dateStr = $date->format('Y-m-d');
            $start = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$dateStr} {$schedule->start_time}",
                'Asia/Damascus'
            );
            $end = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$dateStr} {$schedule->end_time}",
                'Asia/Damascus'
            );

            \Log::info("Processing {$dateStr} ({$dayName}) from {$start} to {$end}");

            // Generate slots
            $current = $start->copy();
            while ($current->addMinutes($slotDuration)->lte($end)) {
                $slotStart = $current->copy()->subMinutes($slotDuration);
                $slotEnd = $current->copy();

                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $dateStr,
                    'start_time' => $slotStart->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                \Log::debug("Generated slot: {$slotStart->format('H:i')} - {$slotEnd->format('H:i')}");
            }
        }

        if (!empty($timeSlots)) {
            try {
                \Log::info("Inserting " . count($timeSlots) . " slots for doctor {$doctor->id}");
                TimeSlot::insert($timeSlots);
                return count($timeSlots);
            } catch (\Exception $e) {
                \Log::error("Failed to insert slots: " . $e->getMessage());
                return 0;
            }
        }

        \Log::warning("No slots generated for doctor {$doctor->id}");
        return 0;
    }



    public function generateTimeSlots(Doctor $doctor, $daysToGenerate = 30, $slotDuration = 30)
    {
        $timeSlots = [];
        $now = Carbon::now();
        $generatedDays = 0;

        \Log::info("Starting generation for {$daysToGenerate} days with {$slotDuration}min slots");

        for ($i = 0; $i < $daysToGenerate && $generatedDays < $daysToGenerate; $i++) {
            $date = $now->copy()->addDays($i);
            $dayName = strtolower($date->englishDayOfWeek);

            if (!$doctor->schedules->where('day', $dayName)->count()) {
                \Log::info("Skipping {$date->format('Y-m-d')} ({$dayName}): No schedule");
                continue;
            }

            $schedule = $doctor->schedules->where('day', $dayName)->first();
            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);

            if ($date->isToday()) {
                $currentTime = $now->copy()->setTimezone('UTC');
                if ($currentTime > $start) {
                    $start = $currentTime->addMinutes($slotDuration - ($currentTime->minute % $slotDuration));
                }
            }

            $current = $start->copy();
            while ($current->addMinutes($slotDuration)->lte($end)) {
                $slotStart = $current->copy()->subMinutes($slotDuration);
                $slotEnd = $current->copy();

                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $slotStart->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            $generatedDays++;
            \Log::info("Generated slots for {$date->format('Y-m-d')}: " . count($timeSlots));
        }

        if (!empty($timeSlots)) {
            TimeSlot::insert($timeSlots);
            return count($timeSlots);
        }

        return 0;
    }

    // this below was not connected well with relation
    //     public function generateTimeSlotsForDoctor(Doctor $doctor, $daysToGenerate, $slotDuration)
    // {
    //     $timeSlots = [];
    //     $now = Carbon::now();
    //     $startDate = $now->copy()->startOfDay();

    //     \Log::info("Generating slots for doctor {$doctor->id} for {$daysToGenerate} days");

    //     for ($i = 0; $i < $daysToGenerate; $i++) {
    //         $date = $startDate->copy()->addDays($i);
    //         $dayName = strtolower($date->englishDayOfWeek);

    //         $schedule = $doctor->schedules()->where('day', $dayName)->first();
    //         if (!$schedule) {
    //             \Log::info("No schedule for {$dayName} on {$date->format('Y-m-d')}");
    //             continue;
    //         }

    //         $dateStr = $date->format('Y-m-d');
    //         $start = Carbon::parse($schedule->start_time);
    //         $end = Carbon::parse($schedule->end_time);

    //         // Adjust for today: only generate future slots
    //         if ($date->isToday()) {
    //             $currentTime = $now->copy();
    //             // Only adjust if current time is within working hours
    //             if ($currentTime->between($start, $end)) {
    //                 $start = $currentTime;
    //             }
    //         }

    //         // Generate slots
    //         $current = $start->copy();
    //         $slotsCount = 0;

    //         while ($current->addMinutes($slotDuration)->lte($end)) {
    //     $slotStart = $current->copy()->subMinutes($slotDuration);
    //     $slotEnd = $current->copy();

    //             // Skip past slots for today
    //             if ($date->isToday() && $slotEnd->lte($now)) {
    //                 continue;
    //             }

    //             $timeSlots[] = [
    //                 'doctor_id' => $doctor->id,
    //                 'date' => $dateStr,
    //                 'start_time' => $slotStart->format('H:i:s'),
    //                 'end_time' => $slotEnd->format('H:i:s'),
    //                 'is_booked' => false,
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ];
    //             $slotsCount++;
    //         }

    //         \Log::info("Generated {$slotsCount} slots for {$dateStr} ({$dayName})");
    //     }

    //     if (!empty($timeSlots)) {
    //         try {
    //             \Log::info("Inserting ".count($timeSlots)." slots for doctor {$doctor->id}");
    //             TimeSlot::insert($timeSlots);
    //             return count($timeSlots);
    //         } catch (\Exception $e) {
    //             \Log::error("Failed to insert slots: ".$e->getMessage());
    //             return 0;
    //         }
    //     }

    //     \Log::warning("No slots generated for doctor {$doctor->id}");
    //     return 0;
    // }



    public function updateDoctor(Request $request, Doctor $doctor)
    {
        $validator = Validator::make($request->all(), [

            'email' => [
                'sometimes',
                'email',
                'unique:users,email,' . $doctor->user_id
            ],
            'phone_number' => 'sometimes|string|max:20',

            'specialty' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'consultation_fee' => 'sometimes|numeric|min:120',
            'experience_years' => 'sometimes|integer|min:1',
            'clinic_id' => 'sometimes|exists:clinics,id',
            'workdays' => 'sometimes|array',
            'workdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',

            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($request, $doctor, $validated) {
            if (
                isset($validated['email']) || isset($validated['phone_number'])
            ) {

                $userData = [
                    'email' => $validated['email'] ?? $doctor->user->email,
                    'phone_number' => $validated['phone_number'] ?? $doctor->user->phone_number,
                ];

                $doctor->user->update($userData);
            }

            $doctorData = collect($validated)
                ->except(['first_name', 'last_name', 'email', 'phone_number'])
                ->toArray();

            $doctor->update($doctorData);

            return response()->json([
                'message' => 'Doctor updated successfully',
                'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
            ]);
        });
    }

    public function deleteDoctor(Doctor $doctor)
    {

        DB::transaction(function () use ($doctor) {
            $doctor->delete();
        });

        return response()->json([
            'message' => 'Doctor deleted successfully. Existing appointments remain intact.',
            'deleted_at' => now()->toDateTimeString()
        ]);
    }






    // Change admin password

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $admin = Auth::user();

        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 401);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    //  هون ما استعملت  trait  التوابع تبع الصور منون و فيون


    public function uploadProfilePicture(Request $request)
    {
        $config = $this->profilePictureConfig;

        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:' . implode(',', $config['allowed_types']) .
                '|max:' . $config['max_size']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $file = $request->file('profile_picture');

        try {
            if ($user->profile_picture) {
                $this->deleteProfilePicture();
            }

            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $directory = trim($config['directory'], '/');

            $path = $file->storeAs($directory, $filename, 'public');

            $user->profile_picture = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'profile_picture_url' => $this->getProfilePictureUrl($user),
                'message' => 'Profile picture updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getProfilePicture()
    {
        $user = Auth::user();

        if (!$user->profile_picture) {
            return response()->json(['message' => 'No profile picture set'], 404);
        }

        try {
            $url = $this->getProfilePictureUrl($user);

            if (!$url) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }

            return response()->json([
                'profile_picture_url' => $url,
                'message' => 'Profile picture retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function deleteProfilePicture()
    {
        $user = Auth::user();

        try {
            if ($this->deleteProfilePictureFile($user)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No profile picture to delete'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getProfilePictureFile()
    {
        $user = Auth::user();

        if (!$user->profile_picture) {
            return response()->json(['message' => 'No profile picture set'], 404);
        }

        $path = storage_path('app/public/' . $user->profile_picture);

        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->file($path);
    }



    private function getProfilePictureUrl(User $user)
    {
        if (!$user->profile_picture) {
            return null;
        }

        $path = $user->profile_picture;

        if (Storage::disk('public')->exists($path)) {
            return asset('storage/' . $path);
        }

        return null;
    }

    private function deleteProfilePictureFile(User $user)
    {
        if (!$user->profile_picture) {
            return false;
        }

        $path = $user->profile_picture;

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $user->profile_picture = null;
        $user->save();

        return true;
    }
























    ///////////////////////////////////////////////////////////routs by alaa ////////////////////////////////////////////////////////////////
    public function addClinic(Request $request)
    {
        $attr = $request->validate([
            'name' => 'required|string',
            'specialty' => 'nullable|string',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('uploads', 'public');
            $imageUrl = asset('storage/' . $path);
        } else {
            $imageUrl = null;
        }


        $clinic = Clinic::create([
            'name' => $attr['name'],
            'specialty' => $attr['specialty'] ?? null,
            'location' => $attr['location'] ?? null,
            'description' => $attr['description'] ?? null,
            'image_path' => $imageUrl,
        ]);

        return response()->json([
            'message' => 'The clinic and image were created successfully',
            'clinic' => $clinic,
        ], 200);
    }

    public function editClinic(Request $request, $clinic_id)
    {
        $clinic = Clinic::findOrFail($clinic_id);

        $attr = $request->validate([
            'name' => 'required|string',
            'specialty' => 'nullable|string',
            'location' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',
        ]);


        if ($request->hasFile('image')) {
            if ($clinic->image_path) {
                $oldPath = str_replace('/storage/', '', parse_url($clinic->image_path, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('uploads', 'public');
            $imageUrl = asset('storage/' . $path);
        } else {
            $imageUrl = $clinic->image_path;
        }

        $clinic->update([
            'name' => $attr['name'],
            'specialty' => $attr['specialty'] ?? $clinic->specialty,
            'location' => $attr['location'] ?? $clinic->location,
            'description' => $attr['description'] ?? $clinic->description,
            'image_path' => $imageUrl,
        ]);

        return response()->json([
            'message' => 'The clinic was updated successfully',
            'clinic' => $clinic,
        ], 200);
    }

    public function deleteClinic($clinic_id)
    {
        $Clinic = Clinic::find($clinic_id);
        if (!$Clinic) {
            return response()->json(['message' => 'Clinic is not found'], 404);
        }
        $Clinic->delete();

        return response()->json(['message' => ' deleted successfully'], 200);
    }
    public function allClinics(Request $request)
    {
        $limit = $request->get('limit', 5);
        $clinics = Clinic::paginate($limit);

        if ($clinics->isEmpty()) {
            return response()->json(['message' => 'No clinics found'], 404);
        }

        return response()->json([
            'clinics' => $clinics->items(),
            'total' => $clinics->total(),
            'current_page' => $clinics->currentPage(),
            'last_page' => $clinics->lastPage(),
        ], 200);
    }

    public function gitClinicById($clinic_id)
    {
        $Clinic = Clinic::find($clinic_id);
        if (!$Clinic) {
            return response()->json(['message' => 'Clinic is not found'], 404);
        }


        return response()->json(["clinic" => $Clinic], 200);
    }


    ///////////    doctors          ////////////////////
    public function allDoctors(Request $request)
    {
        $limit = $request->get('limit', 5);

        $doctors = Doctor::with([
            'user',
            'clinic',
            'salary'
        ])->paginate($limit);

        if ($doctors->isEmpty()) {
            return response()->json(['message' => 'No doctors found'], 404);
        }

        return response()->json([
            'doctors' => $doctors->items(),
            'total' => $doctors->total(),
            'current_page' => $doctors->currentPage(),
            'last_page' => $doctors->lastPage(),
        ], 200);
    }

    public function DoctorInfo($doctor_id)
    {
        $doctor = Doctor::with([
            'user',
            'clinic',
            'salary',
            'schedules',
            'timeSlots'
        ])->where('id', $doctor_id)->first();

        if (!$doctor) {
            return response()->json(['message' => 'No doctor found'], 404);
        }

        return response()->json([
            'doctor' => $doctor
        ], 200);
    }

    // later
    // public function editDoctor1(Request $request, Doctor $doctor)
    // {
    //     $validator = Validator::make($request->all(), [
    //         // User data
    //         'first_name' => 'sometimes|string|max:255',
    //         'last_name' => 'sometimes|string|max:255',
    //         'email' => [
    //             'sometimes',
    //             'email',
    //             'unique:users,email,' . $doctor->user_id
    //         ],
    //         'phone_number' => 'sometimes|string|max:20',

    //         // Doctor data
    //         'specialty' => 'sometimes|string|max:255',
    //         'bio' => 'nullable|string',
    //         'consultation_fee' => 'sometimes|numeric|min:0',
    //         'experience_years' => 'sometimes|integer|min:0',
    //         'clinic_id' => 'sometimes|exists:clinics,id',

    //
    //         'schedules' => 'sometimes|array',
    //         'schedules.*.day' => 'required_with:schedules|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
    //         'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
    //         'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',

    //
    //         'slot_duration' => 'sometimes|integer|min:5',
    //         'generate_slots_for_days' => 'sometimes|integer|min:1',

    //         // Status control
    //         'is_active' => 'sometimes|boolean'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     $validated = $validator->validated();

    //     return DB::transaction(function () use ($request, $doctor, $validated) {
    //         if (isset($validated['first_name']) || isset($validated['last_name']) || isset($validated['email']) || isset($validated['phone_number'])) {
    //             $userData = [
    //                 'first_name' => $validated['first_name'] ?? $doctor->user->first_name,
    //                 'last_name' => $validated['last_name'] ?? $doctor->user->last_name,
    //                 'email' => $validated['email'] ?? $doctor->user->email,
    //                 'phone' => $validated['phone_number'] ?? $doctor->user->phone, // إذا العمود اسمه phone أو phone_number حسب الجدول
    //             ];
    //             $doctor->user->update($userData);
    //         }

    //         $doctorData = collect($validated)
    //             ->except(['first_name', 'last_name', 'email', 'phone_number', 'schedules'])
    //             ->toArray();
    //         $doctor->update($doctorData);

    //         if (isset($validated['schedules'])) {
    //             $doctor->schedules()->delete();

    //             foreach ($validated['schedules'] as $schedule) {
    //                 $doctor->schedules()->create([
    //                     'day' => $schedule['day'],
    //                     'start_time' => $schedule['start_time'],
    //                     'end_time' => $schedule['end_time'],
    //                 ]);
    //             }
    //         }

    //         return response()->json([
    //             'message' => 'Doctor updated successfully',
    //             'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
    //         ]);
    //     });
    // }


    public function createSecretary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:20',
            'gender'       => 'required|in:male,female',
            'workdays'     => 'required|array',
            'workdays.*'   => 'string',
            'salary'       => 'nullable|numeric|min:0',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone_number,
                'gender'     => $request->gender,
                'role_id'    => 3,
                'password' => Hash::make($request->password),

            ]);

            $secretary = Secretary::create([
                'user_id'             => $user->id,
                'salary'              => $request->salary,
                'workdays'            => json_encode($request->workdays),
                'emergency_absences'  => json_encode([]),
                'performance_metrics' => json_encode([]),
            ]);

            return response()->json([
                'message'           => 'Secretary created successfully',
                'secretary'         => $secretary,
                'login_credentials' => [
                    'email'    => $user->email,
                    'password' => $user->password
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create secretary',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function updateSecretary(Request $request, $id)
    {
        $secretary = Secretary::with('user')->find($id);

        if (!$secretary) {
            return response()->json(['success' => false, 'message' => 'السكرتيرة غير موجودة'], 404);
        }

        $validated = $request->validate([
            'first_name'       => 'required|string|max:255',
            'last_name'        => 'nullable|string|max:255',
            'email'            => 'required|email|unique:users,email,' . $secretary->user->id,
            'workdays'         => 'nullable|string',
            'profile_picture'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096'
        ]);

        $secretary->user->first_name = $validated['first_name'];
        $secretary->user->last_name  = $validated['last_name'] ?? null;
        $secretary->user->email      = $validated['email'];

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('uploads/secretaries', 'public');
            $secretary->user->profile_picture = '/storage/' . $path;
        }
        $secretary->user->save();

        $secretary->workdays = $validated['workdays'] ?? $secretary->workdays;
        $secretary->save();

        return response()->json(['success' => true, 'message' => 'تم تحديث بيانات السكرتيرة بنجاح']);
    }

    public function getSecretaryById($id)
    {
        $secretary = Secretary::with('user')->where('id', $id)->first();

        if (!$secretary) {
            return response()->json([
                'success' => false,
                'message' => 'السكرتيرة غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $secretary
        ]);
    }

    ///////////////////////////////profile/////
    public function updateAdminInfo(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'User is not an admin'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'unique:users,email,' . $user->id
            ],
            'phone' => 'sometimes|string|max:20',
            'date_of_birth' => 'sometimes|date',
            'address' => 'sometimes|string|max:500',
            'gender' => 'sometimes|in:male,female,other',
            'additional_notes' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('uploads', 'public');
            $imageUrl = asset('storage/' . $path);
        } else {
            $imageUrl = $user->profile_picture; // Keep old picture if not updated
        }

        try {
            $user->update([
                'first_name'       => $validated['first_name'] ?? $user->first_name,
                'last_name'        => $validated['last_name'] ?? $user->last_name,
                'email'            => $validated['email'] ?? $user->email,
                'phone'            => $validated['phone'] ?? $user->phone,
                'date_of_birth'    => $validated['date_of_birth'] ?? $user->date_of_birth,
                'address'          => $validated['address'] ?? $user->address,
                'gender'           => $validated['gender'] ?? $user->gender,
                'additional_notes' => $validated['additional_notes'] ?? $user->additional_notes,
                'profile_picture'  => $imageUrl,
            ]);

            return response()->json([
                'message' => 'Admin information updated successfully',
                'admin'   => $user->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update admin information',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function statistics()
    {
        $now = Carbon::now();

        return response()->json([
            'total_patients' => Patient::count(),
            'total_doctors' => Doctor::count(),
            'total_appointments' => Appointment::count(),
            'current_month_appointments' => Appointment::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count(),
            'wallet_balance' => MedicalCenterWallet::first()->balance ?? 0,
            'current_month_doctors' => Doctor::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count()
        ]);
    }
}
