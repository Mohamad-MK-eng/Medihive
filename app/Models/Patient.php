<?php

namespace App\Models;

use App\Traits\HandlesFiles;
use DB;
use Dom\Document;
use Illuminate\Database\Eloquent\Model;
use Storage;

/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property string $date_of_birth
 * @property string $address
 * @property string $phone_number
 * @property string $gender
 * @property string|null $blood_type
 * @property array<array-key, mixed>|null $chronic_conditions
 * @property array<array-key, mixed>|null $insurance_provider
 * @property string $emergency_contact
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointment
 * @property-read int|null $appointment_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Prescription> $prescription
 * @property-read int|null $prescription_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereBloodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereChronicConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereEmergencyContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereInsuranceProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereUserId($value)
 * @mixin \Eloquent
 */
class Patient extends Model
{
    use HandlesFiles;

    // هون عدلت
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',

        'date_of_birth',
        'address',
        'profile_picture',
        'phone_number',
        'gender',
        'blood_type',
        'chronic_conditions',
        'insurance_provider',
        'emergency_contact',
        'wallet_balance',
        'wallet_pin',
        'wallet_activated_at'
    ];

    protected $casts = [
        'date_of_birth' => 'date',

        'phone_number',
        'wallet_balance'  => 'double',

        'chronic_conditions' => 'array',
        'insurance_provider' => 'array',
        'wallet_activated_at' => 'datetime'
    ];




    public function user()
    {


        return $this->belongsTo(User::class);
    }



    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }


    // In App\Models\Patient.php
    public function prescriptions()
    {
        return $this->hasManyThrough(
            Prescription::class,
            Appointment::class,
            'patient_id', // Foreign key on appointments table
            'appointment_id', // Foreign key on prescriptions table
            'id', // Local key on patients table
            'id' // Local key on appointments table
        );
    }


    public function prescription()
    {
        return $this->hasManyThrough(Prescription::class, Appointment::class);
    }





    public function payments()
    {

        return $this->hasMany(Payment::class);
    }

    public function documents()
    {

        return $this->hasMany(Document::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }


    public function hasChronicCondition($condition)
    {

        return in_array($condition, $this->chronic_conditions);
    }


    public function getInsuranceDetails()
    {

        return $this->insurance_provider;
    }




    public function getUpcomingAppointments()
    {

        return $this->appointments()
            ->where('appointment_date', '>=', now())
            ->where('status', 'confirmed')
            ->orderBy('appointment_date')->get();
    }

    public function getMedicalHistory()
    {
        return $this->appointments()
            ->with(['doctor.user', 'clinic', 'prescriptions'])
            ->where('appointment_date', '<=', now())
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'date' => $appointment->appointment_date,
                    'clinic' => $appointment->clinic->name,
                    'doctor' => $appointment->doctor->user->full_name,
                    'prescription' => $appointment->prescription,
                    'notes' => $appointment->notes
                ];
            });
    }







    public function getProfilePictureUrlAttribute()
    {
        return $this->user->getFileUrl('profile_picture');
    }



    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }








    // In Patient model (add these methods)
    public function deposit($amount, $notes = null, $adminId = null)
    {
        return DB::transaction(function () use ($amount, $notes, $adminId) {
            $previousBalance = $this->wallet_balance;
            $newBalance = $previousBalance + $amount;

            $transaction = WalletTransaction::create([
                'patient_id' => $this->id,
                'admin_id' => $adminId,
                'amount' => $amount,
                'type' => 'deposit',
                'reference' => 'DEP-' . now()->format('YmdHis'),
                'balance_before' => $previousBalance,
                'balance_after' => $newBalance,
                'notes' => $notes
            ]);

            $this->update(['wallet_balance' => $newBalance]);

            return $transaction;
        });
    }

    public function withdraw($amount, $notes = null)
    {
        if ($this->wallet_balance < $amount) {
            throw new \Exception('Insufficient wallet balance');
        }

        return DB::transaction(function () use ($amount, $notes) {
            $previousBalance = $this->wallet_balance;
            $newBalance = $previousBalance - $amount;

            $transaction = WalletTransaction::create([
                'patient_id' => $this->id,
                'amount' => $amount,
                'type' => 'withdrawal',
                'reference' => 'WTH-' . now()->format('YmdHis'),
                'balance_before' => $previousBalance,
                'balance_after' => $newBalance,
                'notes' => $notes
            ]);

            $this->update(['wallet_balance' => $newBalance]);

            return $transaction;
        });
    }
}
