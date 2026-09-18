<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Plugin;
use TsmlForUnity\Tests\Support\WpdbStub;
use TsmlForUnity\Tests\TestCase;
use Unity\Core\Interfaces\Cache;
use Unity\Core\Interfaces\Configuration;
use Unity\Meetings\CachingMeetingRepository;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Members\CachingMemberRepository;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Testing\Doubles\FakeContainer;
use Unity\Testing\Doubles\InMemoryCache;

/**
 * Wiring for Unity's member and meeting caches.
 *
 * Two halves that are only correct together: the repository is wrapped when
 * there is a cache to wrap it with, and the invalidator is hooked so that a
 * member edited anywhere — the ACF screen, Reconcile, Scrutiny's pruner —
 * drops out of it.
 *
 * @covers \TsmlForUnity\Plugin
 */
class CacheWiringTest extends TestCase
{
    private FakeContainer $container;

    private mixed $previousWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new FakeContainer();
        $this->container->prime(Configuration::class, $this->createMock(Configuration::class));

        // The credential repository takes a wpdb, and registerWithUnity()
        // builds the whole graph.
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new WpdbStub();
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;

        parent::tearDown();
    }

    /**
     * @test
     */
    public function the_member_repository_is_wrapped_when_a_cache_is_available(): void
    {
        $this->container->prime(Cache::class, new InMemoryCache());

        Plugin::registerWithUnity($this->container);

        $this->assertInstanceOf(CachingMemberRepository::class, $this->container->get(MemberRepository::class));
    }

    /**
     * @test
     */
    public function the_bare_repository_is_registered_when_there_is_no_cache(): void
    {
        Plugin::registerWithUnity($this->container);

        $repository = $this->container->get(MemberRepository::class);

        // Unity ships headless and a consumer may register no cache at all.
        // Wrapping regardless would add a layer that can only ever miss.
        $this->assertInstanceOf(TsmlMemberRepository::class, $repository);
        $this->assertNotInstanceOf(CachingMemberRepository::class, $repository);
    }

    /**
     * @test
     */
    public function the_invalidator_hooks_the_post_and_meta_actions_when_caching(): void
    {
        $this->container->prime(Cache::class, new InMemoryCache());
        Plugin::registerWithUnity($this->container);

        Plugin::registerMemberCacheInvalidator($this->container);

        $this->assertActionAdded('save_post_' . TsmlMemberFields::POST_TYPE);
        $this->assertActionAdded('before_delete_post');
        $this->assertActionAdded('trashed_post');
        $this->assertActionAdded('untrashed_post');
        $this->assertActionAdded('added_post_meta');
        $this->assertActionAdded('updated_post_meta');
        $this->assertActionAdded('deleted_post_meta');
    }

    /**
     * @test
     */
    public function nothing_is_hooked_when_the_repository_is_not_caching(): void
    {
        Plugin::registerWithUnity($this->container);

        Plugin::registerMemberCacheInvalidator($this->container);

        // Nothing to invalidate, so these hooks would fire on every post and
        // meta write on the site for no reason at all.
        $this->assertActionNotAdded('updated_post_meta');
        $this->assertActionNotAdded('save_post_' . TsmlMemberFields::POST_TYPE);
    }

    /**
     * @test
     */
    public function nothing_is_hooked_when_no_member_repository_is_registered(): void
    {
        Plugin::registerMemberCacheInvalidator($this->container);

        $this->assertActionNotAdded('updated_post_meta');
    }

    /**
     * @test
     */
    public function the_meeting_repository_is_wrapped_when_a_cache_is_available(): void
    {
        $this->container->prime(Cache::class, new InMemoryCache());

        Plugin::registerWithUnity($this->container);

        $this->assertInstanceOf(CachingMeetingRepository::class, $this->container->get(MeetingRepository::class));
    }

    /**
     * @test
     */
    public function the_bare_meeting_repository_is_registered_when_there_is_no_cache(): void
    {
        Plugin::registerWithUnity($this->container);

        $repository = $this->container->get(MeetingRepository::class);

        $this->assertInstanceOf(TsmlMeetingRepository::class, $repository);
        $this->assertNotInstanceOf(CachingMeetingRepository::class, $repository);
    }

    /**
     * @test
     */
    public function the_meeting_invalidator_hooks_the_meeting_post_type(): void
    {
        $this->container->prime(Cache::class, new InMemoryCache());
        Plugin::registerWithUnity($this->container);

        Plugin::registerCacheInvalidators($this->container);

        // MeetingRepository is read-only, so this is the only thing that can
        // ever clear a cached meeting. Without it, an edited meeting keeps
        // serving its old day and time.
        $this->assertActionAdded('save_post_' . TsmlMeetingFields::POST_TYPE);
        $this->assertActionAdded('updated_post_meta');

        // And the member half is hooked by the same call.
        $this->assertActionAdded('save_post_' . TsmlMemberFields::POST_TYPE);
    }

    /**
     * @test
     */
    public function no_meeting_hooks_without_a_cache(): void
    {
        Plugin::registerWithUnity($this->container);

        Plugin::registerCacheInvalidators($this->container);

        $this->assertActionNotAdded('save_post_' . TsmlMeetingFields::POST_TYPE);
        $this->assertActionNotAdded('updated_post_meta');
    }
}
