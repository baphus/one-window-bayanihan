<?php

use App\Models\User;
use App\Services\CaseService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$userId = User::orderBy('created_at')->first()->id;
$caseService = app(CaseService::class);

$drafts = [
    ['first_name' => 'Maria', 'last_name' => 'Clara', 'middle_name' => 'Lopez'],
    ['first_name' => 'Pedro', 'last_name' => 'Garcia', 'middle_name' => 'Reyes'],
    ['first_name' => 'Ana', 'last_name' => 'Santos', 'middle_name' => 'Mendoza'],
];

foreach ($drafts as $d) {
    $case = $caseService->createCase([
        'client_type' => 'OFW',
        'client' => [
            'first_name' => $d['first_name'],
            'last_name' => $d['last_name'],
            'middle_name' => $d['middle_name'],
        ],
        'consent' => true,
    ], $userId, true);
    echo 'Created: '.$case->case_number.' - '.$d['first_name'].' '.$d['last_name'].PHP_EOL;
}

echo 'Drafts created successfully'.PHP_EOL;
