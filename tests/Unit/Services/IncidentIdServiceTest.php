<?php

namespace Tests\Unit\Services;

use App\Services\IncidentIdService;
use Tests\TestCase;

class IncidentIdServiceTest extends TestCase
{
    private IncidentIdService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IncidentIdService;
    }

    public function test_generate_returns_valid_uuid_format(): void
    {
        $id = $this->service->generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id,
            'Generated ID must be a valid UUID format'
        );
    }

    public function test_generate_returns_uuid_version_7(): void
    {
        $id = $this->service->generate();

        // UUID v7 has version digit = 7 in the 13th-14th characters (the version field)
        // Format: xxxxxxxx-xxxx-7xxx-xxxx-xxxxxxxxxxxx
        $this->assertSame('7', $id[14], 'UUID version byte should be 7 (UUID v7)');
    }

    public function test_format_for_display_returns_ref_prefix(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $formatted = $this->service->formatForDisplay($uuid);

        $this->assertSame('Ref: 550e8400', $formatted);
    }

    public function test_format_for_display_works_with_real_uuid(): void
    {
        $id = $this->service->generate();
        $formatted = $this->service->formatForDisplay($id);

        $this->assertStringStartsWith('Ref: ', $formatted);
        // "Ref: " + 8 hex chars = 13 characters
        $this->assertSame(13, strlen($formatted));
    }

    public function test_generate_id_static_method_returns_valid_uuid(): void
    {
        $id = IncidentIdService::generateId();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id,
            'Static generateId() must return a valid UUID format'
        );
    }

    public function test_generate_id_static_method_returns_different_value_each_call(): void
    {
        $id1 = IncidentIdService::generateId();
        $id2 = IncidentIdService::generateId();

        $this->assertNotSame($id1, $id2, 'Consecutive calls to generateId() must return different values');
    }

    public function test_generate_returns_unique_ids(): void
    {
        $ids = [];
        $count = 1000;

        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->service->generate();
        }

        $this->assertCount($count, $ids);
        $this->assertCount($count, array_unique($ids), 'All 1000 generated IDs must be unique');
    }

    public function test_generate_id_static_matches_instance_output_format(): void
    {
        $instanceId = $this->service->generate();
        $staticId = IncidentIdService::generateId();

        // Both must match the UUID regex
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
        $this->assertMatchesRegularExpression($pattern, $instanceId);
        $this->assertMatchesRegularExpression($pattern, $staticId);
    }
}
