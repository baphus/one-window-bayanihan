<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientEmailCheckTest extends TestCase
{
    public function test_shared_client_email_duplicate_probe_is_retired(): void
    {
        $this->assertFalse(Route::has('api.clients.email-check'));
        $this->getJson('/api/clients/email-check?email=ofw@example.test')->assertNotFound();
    }
}
