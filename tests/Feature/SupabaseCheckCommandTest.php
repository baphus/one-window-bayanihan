<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseCheckCommandTest extends TestCase
{
    public function test_command_reports_warnings_when_config_missing(): void
    {
        Config::set('services.supabase.url', '');
        Config::set('services.supabase.key', '');
        Config::set('services.supabase.service_key', '');
        Config::set('database.connections.pgsql.sslmode', '');

        $this->artisan('supabase:check')
            ->expectsOutputToContain('not set')
            ->expectsOutputToContain('not configured')
            ->assertFailed();
    }

    public function test_command_succeeds_when_config_is_set(): void
    {
        Http::fake();

        Config::set('services.supabase.url', 'https://testproject.supabase.co');
        Config::set('services.supabase.key', 'eyjanatestkey123');
        Config::set('services.supabase.service_key', 'eyjanaservicekey456');
        Config::set('database.connections.pgsql.sslmode', 'require');

        $this->artisan('supabase:check')
            ->expectsOutputToContain('SUPABASE_URL')
            ->expectsOutputToContain('SUPABASE_KEY')
            ->expectsOutputToContain('SUPABASE_SERVICE_KEY')
            ->expectsOutputToContain('require')
            ->assertSuccessful();
    }

    public function test_command_handles_partial_config(): void
    {
        Http::fake();

        Config::set('services.supabase.url', 'https://testproject.supabase.co');
        Config::set('services.supabase.key', '');
        Config::set('services.supabase.service_key', '');
        Config::set('database.connections.pgsql.sslmode', 'prefer');

        $this->artisan('supabase:check')
            ->expectsOutputToContain('not set')
            ->assertFailed();
    }

    public function test_command_handles_missing_service_key(): void
    {
        Http::fake();

        Config::set('services.supabase.url', 'https://testproject.supabase.co');
        Config::set('services.supabase.key', 'eyjanatestkey123');
        Config::set('services.supabase.service_key', '');
        Config::set('database.connections.pgsql.sslmode', 'require');

        $this->artisan('supabase:check')
            ->expectsOutputToContain('SERVICE_KEY')
            ->assertFailed();
    }
}
