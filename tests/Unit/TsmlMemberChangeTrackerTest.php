<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Testing\Doubles\MemberStub;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Members\Interfaces\MemberRepository;

/*
 * Tests for TsmlMemberChangeTracker's admin-form save lifecycle.
 *
 * The change tracker hooks acf/save_post twice — once at priority 1 to
 * snapshot the original member and detect whether this is the first
 * save, and once at priority 20 to fire the appropriate domain event
 * after ACF has written every field. These tests exercise that
 * captureOriginalMember → checkForChanges pair, focusing on the
 * branching between unity/member_created and unity/member_changing.
 */

covers(\TsmlForUnity\Members\TsmlMemberChangeTracker::class);

uses(ActionExpectations::class);

beforeEach(function () {
    // Allow add_action calls fired by the constructor without
    // pinning their exact arguments — the constructor wiring is
    // not under test here.

    $this->repository = $this->createMock(MemberRepository::class);
    $this->tracker = new TsmlMemberChangeTracker($this->repository);
});

afterEach(function () {
    // Reset static state so test order can't leak originalMember /
    // newMemberIds between cases.
    $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
    $reflection->getProperty('originalMember')->setValue(null, null);
    $reflection->getProperty('newMemberIds')->setValue(null, []);
});

/**
 * Stub the post-type guard at the top of capture/check, so the
 * methods proceed past their early return.
 */
function stubMemberPostTypeGuard(int $postId): void
{
    Functions\expect('get_post_type')
        ->with($postId)
        ->andReturn(TsmlMemberFields::POST_TYPE);
}

/**
 * Simulate the wp_insert_post lifecycle that flags a post as a creation:
 * fire onPostStatusTransition with auto-draft → publish for a member post.
 * After this the next checkForChanges call for that post id will treat
 * the save as a creation.
 */
function flagAsNewMember(int $postId): void
{
    test()->tracker->onPostStatusTransition(
        'publish',
        'auto-draft',
        (object) ['ID' => $postId, 'post_type' => TsmlMemberFields::POST_TYPE]
    );
}

/**
 * Stub get_post + the title-sync wp_update_post that runs in
 * checkForChanges. We don't care which title is passed — only that
 * the method completes — so any update is allowed and any get_post
 * call returns a record whose title already matches, suppressing
 * the wp_update_post call entirely. Tests that need to vary this
 * can override these expectations.
 */
function stubMemberTitleSyncIsNoop(int $postId, string $existingTitle = ''): void
{
    Functions\expect('get_post')
        ->with($postId)
        ->andReturn((object) [
            'ID' => $postId,
            'post_title' => $existingTitle,
        ]);
}

// ─── First save of an admin-created member fires member_created ──
test('first save of admin created member fires member created', function () {
    $postId = 1234;

    // Auto-draft snapshot has no fields populated; the post-save
    // snapshot has the form's submitted values. The change tracker
    // sees both via repository->findById.
    $autoDraft = new MemberStub($postId);
    $populated = new MemberStub(
        $postId,
        'New Anon',
        false,
        false,
        '',
        0,
        '',
        0,
        false,
        null,
        'new@example.com',
        '07700 900000'
    );

    stubMemberPostTypeGuard($postId);
    flagAsNewMember($postId);
    stubMemberTitleSyncIsNoop($postId, 'New Anon');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($autoDraft, $populated);

    expectDone('unity/member_before_save')->once()->with($postId, $autoDraft);
    expectDone('unity/member_created')->once()->with($populated);
    expectDone('unity/member_changed')->once()->with($postId, $populated, $autoDraft);
    // member_changing must NOT fire on a first save.
    $this->expectActionNotFired('unity/member_changing', $populated, $autoDraft);

    $this->tracker->captureOriginalMember($postId);
    $this->tracker->checkForChanges($postId);
});

test('first save fires member created even when no fields were populated', function () {
    // The "is this a new member" decision is taken from the
    // earlier transition_post_status hook, not from the field
    // diff. Even if every field remains empty after submit, the
    // create event must still fire.
    $postId = 4321;

    $before = new MemberStub($postId);
    $after  = new MemberStub($postId);

    stubMemberPostTypeGuard($postId);
    flagAsNewMember($postId);
    stubMemberTitleSyncIsNoop($postId);

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($before, $after);

    expectDone('unity/member_created')->once()->with($after);
    $this->expectActionNotFired('unity/member_changing', $after, $before);

    $this->tracker->captureOriginalMember($postId);
    $this->tracker->checkForChanges($postId);
});

// ─── Subsequent saves of an existing member fire member_changing ─
test('edit of existing published member fires member changing', function () {
    $postId = 5678;

    $original = new MemberStub(
        $postId,
        'Anon',
        false,
        false,
        '',
        0,
        '',
        0,
        false,
        null,
        '',
        'OLD-MOBILE'
    );
    $updated = new MemberStub(
        $postId,
        'Anon',
        false,
        false,
        '',
        0,
        '',
        0,
        false,
        null,
        '',
        'NEW-MOBILE'
    );

    stubMemberPostTypeGuard($postId);
    stubMemberTitleSyncIsNoop($postId, 'Anon');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    expectDone('unity/member_changing')->once()->with($updated, $original);
    $this->expectActionNotFired('unity/member_created', $updated);

    $this->tracker->captureOriginalMember($postId);
    $this->tracker->checkForChanges($postId);
});

test('toggling telephone responder fires member changing', function () {
    $postId = 5679;

    // Original: not a responder. Updated: is a responder. Everything
    // else is identical so the only diff lives in the new flag.
    $original = new MemberStub(id: $postId, anonymousName: 'Anon', telephoneResponder: false);
    $updated = new MemberStub(id: $postId, anonymousName: 'Anon', telephoneResponder: true);

    stubMemberPostTypeGuard($postId);
    stubMemberTitleSyncIsNoop($postId, 'Anon');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    expectDone('unity/member_changing')->once()->with($updated, $original);
    $this->expectActionNotFired('unity/member_created', $updated);

    $this->tracker->captureOriginalMember($postId);
    $this->tracker->checkForChanges($postId);
});

test('edit with no actual field changes fires no create or update event', function () {
    $postId = 91011;

    // Same data on both sides — diff is empty.
    $args = [$postId, 'Anon', false, false, '', 0, '', 0, false, null, 'a@b.com', '07700 900000'];
    $original = new MemberStub(...$args);
    $updated  = new MemberStub(...$args);

    stubMemberPostTypeGuard($postId);
    stubMemberTitleSyncIsNoop($postId, 'Anon');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    // Only the catch-all "save completed" event fires; create and
    // changing both stay silent.
    expectDone('unity/member_changed')->once()->with($postId, $updated, $original);
    $this->expectActionNotFired('unity/member_created', $updated);
    $this->expectActionNotFired('unity/member_changing', $updated, $original);

    $this->tracker->captureOriginalMember($postId);
    $this->tracker->checkForChanges($postId);
});

// ─── onPostStatusTransition filters correctly ────────────────────

/**
 * Inspect the static $newMemberIds map by reflection so each
 * transition test can assert on the exact effect of the call.
 */
function newMemberIds(): array
{
    $r = new \ReflectionClass(TsmlMemberChangeTracker::class);
    $prop = $r->getProperty('newMemberIds');
    return $prop->getValue();
}

test('transition from auto draft to publish flags the post', function () {
    $post = (object) ['ID' => 314, 'post_type' => TsmlMemberFields::POST_TYPE];

    $this->tracker->onPostStatusTransition('publish', 'auto-draft', $post);

    expect(newMemberIds())->toBe([314 => true]);
});

test('transition from auto draft to draft also flags the post', function () {
    // "Save Draft" on a brand-new Add New form is still a creation.
    $post = (object) ['ID' => 315, 'post_type' => TsmlMemberFields::POST_TYPE];

    $this->tracker->onPostStatusTransition('draft', 'auto-draft', $post);

    expect(newMemberIds())->toBe([315 => true]);
});

test('transition between two live statuses does not flag the post', function () {
    // Editing an existing member and changing draft → publish is a
    // status change, not a creation.
    $post = (object) ['ID' => 316, 'post_type' => TsmlMemberFields::POST_TYPE];

    $this->tracker->onPostStatusTransition('publish', 'draft', $post);

    expect(newMemberIds())->toBe([]);
});

test('transition into auto draft does not flag the post', function () {
    // The new → auto-draft transition fires when WordPress creates
    // the scaffolding row on /post-new.php load. No fields will be
    // saved for that, so it must not be flagged as a creation.
    $post = (object) ['ID' => 317, 'post_type' => TsmlMemberFields::POST_TYPE];

    $this->tracker->onPostStatusTransition('auto-draft', 'new', $post);

    expect(newMemberIds())->toBe([]);
});

test('transition for non member post types is ignored', function () {
    // Posts, pages, and other CPTs share transition_post_status; we
    // must not touch the flag map for them.
    $post = (object) ['ID' => 318, 'post_type' => 'post'];

    $this->tracker->onPostStatusTransition('publish', 'auto-draft', $post);

    expect(newMemberIds())->toBe([]);
});

// ─── Static state isolation ──────────────────────────────────────
test('isNewMember flag does not leak into a following update request', function () {
    // A creation in one request followed by an unrelated edit in
    // another must not cause the second save to be misclassified.
    // We simulate the two requests back-to-back; the static reset
    // happens at the end of checkForChanges in request 1.

    $createId = 111;
    $createAutoDraft = new MemberStub($createId);
    $createPopulated = new MemberStub($createId, 'A', false, false, '', 0, '', 0, false, null, 'a@b.com');

    stubMemberPostTypeGuard($createId);
    flagAsNewMember($createId);
    stubMemberTitleSyncIsNoop($createId, 'A');

    $editId = 222;
    $editOriginal = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'OLD');
    $editUpdated  = new MemberStub($editId, 'B', false, false, '', 0, '', 0, false, null, 'b@b.com', 'NEW');

    stubMemberPostTypeGuard($editId);
    stubMemberTitleSyncIsNoop($editId, 'B');

    $this->repository->expects($this->exactly(4))
        ->method('findById')
        ->willReturnOnConsecutiveCalls(
            $createAutoDraft,
            $createPopulated,
            $editOriginal,
            $editUpdated,
        );

    expectDone('unity/member_created')->once()->with($createPopulated);
    expectDone('unity/member_changing')->once()->with($editUpdated, $editOriginal);

    // Request 1 — create
    $this->tracker->captureOriginalMember($createId);
    $this->tracker->checkForChanges($createId);

    // Request 2 — edit on a different post; no creation flag set
    $this->tracker->captureOriginalMember($editId);
    $this->tracker->checkForChanges($editId);
});
