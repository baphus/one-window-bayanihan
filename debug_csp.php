<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$request = Request::create('/login', 'GET');
$response = $kernel->handle($request);

echo 'Status: '.$response->getStatusCode().PHP_EOL;
echo 'CSP-RO Header: '.($response->headers->get('Content-Security-Policy-Report-Only') ?? 'MISSING').PHP_EOL;
echo 'CSP Header: '.($response->headers->get('Content-Security-Policy') ?? 'MISSING').PHP_EOL;
echo PHP_EOL;

// List all security headers
foreach ($response->headers->all() as $name => $values) {
    if (str_contains($name, 'Security') || str_contains($name, 'CSP') || str_contains($name, 'Policy')) {
        echo "$name: ".implode(', ', $values).PHP_EOL;
    }
}

$kernel->terminate($request, $response);
