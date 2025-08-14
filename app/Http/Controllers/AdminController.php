<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
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
use Storage;
use Str;
use Validator;

class AdminController extends Controller
{
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
            COUNT(*) as transaction_count
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
            'location' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i|after:opening_time',
            'icon' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle icon update if present
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








public function createSecretary(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        // User data
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone_number' => 'required|string|max:20',
        'gender' => 'required|in:male,female,other',

        // Secretary data
        'workdays' => 'required|array|min:1',
        'workdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        // Start transaction
        return DB::transaction(function () use ($request) {
            // Get the secretary role
            $secretaryRole = Role::where('name', 'secretary')->first();
            if (!$secretaryRole) {
                throw new \Exception('Secretary role not found in database');
            }

            // Create user account
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make('temporary_password'),
                'role_id' => $secretaryRole->id,
                'gender' => $request->gender,
            ]);

            // Create secretary profile
            $secretary = Secretary::create([
                'user_id' => $user->id,
                'workdays' => $request->workdays,

            ]);

            return response()->json([
                'message' => 'Secretary created successfully',
                'secretary' => $secretary->load('user'),

            ], 201);
        });
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to create secretary',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTrace() : null
        ], 500);
    }
}





public function updateSecretary(Request $request,Secretary $secretary){
$validator = Validator::make($request->all(),[

 'email' => [
                'sometimes',
                'email',
                'unique:users,email,' . $secretary->user_id
            ],

            'phone_number' => 'sometimes|string|max:20',
        'gender' => 'sometimes|in:male,female,other',
        'workdays' => 'sometimes|array|min:1',
        'workdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($request, $secretary, $validated) {
            // Update user data if present
            if (
                isset($validated['email']) || isset($validated['phone_number'])
            ) {

                $userData = [
                  'email' => $validated['email'] ?? $secretary->user->email,
                    'phone_number' => $validated['phone_number'] ?? $secretary->user->phone_number,
                ];

                $secretary->user->update($userData);
            }

            // Update doctor data
            $SecData = collect($validated)
                ->except(['first_name', 'last_name', 'email', 'phone_number'])
                ->toArray();

            $secretary->update($SecData);

            return response()->json([
                'message' => 'Secretary updated successfully',
                'secretary' => $secretary->fresh()]);

        });
    }



    public function createDoctor(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            // User data
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:20',

            // Doctor data
            'specialty' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'consultation_fee' => 'required|numeric|min:0',
            'experience_years' => 'required|integer|min:0',
            'clinic_id' => 'required|exists:clinics,id',

            // Schedule data
            'schedules' => 'required|array|min:1',
            'schedules.*.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

            // Time slot configuration
            'slot_duration' => 'required|integer|in:30,60',
            'generate_slots_for_days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Start transaction
            return DB::transaction(function () use ($request) {
                // Get the doctor role
                $doctorRole = Role::where('name', 'doctor')->first();
                if (!$doctorRole) {
                    throw new \Exception('Doctor role not found in database');
                }

                // Get a secretary for salary assignment
                $secretary = Secretary::first();
                if (!$secretary) {
                    throw new \Exception('No secretary found in database');
                }

                // Verify clinic exists
                $clinic = Clinic::find($request->clinic_id);
                if (!$clinic) {
                    throw new \Exception('Specified clinic not found');
                }

                // Create salary settings and salary record
                $salarySettings = SalarySetting::firstOrCreate([]);
                $salary = Salary::create([
                    'secretary_id' => $secretary->id,
                    'base_amount' => 100,
                    'bonus_amount' => 0.5,
                    'total_amount' => 100.5,
                    'salary_setting_id' => $salarySettings->id,
                    'status' => 'pending'
                ]);

                // Create user account
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make('temporary_password'),
                    'role_id' => $doctorRole->id,
                    'gender'=>$request->gender,
                ]);

                // Create doctor profile
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

                // Create schedules
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
                // Generate time slots
              /*   $timeSlots = [];
                $slotDuration = $request->slot_duration;

                for ($i = 1; $i <= $request->generate_slots_for_days; $i++) {
                    $date = now()->addDays($i)->format('Y-m-d');
                    $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

                    $schedule = $doctor->schedules()->where('day', $dayOfWeek)->first();
                    if (!$schedule) continue;

                    $start = Carbon::parse($schedule->start_time);
                    $end = Carbon::parse($schedule->end_time);

                    $current = $start->copy();
                    while ($current->addMinutes($slotDuration)->lte($end)) {
                        $slotStart = $current->copy()->subMinutes($slotDuration);
                        $slotEnd = $current->copy();

                        $timeSlots[] = [
                            'doctor_id' => $doctor->id,
                            'date' => $date,
                            'start_time' => $slotStart->format('H:i:s'),
                            'end_time' => $slotEnd->format('H:i:s'),
                            'is_booked' => false,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                } */

                // Insert time slots if any were generated
                if (!empty($timeSlots)) {
                    TimeSlot::insert($timeSlots);
                }

                // Refresh the doctor model with relationships
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


    public function generateTimeSlotsForDoctor(Doctor $doctor, $daysToGenerate, $slotDuration)
{
    $timeSlots = [];
    $now = Carbon::now();
    $startDate = $now->copy()->startOfDay();

    \Log::info("Generating slots for doctor {$doctor->id} for {$daysToGenerate} days");

    for ($i = 0; $i < $daysToGenerate; $i++) {
        $date = $startDate->copy()->addDays($i);
        $dayName = strtolower($date->englishDayOfWeek);

        $schedule = $doctor->schedules()->where('day', $dayName)->first();
        if (!$schedule) {
            \Log::info("No schedule for {$dayName} on {$date->format('Y-m-d')}");
            continue;
        }

        $dateStr = $date->format('Y-m-d');
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);

        // Adjust for today: only generate future slots
        if ($date->isToday()) {
            $currentTime = $now->copy();
            // Only adjust if current time is within working hours
            if ($currentTime->between($start, $end)) {
                $start = $currentTime;
            }
        }

        // Generate slots
        $current = $start->copy();
        $slotsCount = 0;

        while ($current->addMinutes($slotDuration)->lte($end)) {
    $slotStart = $current->copy()->subMinutes($slotDuration);
    $slotEnd = $current->copy();

            // Skip past slots for today
            if ($date->isToday() && $slotEnd->lte($now)) {
                continue;
            }

            $timeSlots[] = [
                'doctor_id' => $doctor->id,
                'date' => $dateStr,
                'start_time' => $slotStart->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
                'is_booked' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];
            $slotsCount++;
        }

        \Log::info("Generated {$slotsCount} slots for {$dateStr} ({$dayName})");
    }

    if (!empty($timeSlots)) {
        try {
            \Log::info("Inserting ".count($timeSlots)." slots for doctor {$doctor->id}");
            TimeSlot::insert($timeSlots);
            return count($timeSlots);
        } catch (\Exception $e) {
            \Log::error("Failed to insert slots: ".$e->getMessage());
            return 0;
        }
    }

    \Log::warning("No slots generated for doctor {$doctor->id}");
    return 0;
}
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
            // Update user data if present
            if (
                isset($validated['email']) || isset($validated['phone_number'])
            ) {

                $userData = [
                  'email' => $validated['email'] ?? $doctor->user->email,
                    'phone_number' => $validated['phone_number'] ?? $doctor->user->phone_number,
                ];

                $doctor->user->update($userData);
            }

            // Update doctor data
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
    /**
     * Delete a doctor (admin only)
     */








/**
 * Change admin password
 */
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








/**
 * Update admin information (excluding profile picture)
 */
public function updateAdminInfo(Request $request)
{
    $user = Auth::user();

    // Check if user is authenticated
    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated'
        ], 401);
    }

    // Check if user has admin role
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
        'phone_number' => 'sometimes|string|max:20',
        'date_of_birth' => 'sometimes|date',
        'address' => 'sometimes|string|max:500',
        'gender' => 'sometimes|in:male,female,other',
        'additional_notes' => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $validated = $validator->validated();

    try {
        $user->update([
            'first_name' => $validated['first_name'] ?? $user->first_name,
            'last_name' => $validated['last_name'] ?? $user->last_name,
            'email' => $validated['email'] ?? $user->email,
            'phone_number' => $validated['phone_number'] ?? $user->phone_number,
            'date_of_birth' => $validated['date_of_birth'] ?? $user->date_of_birth,
            'address' => $validated['address'] ?? $user->address,
            'gender' => $validated['gender'] ?? $user->gender,
            'additional_notes' => $validated['additional_notes'] ?? $user->additional_notes,
        ]);

        return response()->json([
            'message' => 'Admin information updated successfully',
            'admin' => $user->fresh()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to update admin information',
            'error' => $e->getMessage()
        ], 500);
    }
}











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
            // Delete old file if exists
            if ($user->profile_picture) {
                $this->deleteProfilePicture();
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $directory = trim($config['directory'], '/');

            // Store file
            $path = $file->storeAs($directory, $filename, 'public');

            // Update user record
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

    /**
     * Get admin profile picture URL
     */
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
            if ($this->deleteProfilePictureFile($user)) {  // Changed to helper method
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

    // Get the stored path directly
    $path = $user->profile_picture;

    // Verify file exists in storage
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




public function deleteDoctor(Doctor $doctor)
{
    // No need to check for upcoming appointments since we're keeping them

    DB::transaction(function () use ($doctor) {
        // Delete the doctor record
        $doctor->delete();

        // Optionally delete the associated user account if needed
        // $doctor->user()->delete();
    });
    $doctor->update(['is_active' => false]);

    return response()->json([
        'message' => 'Doctor deleted successfully. Existing appointments remain intact.',
        'deleted_at' => now()->toDateTimeString()
    ]);
}







public function restoreDoctor($id)
{
    return DB::transaction(function () use ($id) {
        $doctor = Doctor::withTrashed()->findOrFail($id);
        $doctor->restore();
        $doctor->user()->restore();

        return response()->json([
            'message' => 'Doctor restored successfully',
            'doctor' => $doctor->load('user')
        ]);
    });
}



}

