<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientControllerAuthTest extends TestCase
{
    public function test_shared_client_directory_routes_are_not_registered(): void
    {
        foreach ([
            'clients.index',
            'clients.show',
            'clients.export-excel',
            'clients.export-count',
            'clients.avatar.store',
            'clients.avatar.destroy',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Legacy route [{$name}] must remain retired.");
        }

        $this->get('/clients')->assertNotFound();
        $this->get('/clients/not-a-client-id')->assertNotFound();
        $this->get('/clients/export-excel')->assertNotFound();
        $this->getJson('/clients/export-count')->assertNotFound();
    }
}
