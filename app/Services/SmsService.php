<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SmsLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS notification service.
 *
 * Mirrors the eTextMo HTTP API structure:
 *   POST {SMS_ENDPOINT}
 *   form: api_key, sender_id, recipients, message
 *
 * When SMS_MOCK=true (default) the HTTP call is simulated and the attempt is
 * recorded in sms_logs with status "Mocked" so the feature can be demonstrated
 * without a real SMS gateway / network access.
 */
class SmsService
{
    public function __construct(
        protected bool $mock = true,
        protected string $apiKey = '',
        protected string $senderId = '',
        protected string $endpoint = '',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            mock: (bool) config('services.sms.mock', true),
            apiKey: (string) config('services.sms.api_key', ''),
            senderId: (string) config('services.sms.sender_id', ''),
            endpoint: (string) config('services.sms.endpoint', 'https://api.etextmo.com/api/send-message'),
        );
    }

    /**
     * Normalize Philippine phone numbers to 11-digit 09XXXXXXXXX format.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * Send a message to one recipient and persist the attempt in sms_logs.
     */
    public function send(string $recipientPhone, string $message): SmsLog
    {
        $normalizedPhone = self::normalizePhone($recipientPhone);

        if ($this->mock) {
            // Simulate the gateway round-trip.
            $log = SmsLog::create([
                'recipient_phone' => $recipientPhone,
                'message' => $message,
                'status' => SmsLog::STATUS_MOCKED,
                'sent_at' => now(),
            ]);

            Log::info('[SMS][MOCK] SMS queued for delivery.', [
                'to' => $recipientPhone,
                'message' => $message,
            ]);

            return $log;
        }

        try {
            $isSemaphore = str_contains($this->endpoint, 'semaphore.co');

            if ($isSemaphore) {
                $payload = [
                    'apikey' => $this->apiKey,
                    'number' => $normalizedPhone,
                    'message' => $message,
                    'sendername' => $this->senderId,
                ];
            } else {
                $payload = [
                    'api_key' => $this->apiKey,
                    'sender_id' => $this->senderId,
                    'recipients' => $recipientPhone,
                    'message' => $message,
                ];
            }

            Log::info('[SMS] Sending via gateway.', [
                'endpoint' => $this->endpoint,
                'to'       => $normalizedPhone,
                'sender'   => $this->senderId,
            ]);

            $response = Http::asForm()->timeout(15)->post($this->endpoint, $payload);

            Log::info('[SMS] Gateway response.', [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);

            $status = $response->successful() ? SmsLog::STATUS_SENT : SmsLog::STATUS_FAILED;

            if (! $response->successful()) {
                Log::warning('[SMS] Gateway returned a non-success response.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            $status = SmsLog::STATUS_FAILED;
            Log::error('[SMS] Failed to send SMS: '.$e->getMessage(), [
                'to'        => $recipientPhone,
                'exception' => $e->getTraceAsString(),
            ]);
        }

        return SmsLog::create([
            'recipient_phone' => $recipientPhone,
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);
    }

    /**
     * Send an appointment confirmation reminder to the patient.
     * Returns the created sms_log entry, or null if the patient has no contact number.
     */
    public function sendAppointmentConfirmation(Appointment $appointment): ?SmsLog
    {
        $appointment->loadMissing(['patient', 'dentist']);
        $clinic = self::clinicName();

        return $this->notifyPatient($appointment, sprintf(
            "Hello, %s! \u{1F49C}\n\nGood news! Your appointment for %s on %s, at %s is officially confirmed.\n\nKindly arrive 15 minutes before your scheduled time to allow for a smooth check-in.\n\nThank you for choosing %s! We're looking forward to seeing you and taking care of your smile. See you soon! \u{1F60A}\n\n— %s",
            $appointment->patient?->name,
            $appointment->service_type,
            $appointment->appointment_date?->format('F j, Y'),
            self::naturalTime($appointment),
            $clinic,
            $clinic
        ));
    }

    /**
     * Acknowledge a newly booked appointment while it is still Pending.
     */
    public function sendBookingAcknowledgment(Appointment $appointment): ?SmsLog
    {
        $appointment->loadMissing(['patient', 'dentist']);
        $clinic = self::clinicName();
        $dentist = $appointment->dentist?->name;

        return $this->notifyPatient($appointment, sprintf(
            "Good day, %s! \u{1F49C}\n\nThank you for choosing %s! We've received your appointment request for %s%s on %s, at %s.\n\nYour appointment is currently pending confirmation. We'll send you another message once it has been confirmed.\n\nWe look forward to taking care of your smile! Keep smiling! \u{1F60A}\n\n— %s",
            $appointment->patient?->name,
            $clinic,
            $appointment->service_type,
            $dentist ? ' with Dr. '.$dentist : '',
            $appointment->appointment_date?->format('F j, Y'),
            self::naturalTime($appointment),
            $clinic
        ));
    }

    /**
     * Notify the patient that an appointment was cancelled.
     */
    public function sendCancellationNotice(Appointment $appointment): ?SmsLog
    {
        $appointment->loadMissing(['patient']);
        $clinic = self::clinicName();

        return $this->notifyPatient($appointment, sprintf(
            "Hello, %s! \u{1F49C} We regret to inform you that your appointment for %s scheduled on %s, at %s has been cancelled.\n\nWe apologize for any inconvenience this may cause. If you'd still like to visit us, you're always welcome to book another appointment.\n\nThank you for your understanding and for considering %s. We hope to see you again soon! \u{1F60A}\n\n— %s",
            $appointment->patient?->name,
            $appointment->service_type,
            $appointment->appointment_date?->format('F j, Y'),
            self::naturalTime($appointment),
            $clinic,
            $clinic
        ));
    }

    /**
     * Time as a person writes it: "9:00 AM" rather than the table's "09:00 AM".
     */
    protected static function naturalTime(Appointment $appointment): string
    {
        $slot = (string) $appointment->time_slot;

        if ($slot === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('H:i', $slot)->format('g:i A');
        } catch (\Throwable) {
            return (string) $appointment->formatted_slot;
        }
    }

    /** Patient-facing clinic name used in the notification copy. */
    protected static function clinicName(): string
    {
        return (string) config('clinic.name', config('app.name', 'Datorin-Dajay Dental Clinic'));
    }

    /**
     * Day-before reminder for confirmed appointments (used by sms:send-reminders).
     */
    public function sendDayBeforeReminder(Appointment $appointment): ?SmsLog
    {
        return $this->notifyPatient($appointment, sprintf(
            'Reminder from '.config('app.name', 'Dental Clinic').': %s, you have a %s appointment tomorrow, %s at %s. Please arrive 15 minutes early. - '.config('app.name', 'Dental Clinic'),
            $appointment->patient?->name,
            $appointment->service_type,
            $appointment->appointment_date?->format('M d, Y'),
            $appointment->formatted_slot
        ));
    }

    /** Shared guard: skip silently when the patient has no contact number. */
    protected function notifyPatient(Appointment $appointment, string $message): ?SmsLog
    {
        $phone = $appointment->patient?->contact_no;

        if (empty($phone)) {
            Log::info('[SMS] Skipped notification: patient has no contact number.', [
                'appointment_id' => $appointment->id,
            ]);

            return null;
        }

        return $this->send($phone, $message);
    }
}
