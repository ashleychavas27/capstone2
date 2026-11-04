<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;

$pw = 'TestPass1234';

$mk = function (string $name, string $email, string $role, string $contact, ?string $duty = null) use ($pw) {
    return User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => $pw,
            'contact_no' => $contact,
            'role' => $role,
            'is_active' => true,
            'duty_days' => $duty,
        ]
    );
};

$p1 = $mk('Test Patient Uno', 'qa.patient1@example.test', User::ROLE_PATIENT, '09516469880');
$p2 = $mk('Test Patient Dos', 'qa.patient2@example.test', User::ROLE_PATIENT, '09171234568');
$sec = $mk('Test Secretary', 'qa.secretary@example.test', User::ROLE_SECRETARY, '09171234569');
$den = $mk('Test Dentist', 'qa.dentist@example.test', User::ROLE_DENTIST, '09171234570', '1,2,3,4,5,6');

foreach ([$p1, $p2] as $p) {
    PatientProfile::updateOrCreate(
        ['user_id' => $p->id],
        ['age' => 24, 'address' => 'Dingle, Iloilo', 'medical_history' => 'None']
    );
}

$rx = Prescription::updateOrCreate(
    ['patient_id' => $p1->id, 'diagnosis' => 'QA TEST - impacted molar, mild gingivitis'],
    ['dentist_id' => $den->id, 'notes' => 'QA test record, safe to delete.', 'date_issued' => now()->toDateString()]
);

PrescriptionItem::updateOrCreate(
    ['prescription_id' => $rx->id, 'drug_name' => 'Amoxicillin 500mg'],
    ['dosage' => '1 capsule', 'frequency' => 'Every 8 hours', 'duration' => '7 days', 'quantity' => '21', 'instructions' => 'Take after meals.']
);

echo "PATIENT1_ID={$p1->id}\n";
echo "PATIENT2_ID={$p2->id}\n";
echo "SECRETARY_ID={$sec->id}\n";
echo "DENTIST_ID={$den->id}\n";
echo "PRESCRIPTION_ID={$rx->id}\n";
echo "SMS_MOCK=".var_export((bool) config('services.sms.mock'), true)."\n";
echo "ENDPOINT=".config('services.sms.endpoint')."\n";
echo "SENDER=".config('services.sms.sender_id')."\n";
echo "API_KEY_SET=".(config('services.sms.api_key') !== '' ? 'yes' : 'no')."\n";
