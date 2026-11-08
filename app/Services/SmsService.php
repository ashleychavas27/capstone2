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
 *
 * One segment per message. A single emoji or curly quote flips the whole payload
 * to Unicode (UCS-2), which drops the per-segment budget from 160 characters to
 * 70 - that is why the first versions arrived cut off. Every patient message is
 * therefore written in the plain GSM-7 alphabet with the emoji and em dashes
 * removed, and each one is checked with segments() before it is sent; when a long
 * name or procedure will not fit, the shorter variant is used instead.
 */
class SmsService
{
    /** GSM-7 default alphabet: 1 unit per character. */
    protected const GSM7_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** GSM-7 extension alphabet: each of these costs 2 units, so they are avoided. */
    protected const GSM7_EXTENDED = "^{}\\[~]|€";

    /** Single-segment budget in GSM-7 units (153 + 7 for the concatenation header when split). */
    public const MAX_GSM7_UNITS = 160;
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
     * Does the message need Unicode encoding? One emoji is enough to force it.
     */
    public static function usesUnicode(string $message): bool
    {
        foreach (mb_str_split($message, 1, 'UTF-8') as $char) {
            if (! str_contains(self::GSM7_BASIC, $char) && ! str_contains(self::GSM7_EXTENDED, $char)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Billable segments for a message: 160 GSM-7 units, or 70 Unicode characters.
     */
    public static function segments(string $message): int
    {
        if ($message === '') {
            return 0;
        }

        $chars = mb_str_split($message, 1, 'UTF-8');

        if (self::usesUnicode($message)) {
            return count($chars) <= 70 ? 1 : (int) ceil(count($chars) / 67);
        }

        $units = 0;

        foreach ($chars as $char) {
            $units += str_contains(self::GSM7_EXTENDED, $char) ? 2 : 1;
        }

        return $units <= self::MAX_GSM7_UNITS ? 1 : (int) ceil($units / 153);
    }

    /**
     * Long messages are split by the gateway and can arrive cut off on some
     * networks, so the copy is always kept inside one segment.
     */
    public static function fitsOneSegment(string $message): bool
    {
        return self::segments($message) === 1;
    }

    /**
     * Pick the first candidate that fits a single segment, else the shortest one.
     *
     * @param  array<int, string>  $candidates  most faithful wording first
     */
    protected function fitOneSegment(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (self::fitsOneSegment($candidate)) {
                return $candidate;
            }
        }

        $message = collect($candidates)->sortBy(fn (string $c) => mb_strlen($c))->first();

        Log::warning('[SMS] No single-segment variant fits; sending the shortest one.', [
            'length' => mb_strlen($message),
        ]);

        return $message;
    }

    /**
     * Send a message to one recipient and persist the attempt in sms_logs.
     */
    public function send(string $recipientPhone, string $message): SmsLog
    {
        $normalizedPhone = self::normalizePhone($recipientPhone);

        if (! self::fitsOneSegment($message)) {
            Log::warning('[SMS] Message is longer than one segment and may arrive truncated.', [
                'segments' => self::segments($message),
                'length' => mb_strlen($message),
            ]);
        }

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

        return $this->notifyPatient($appointment, $this->fitOneSegment($this->confirmationCopy($appointment)));
    }

    /**
     * Acknowledge a newly booked appointment while it is still Pending.
     */
    public function sendBookingAcknowledgment(Appointment $appointment): ?SmsLog
    {
        $appointment->loadMissing(['patient', 'dentist']);

        return $this->notifyPatient($appointment, $this->fitOneSegment($this->pendingCopy($appointment)));
    }

    /**
     * Notify the patient that an appointment was cancelled.
     */
    public function sendCancellationNotice(Appointment $appointment): ?SmsLog
    {
        $appointment->loadMissing(['patient']);

        return $this->notifyPatient($appointment, $this->fitOneSegment($this->cancellationCopy($appointment)));
    }

    /**
     * Pending (booking received) copy, longest variant first.
     *
     * @return array<int, string>
     */
    protected function pendingCopy(Appointment $appointment): array
    {
        $patient = (string) $appointment->patient?->name;
        $first = self::firstName($patient);
        $service = (string) $appointment->service_type;
        $withDentist = self::dentistSuffix($appointment);
        $date = self::longDate($appointment);
        $shortDate = self::shortDate($appointment);
        $time = self::naturalTime($appointment);
        $clinic = self::clinicName();

        return [
            "Hello, {$patient}! Your {$service}{$withDentist} on {$date} at {$time} is pending confirmation. We'll text you once it's confirmed.",
            "Hi {$first}! Your {$service}{$withDentist} on {$shortDate} at {$time} is pending confirmation. We'll text you once it's confirmed.",
            "Hi {$first}! {$service} on {$shortDate} at {$time} is pending - we'll text you once it's confirmed.",
        ];
    }

    /**
     * Confirmation copy, longest variant first.
     *
     * @return array<int, string>
     */
    protected function confirmationCopy(Appointment $appointment): array
    {
        $patient = (string) $appointment->patient?->name;
        $first = self::firstName($patient);
        $service = (string) $appointment->service_type;
        $date = self::longDate($appointment);
        $shortDate = self::shortDate($appointment);
        $time = self::naturalTime($appointment);
        $clinic = self::clinicName();

        return [
            "Hello, {$patient}! Your {$service} on {$date} at {$time} is confirmed. Please arrive 15 minutes early. - {$clinic}",
            "Hello, {$patient}! Your {$service} on {$date} at {$time} is confirmed. Please arrive 15 minutes early.",
            "Hi {$first}! Your {$service} on {$shortDate} at {$time} is confirmed. Please arrive 15 mins early.",
            "Hi {$first}! {$service} on {$shortDate} at {$time} is confirmed.",
        ];
    }

    /**
     * Cancelled copy, longest variant first.
     *
     * @return array<int, string>
     */
    protected function cancellationCopy(Appointment $appointment): array
    {
        $patient = (string) $appointment->patient?->name;
        $first = self::firstName($patient);
        $service = (string) $appointment->service_type;
        $date = self::longDate($appointment);
        $shortDate = self::shortDate($appointment);
        $time = self::naturalTime($appointment);
        $clinic = self::clinicName();

        return [
            "Hello, {$patient}! Your {$service} on {$date} at {$time} has been cancelled. You're welcome to reschedule anytime. - {$clinic}",
            "Hello, {$patient}! Your {$service} on {$date} at {$time} has been cancelled. You're welcome to reschedule anytime.",
            "Hi {$first}! Your {$service} on {$shortDate} at {$time} is cancelled. Sorry for the inconvenience - you may reschedule anytime.",
            "Hi {$first}! {$service} on {$shortDate} at {$time} is cancelled. Sorry - you may reschedule anytime.",
        ];
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
        $appointment->loadMissing(['patient']);

        $first = self::firstName((string) $appointment->patient?->name);
        $service = (string) $appointment->service_type;
        $date = self::shortDate($appointment);
        $time = self::naturalTime($appointment);
        $clinic = self::clinicName();

        return $this->notifyPatient($appointment, $this->fitOneSegment([
            "Hi {$first}! Reminder: your {$service} appointment is tomorrow, {$date} at {$time}. Please arrive 15 minutes early. - {$clinic}",
            "Hi {$first}! Reminder: {$service} tomorrow, {$date} at {$time}. Please arrive 15 mins early.",
            "Reminder: {$service} tomorrow at {$time}. - {$clinic}",
        ]));
    }

    /** First name, for the compact variants. */
    protected static function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? trim($name);
    }

    /** " with Dr. X" unless the name already carries the title. */
    protected static function dentistSuffix(Appointment $appointment): string
    {
        $dentist = trim((string) $appointment->dentist?->name);

        if ($dentist === '') {
            return '';
        }

        if (preg_match('/^dr\.?\s/i', $dentist)) {
            return ' with '.$dentist;
        }

        return ' with Dr. '.$dentist;
    }

    /** "November 6, 2026" */
    protected static function longDate(Appointment $appointment): string
    {
        return (string) $appointment->appointment_date?->format('F j, Y');
    }

    /** "Nov 6, 2026" */
    protected static function shortDate(Appointment $appointment): string
    {
        return (string) $appointment->appointment_date?->format('M j, Y');
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
