<?php

use App\Models\CaseFile;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$case = CaseFile::orderBy('created_at', 'desc')->first();
echo 'Case: '.$case->case_number.' Status: '.$case->status.PHP_EOL;
