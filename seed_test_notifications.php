<?php

use Illuminate\Support\Str;

// Create test notifications and alerts for the case manager
$cmEmail = 'case@bayanihan.gov.ph';
$cmUser = DB::table('users')->where('email', $cmEmail)->first();
if (! $cmUser) {
    echo "ERROR: Case manager not found\n";
    exit(1);
}

$now = now();

// Create 5 test notifications (database type)
echo "Creating notifications...\n";
$notifTypes = [
    'App\Notifications\Cases\CaseCreatedNotification',
    'App\Notifications\Cases\CaseUpdatedNotification',
    'App\Notifications\Cases\CaseAssignedNotification',
    'App\Notifications\Referrals\ReferralCreatedNotification',
    'App\Notifications\Referrals\ReferralCompletedNotification',
];

$notificationData = [
    ['title' => 'New Case Created', 'message' => 'Case CASE-20260511-0001 has been created successfully.'],
    ['title' => 'Case Updated', 'message' => 'Case CASE-20260511-0002 has been updated with new information.'],
    ['title' => 'Case Assigned', 'message' => 'Case CASE-20260511-0003 has been assigned to your queue.'],
    ['title' => 'Referral Initiated', 'message' => 'A new referral has been created for CASE-20260511-0001.'],
    ['title' => 'Referral Completed', 'message' => 'The referral for CASE-20260511-0002 has been completed.'],
];

for ($i = 0; $i < count($notifTypes); $i++) {
    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => $notifTypes[$i],
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $cmUser->id,
        'data' => json_encode($notificationData[$i]),
        'read_at' => null,
        'created_at' => $now->copy()->subMinutes(count($notifTypes) - $i),
        'updated_at' => $now->copy()->subMinutes(count($notifTypes) - $i),
    ]);
}
echo '  Created '.count($notifTypes)." notifications\n";

// Create 5 test alerts (mark-all-read endpoint works with alerts)
echo "Creating alerts...\n";
$alertTypes = ['info', 'warning', 'critical', 'info', 'success'];
$alertSeverities = ['info', 'warning', 'critical', 'info', 'success'];
$alertTitles = [
    'System maintenance scheduled',
    'Storage capacity low',
    'Security breach attempt detected',
    'New feature available',
    'Backup completed successfully',
];
$alertMessages = [
    'The system will undergo maintenance on June 15, 2026 from 2:00 AM to 4:00 AM.',
    'Cloudinary storage is at 85% capacity. Please review and archive old files.',
    'Multiple failed login attempts detected from IP 203.0.113.42.',
    'The new analytics dashboard is now available. Check it out in the Reports section.',
    'Daily database backup completed successfully at 2:00 AM.',
];

for ($i = 0; $i < 5; $i++) {
    DB::table('alerts')->insert([
        'id' => Str::uuid(),
        'type' => $alertTypes[$i],
        'severity' => $alertSeverities[$i],
        'title' => $alertTitles[$i],
        'message' => $alertMessages[$i],
        'assigned_to_id' => $cmUser->id,
        'read_at' => null,
        'dismissed_at' => null,
        'created_at' => $now->copy()->subMinutes(5 - $i),
    ]);
}
echo "  Created 5 alerts\n";

// Create additional notifications to ensure pagination test (more than 20)
echo "Creating extra notifications for pagination testing...\n";
for ($i = 0; $i < 25; $i++) {
    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\Cases\CaseCreatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $cmUser->id,
        'data' => json_encode([
            'title' => 'Bulk Notification #'.($i + 1),
            'message' => 'This is a test notification number '.($i + 1).' for pagination testing.',
        ]),
        'read_at' => null,
        'created_at' => $now->copy()->subHours($i),
        'updated_at' => $now->copy()->subHours($i),
    ]);
}
echo "  Created 25 extra notifications\n";

// Set OTP debug mode
DB::table('cache')->insert([
    'key' => 'otp_debug_mode',
    'value' => '1',
    'expiration' => 9999999999,
]);

echo "\nDone! Test data created successfully.\n";
