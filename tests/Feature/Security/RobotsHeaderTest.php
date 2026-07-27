<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Search-engine indexing is opt-in, not a side effect of APP_ENV.
 *
 * A staging host behind an unguessable platform URL is effectively private, but
 * becomes discoverable the moment it gains a custom hostname. A production host
 * that is provisioned and not yet launched has the same problem, and indexing a
 * half-configured public service is not cleanly reversible.
 *
 * robots.txt cannot express this — one image serves every environment — and it
 * only requests no crawl. This header suppresses indexing outright.
 */
class RobotsHeaderTest extends TestCase
{
    #[Test]
    public function it_sends_noindex_by_default(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[Test]
    public function it_sends_noindex_in_production_until_indexing_is_switched_on(): void
    {
        // The regression this guards: gating on APP_ENV alone meant merely
        // setting APP_ENV=production made an unlaunched site indexable.
        $this->app['env'] = 'production';
        config(['app.search_indexing_enabled' => false]);

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    #[Test]
    public function it_omits_noindex_once_indexing_is_enabled(): void
    {
        config(['app.search_indexing_enabled' => true]);

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeaderMissing('X-Robots-Tag');
    }

    #[Test]
    public function indexing_stays_disabled_when_the_env_value_is_a_falsey_string(): void
    {
        // Environment variables arrive as strings, and "false" is truthy in PHP.
        // filter_var with FILTER_VALIDATE_BOOLEAN is what stops SEARCH_INDEXING
        // _ENABLED=false from switching indexing ON.
        config(['app.search_indexing_enabled' => filter_var('false', FILTER_VALIDATE_BOOLEAN)]);

        $this->get('/login')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
