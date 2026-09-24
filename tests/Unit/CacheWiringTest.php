<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Plugin;
use TsmlForUnity\Tests\Support\WpdbStub;
use Unity\Core\Interfaces\Cache;
use Unity\Core\Interfaces\Configuration;
use Unity\Meetings\CachingMeetingRepository;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Members\CachingMemberRepository;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Testing\Doubles\FakeContainer;
use Unity\Testing\Doubles\InMemoryCache;

/*
 * Wiring for Unity's member and meeting caches.
 *
 * Two halves that are only correct together: the repository is wrapped when
 * there is a cache to wrap it with, and the invalidator is hooked so that a
 * member edited anywhere — the ACF screen, Reconcile, Scrutiny's pruner —
 * drops out of it.
 */

covers(\TsmlForUnity\Plugin::class);

beforeEach(function () {
    $this->container = new FakeContainer();
    $this->container->prime(Configuration::class, $this->createMock(Configuration::class));

    // The credential repository takes a wpdb, and registerWithUnity()
    // builds the whole graph.
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $GLOBALS['wpdb'] = new WpdbStub();
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

test('the member repository is wrapped when a cache is available', function () {
    $this->container->prime(Cache::class, new InMemoryCache());

    Plugin::registerWithUnity($this->container);

    expect($this->container->get(MemberRepository::class))->toBeInstanceOf(CachingMemberRepository::class);
});

test('the bare repository is registered when there is no cache', function () {
    Plugin::registerWithUnity($this->container);

    $repository = $this->container->get(MemberRepository::class);

    // Unity ships headless and a consumer may register no cache at all.
    // Wrapping regardless would add a layer that can only ever miss.
    expect($repository)->toBeInstanceOf(TsmlMemberRepository::class)
        ->and($repository)->not->toBeInstanceOf(CachingMemberRepository::class);
});

test('the invalidator hooks the post and meta actions when caching', function () {
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
});

test('nothing is hooked when the repository is not caching', function () {
    Plugin::registerWithUnity($this->container);

    Plugin::registerMemberCacheInvalidator($this->container);

    // Nothing to invalidate, so these hooks would fire on every post and
    // meta write on the site for no reason at all.
    $this->assertActionNotAdded('updated_post_meta');
    $this->assertActionNotAdded('save_post_' . TsmlMemberFields::POST_TYPE);
});

test('nothing is hooked when no member repository is registered', function () {
    Plugin::registerMemberCacheInvalidator($this->container);

    $this->assertActionNotAdded('updated_post_meta');
});

test('the meeting repository is wrapped when a cache is available', function () {
    $this->container->prime(Cache::class, new InMemoryCache());

    Plugin::registerWithUnity($this->container);

    expect($this->container->get(MeetingRepository::class))->toBeInstanceOf(CachingMeetingRepository::class);
});

test('the bare meeting repository is registered when there is no cache', function () {
    Plugin::registerWithUnity($this->container);

    $repository = $this->container->get(MeetingRepository::class);

    expect($repository)->toBeInstanceOf(TsmlMeetingRepository::class)
        ->and($repository)->not->toBeInstanceOf(CachingMeetingRepository::class);
});

test('the meeting invalidator hooks the meeting post type', function () {
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
});

test('no meeting hooks without a cache', function () {
    Plugin::registerWithUnity($this->container);

    Plugin::registerCacheInvalidators($this->container);

    $this->assertActionNotAdded('save_post_' . TsmlMeetingFields::POST_TYPE);
    $this->assertActionNotAdded('updated_post_meta');
});
