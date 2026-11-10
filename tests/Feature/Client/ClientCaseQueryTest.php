<?php

namespace Tests\Feature\Client;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCaseQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_client_directory_routes_return_not_found(): void
    {
        $this->get('/clients')->assertNotFound();
        $this->get('/clients/export-count')->assertNotFound();
        $this->get('/clients/export')->assertNotFound();
    }
}
