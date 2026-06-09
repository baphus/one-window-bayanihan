<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== AUDIT LOG MODULES ===\n";
$modules = DB::table('audit_logs')->select('module', 'action')->distinct()->get();
foreach ($modules as $m) {
    echo "  module={$m->module} action={$m->action}\n";
}

echo "\n=== AUDIT LOG SAMPLE (5) ===\n";
$samples = DB::table('audit_logs')->limit(5)->get();
foreach ($samples as $s) {
    echo "  id={$s->id} module={$s->module} action={$s->action}\n";
    echo '    description='.($s->description ?? 'null')."\n";
    if ($s->old_value) {
        echo '    old_value='.substr(json_encode($s->old_value), 0, 300)."\n";
    }
    if ($s->new_value) {
        echo '    new_value='.substr(json_encode($s->new_value), 0, 300)."\n";
    }
}

echo "\n=== SYSTEM SETTINGS (all) ===\n";
$settings = DB::table('system_settings')->get();
foreach ($settings as $s) {
    echo "  {$s->key} = {$s->value}\n";
}

echo "\n=== USERS ===\n";
$users = DB::table('users')->select('email', 'role', 'is_active')->get();
foreach ($users as $u) {
    echo "  {$u->email} role={$u->role} active={$u->is_active}\n";
}

echo "\n=== CLIENTS (5) ===\n";
$clients = DB::table('clients')->limit(5)->get();
foreach ($clients as $c) {
    echo "  {$c->id} {$c->first_name} {$c->last_name}\n";
}
