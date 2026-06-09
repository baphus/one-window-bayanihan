<?php

$users = DB::table('users')->select('id', 'name', 'email', 'role')->get();
foreach ($users as $user) {
    echo "{$user->email} | {$user->name} | {$user->role}\n";
}
echo "\n--- Notifications table ---\n";
echo 'Notifications count: '.DB::table('notifications')->count()."\n";
echo "\n--- Alerts table ---\n";
echo 'Alerts count: '.DB::table('alerts')->count()."\n";
