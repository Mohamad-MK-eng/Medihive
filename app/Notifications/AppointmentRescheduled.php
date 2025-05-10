<?php

// app/Notifications/AppointmentRescheduled.php
namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
class AppointmentRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Appointment Rescheduled")
           // ->line("Your appointment has been rescheduled to {$this->appointment->appointment_date->format('l, F j Y g:i a')}")
            ->action('View Appointment', url("/appointments/{$this->appointment->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'appointment_rescheduled',
            'appointment_id' => $this->appointment->id,
            'new_date' => $this->appointment->appointment_date,
            'rescheduled_by' => $this->appointment->rescheduled_by
        ];
    }
}









/*// app/Notifications/AppointmentRescheduled.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Appointment;

class AppointmentRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Appointment Rescheduled')
            ->line(sprintf(
                'Your appointment has been rescheduled to %s',
                $this->appointment->appointment_date->format('l, F j, Y \a\t g:i a')
            ))
            ->action(
                'View Appointment',
                route('appointments.show', $this->appointment->id)
            )
            ->line('Thank you for using our clinic management system!');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'appointment_rescheduled',
            'appointment_id' => $this->appointment->id,
            'new_date' => $this->appointment->appointment_date->toDateTimeString(),
            'rescheduled_by' => $this->appointment->rescheduled_by,
            'message' => sprintf(
                'Appointment rescheduled to %s',
                $this->appointment->appointment_date->format('M j, Y g:i A')
            )
        ];
    }
}

*/
