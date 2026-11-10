<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientSelectApiTest extends TestCase
{
    public function test_shared_client_picker_endpoints_are_not_registered(): void
    {
        $this->assertFalse(Route::has('api.clients.index'));
        $this->assertFalse(Route::has('api.clients.email-check'));
        $this->assertFalse(Route::has('api.clients.show'));
        $this->assertFalse(Route::has('api.my-linked-ofws'));

        $this->getJson('/api/clients')->assertNotFound();
        $this->getJson('/api/clients/email-check?email=ofw@example.test')->assertNotFound();
        $this->getJson('/api/my-linked-ofws')->assertNotFound();
    }
}
