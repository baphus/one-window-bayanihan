<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Non-production environments must not be indexable.
 *
 * A staging host behind an unguessable platform URL is effectively private. The
 * moment it gains a guessable custom hostname it becomes discoverable while still
 * holding seeded data, so the guard is the environment, not the hostname.
 *
 * robots.txt cannot express this: one image serves every environment. The header
 * can, and unlike robots.txt it suppresses indexing rather than merely requesting
 * no crawl.
 */
class RobotsHeaderTest extends TestCase
{
    #[Test]
    public function it_sends_noindex_outside_production(): void
    {
        $this->app['env'] = 'staging';

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[Test]
    public function it_sends_noindex_in_the_testing_environment(): void
    {
        $this->app['env'] = 'testing';

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[Test]
    public function it_does_not_send_noindex_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeaderMissing('X-Robots-Tag');
    }
}
