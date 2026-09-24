<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use Unity\Testing\Doubles\MemberStub;
use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\PreferredContact;

/*
 * Tests for TsmlMemberRepository's domain event firing.
 *
 * These tests pin down the contract that programmatic writes through
 * the repository — Integrity REST, WP-CLI, cron, anything that doesn't
 * go through ACF's form-save lifecycle — emit unity/member_changing
 * (for updates) or unity/member_created (for inserts), so listeners
 * like Scrutiny's audit tracker can react.
 *
 * The tests deliberately do NOT exercise the underlying acf/save_post
 * path used by the admin form; that path has its own listener in
 * TsmlMemberChangeTracker and is covered by other tests.
 */

covers(\TsmlForUnity\Members\TsmlMemberRepository::class);

beforeEach(function () {
    $this->factory = $this->createMock(MemberFactory::class);
    $this->repository = new TsmlMemberRepository($this->factory);
});

/**
 * Helper: stub get_post / get_post_type for findById's pre-check
 * so the factory's createFromSource() is reached.
 */
function stubExistingPost(int $postId): void
{
    Functions\expect('get_post')
        ->with($postId)
        ->andReturn((object) [
            'ID' => $postId,
            'post_type' => TsmlMemberFields::POST_TYPE,
        ]);
}

/**
 * Helper: stub all the update_field calls updateFields() makes.
 * We don't care which arguments they get for these tests — we're
 * asserting on the do_action events, not the field writes.
 */
function allowAnyUpdateFieldCalls(): void
{
    Functions\expect('update_field')->andReturn(true);
}

// ─── update() fires unity/member_changing ───────────────────────
test('update fires member changing with original and updated members', function () {
    $postId = 23462;

    // Two distinct member objects: original (mobile = old) and
    // updated (mobile = new). The factory returns the original on
    // the first findById() (before writes) and the updated on the
    // second (after writes).
    $original = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'OLD-MOBILE');
    $updated  = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');

    stubExistingPost($postId);
    $this->factory->expects($this->exactly(2))
        ->method('createFromSource')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    Functions\expect('wp_update_post')->once()->andReturn($postId);
    allowAnyUpdateFieldCalls();

    expectDone('unity/member_changing')->once()->with($updated, $original);

    $caller = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');
    $result = $this->repository->update($caller);

    expect($result)->toBeTrue();
});

test('update does not fire member changing when wp update post fails', function () {
    $postId = 23462;

    $original = new MemberStub($postId);

    stubExistingPost($postId);
    $this->factory->expects($this->once())
        ->method('createFromSource')
        ->with($postId)
        ->willReturn($original);

    // Simulate wp_update_post returning a WP_Error.
    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_update_post')->once()->andReturn($error);

    // No update_field calls and no event fired — assert by absence.

    $caller = new MemberStub($postId, 'Anon');
    $result = $this->repository->update($caller);

    expect($result)->toBeFalse();
    // The assertion that matters is that we never reached
    // findField/createFromSource a second time, verified by the once()
    // expectation above.
});

test('update returns false for zero post id and does nothing', function () {
    // Zero ID never reaches findById, wp_update_post, or update_field.
    // Said explicitly rather than left to "an unstubbed call fatals":
    // wp-mocks defines these for real, so an unexpected call would now
    // succeed quietly where wp_mock would have blown up.
    Functions\expect('wp_update_post')->never();
    Functions\expect('update_field')->never();

    $caller = new MemberStub(0, 'Anon');
    $result = $this->repository->update($caller);

    expect($result)->toBeFalse();
});

// ─── save() insert path fires unity/member_created ──────────────
test('save insert fires member created after writes', function () {
    $newPostId = 99999;

    // Caller submits a Member with id=0 (insert).
    $caller = new MemberStub(0, 'New Anon');

    // After insert + updateFields, findById is called once to
    // re-read the persisted state. The factory returns the
    // member as it exists in storage.
    $persisted = new MemberStub($newPostId, 'New Anon');

    Functions\expect('wp_insert_post')->once()->andReturn($newPostId);
    allowAnyUpdateFieldCalls();

    // findById's pre-check
    Functions\expect('get_post')
        ->with($newPostId)
        ->andReturn((object) [
            'ID' => $newPostId,
            'post_type' => TsmlMemberFields::POST_TYPE,
        ]);

    $this->factory->expects($this->once())
        ->method('createFromSource')
        ->with($newPostId)
        ->willReturn($persisted);

    expectDone('unity/member_created')->once()->with($persisted);

    $result = $this->repository->save($caller);

    expect($result)->toBeTrue();
});

test('save insert does not fire member created when wp insert post fails', function () {
    $caller = new MemberStub(0, 'New Anon');

    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_insert_post')->once()->andReturn($error);

    // No update_field, no get_post, no createFromSource: a
    // failure to insert returns false before any of those.

    $result = $this->repository->save($caller);

    expect($result)->toBeFalse();
});

test('save with existing id delegates to update and fires member changing', function () {
    // save() with id > 0 must delegate to update() — verified by
    // observing the same unity/member_changing event update() fires,
    // not unity/member_created.

    $postId = 23462;

    $original = new MemberStub($postId, 'Old Anon');
    $updated  = new MemberStub($postId, 'New Anon');

    stubExistingPost($postId);
    $this->factory->expects($this->exactly(2))
        ->method('createFromSource')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    Functions\expect('wp_update_post')->once()->andReturn($postId);
    allowAnyUpdateFieldCalls();

    expectDone('unity/member_changing')->once()->with($updated, $original);

    $caller = new MemberStub($postId, 'New Anon');
    $result = $this->repository->save($caller);

    expect($result)->toBeTrue();
});

// ─── findByEmail() ──────────────────────────────────────────────
test('find by email returns member when acf field matches', function () {
    $postId = 4242;
    $email  = 'member@example.test';

    // findByEmail() builds a get_posts query that:
    //  - filters by the members CPT and 'publish' status (defaults)
    //  - limits to one post (numberposts => 1)
    //  - meta_query keys on the personal-email ACF field
    Functions\expect('get_posts')
        ->once()
        ->withArgs(function ($args) use ($email) {
            if (!isset($args['meta_query'][0])) {
                return false;
            }
            $clause = $args['meta_query'][0];
            return $args['post_type']   === TsmlMemberFields::POST_TYPE
                && $args['post_status'] === 'publish'
                && $args['numberposts'] === 1
                && $clause['key']       === TsmlMemberFields::FIELD_PERSONAL_EMAIL
                && $clause['value']     === $email
                && $clause['compare']   === '=';
        })
        ->andReturn([new \WP_Post(['ID' => $postId])]);

    stubExistingPost($postId);

    $expected = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, $email);
    $this->factory->expects($this->once())
        ->method('createFromSource')
        ->with($postId)
        ->willReturn($expected);

    $result = $this->repository->findByEmail($email);

    expect($result)->toBe($expected);
});

test('find by email returns null when no member matches', function () {
    // No matching posts → findAll() returns [] → findByEmail() returns null.
    Functions\expect('get_posts')->once()->andReturn([]);

    // Factory must not be called when there are no posts.
    $this->factory->expects($this->never())->method('createFromSource');

    expect($this->repository->findByEmail('missing@example.test'))->toBeNull();
});

test('find by email returns null for empty string without querying', function () {
    // Empty input short-circuits before any DB work. get_posts() is a
    // real function in wp-mocks, so "no expectation" no longer means "a
    // call would fatal" — say it outright.
    Functions\expect('get_posts')->never();
    $this->factory->expects($this->never())->method('createFromSource');

    expect($this->repository->findByEmail(''))->toBeNull();
});

// ─── findTelephoneResponders() ──────────────────────────────────
test('find telephone responders queries the responder flag and returns members', function () {
    $postId = 7777;

    // findTelephoneResponders() runs a single get_posts query that:
    //  - filters by the members CPT and 'publish' status
    //  - asks for ids only ('fields' => 'ids') so the build path
    //    goes straight through the factory, no per-post get_post
    //  - meta_query keys on the telephone-responder ACF field,
    //    matching the ACF true_false stored value '1'
    Functions\expect('get_posts')
        ->once()
        ->withArgs(function ($args) {
            if (!isset($args['meta_query'][0])) {
                return false;
            }
            $clause = $args['meta_query'][0];
            return $args['post_type']   === TsmlMemberFields::POST_TYPE
                && $args['post_status'] === 'publish'
                && $args['fields']      === 'ids'
                && $clause['key']       === TsmlMemberFields::FIELD_TELEPHONE_RESPONDER
                && $clause['value']     === '1'
                && $clause['compare']   === '=';
        })
        ->andReturn([$postId]);

    $expected = new MemberStub(id: $postId, anonymousName: 'Anon', telephoneResponder: true);
    $this->factory->expects($this->once())
        ->method('createFromSource')
        ->with($postId)
        ->willReturn($expected);

    $result = $this->repository->findTelephoneResponders();

    expect($result)->toBe([$expected]);
});

test('find telephone responders returns empty array when none match', function () {
    // No matching posts → findAll() returns [] → method returns [].
    Functions\expect('get_posts')->once()->andReturn([]);

    $this->factory->expects($this->never())->method('createFromSource');

    expect($this->repository->findTelephoneResponders())->toBe([]);
});

// ─── updateFields() writes the landline and the preference ──────

/**
 * Capture every update_field() call as fieldName => value.
 *
 * @param array<string, mixed> $captured Filled in by reference.
 */
function captureUpdateFieldCalls(array &$captured): void
{
    Functions\expect('update_field')->andReturnUsing(
        static function (string $field, mixed $value, int $postId) use (&$captured): bool {
            $captured[$field] = $value;
            return true;
        }
    );
}

test('update writes the landline and the preferred contact', function () {
    $postId = 5100;
    $member = new MemberStub(
        id: $postId,
        landlineNumber: '0117 496 0000',
        preferredContact: PreferredContact::Landline
    );

    stubExistingPost($postId);
    $this->factory->method('createFromSource')->willReturn($member);
    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $captured = [];
    captureUpdateFieldCalls($captured);

    $this->repository->update($member);

    expect($captured[TsmlMemberFields::FIELD_LANDLINE_NUMBER])->toBe('0117 496 0000');
    // ACF stores the radio's choice value, not the enum case.
    expect($captured[TsmlMemberFields::FIELD_PREFERRED_CONTACT])->toBe('Landline');
});

/*
 * A Member can be built by hand — through the REST API, or an importer —
 * saying Landline with no landline to ring. The stored value is what the
 * admin form and the forwarding side read back, so it is settled on the
 * way in rather than left for every reader to second-guess.
 */
test('update writes mobile when the preference has no landline behind it', function () {
    $postId = 5101;
    $member = new MemberStub(
        id: $postId,
        landlineNumber: '',
        preferredContact: PreferredContact::Landline
    );

    stubExistingPost($postId);
    $this->factory->method('createFromSource')->willReturn($member);
    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $captured = [];
    captureUpdateFieldCalls($captured);

    $this->repository->update($member);

    expect($captured[TsmlMemberFields::FIELD_LANDLINE_NUMBER])->toBe('')
        ->and($captured[TsmlMemberFields::FIELD_PREFERRED_CONTACT])->toBe('Mobile');
});
