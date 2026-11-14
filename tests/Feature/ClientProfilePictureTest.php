<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClientProfilePictureTest extends TestCase
{
    public function test_shared_client_profile_picture_routes_are_retired(): void
    {
        $this->assertFalse(Route::has('clients.avatar.store'));
        $this->assertFalse(Route::has('clients.avatar.destroy'));
        $this->post('/clients/not-a-client-id/avatar')->assertNotFound();
        $this->delete('/clients/not-a-client-id/avatar')->assertNotFound();
    }
}
