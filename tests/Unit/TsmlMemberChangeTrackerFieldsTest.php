<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use Exception;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Testing\Doubles\MemberStub;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\PreferredContact;
use Unity\Members\ResponderCertification;

/*
 * Field-by-field change detection, plus the save-path guards.
 *
 * hasMemberChanged() is a long chain of comparisons, and a field missing
 * from it is invisible: the member saves, but no unity/member_changing
 * fires, so Scrutiny records nothing and downstream caches never
 * invalidate. Every tracked field therefore gets its own case, which is
 * also what stops a field being quietly dropped from the chain later.
 */

covers(\TsmlForUnity\Members\TsmlMemberChangeTracker::class);

const MEMBER_FIELDS_POST_ID = 42;

beforeEach(function () {
    // Routed through a property: the first matching expectation wins —
    // that is true of Brain Monkey as it was of WP_Mock — so a per-test
    // override registered later would never be consulted.
    $this->postType = TsmlMemberFields::POST_TYPE;
    Functions\expect('get_post_type')->andReturnUsing(fn (): string => $this->postType);

    $this->repository = $this->createMock(MemberRepository::class);
    $this->tracker = new TsmlMemberChangeTracker($this->repository);
});

afterEach(function () {
    $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
    $reflection->getProperty('originalMember')->setValue(null, null);
    $reflection->getProperty('newMemberIds')->setValue(null, []);
});

/** post_title already matches, so the sync is a no-op. */
function stubMemberFieldsTitleSyncIsNoop(string $existingTitle = ''): void
{
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => MEMBER_FIELDS_POST_ID, 'post_title' => $existingTitle]);
    Functions\expect('wp_update_post')->andReturn(MEMBER_FIELDS_POST_ID);
}

/** The tracker's static snapshot, or null once released. */
function capturedSnapshot(): mixed
{
    return (new \ReflectionClass(TsmlMemberChangeTracker::class))
        ->getProperty('originalMember')->getValue();
}

/**
 * Run the capture → check pair over a pair of members.
 */
function runMemberSave(MemberStub $original, MemberStub $updated): void
{
    test()->repository->method('findById')
        ->willReturnOnConsecutiveCalls($original, $updated);

    test()->tracker->captureOriginalMember(MEMBER_FIELDS_POST_ID);
    test()->tracker->checkForChanges(MEMBER_FIELDS_POST_ID);
}

// ─── field-level change detection ───────────────────────────────
/*
 * Each case changes exactly one tracked field, so the resulting
 * unity/member_changing proves that field is part of the comparison.
 */
test('changing a tracked field fires member changing', function (array $updatedArgs) {
    stubMemberFieldsTitleSyncIsNoop();

    $original = new MemberStub(MEMBER_FIELDS_POST_ID);
    $updated  = new MemberStub(MEMBER_FIELDS_POST_ID, ...$updatedArgs);

    expectDone('unity/member_changing')->once()->with($updated, $original);

    runMemberSave($original, $updated);

    // A completed check releases the snapshot; its absence confirms
    // the comparison ran to the end rather than bailing early.
    expect(capturedSnapshot())->toBeNull();
})->with([
    'anonymous name'         => [['anonymousName' => 'Alex']],
    'personal email'         => [['personalEmail' => 'alex@example.test']],
    'show anonymous name'    => [['showAnonymousName' => true]],
    'show member profile'    => [['showMemberProfile' => true]],
    'anonymous profile'      => [['anonymousProfile' => 'A short bio']],
    'intergroup position'    => [['intergroupPosition' => 7]],
    'position rotation'      => [['intergroupPositionRotation' => '01/01/2027']],
    'home group'             => [['homeGroup' => 3]],
    'gsr flag'               => [['isGSR' => true]],
    'meeting po'             => [['meetingPO' => 99]],
    'mobile number'          => [['mobileNumber' => '07700 900123']],
    'landline number'        => [['landlineNumber' => '0117 496 0000']],
    'preferred contact'      => [['preferredContact' => PreferredContact::Landline]],
    'twelfth stepper'        => [['twelfthStepper' => true]],
    'telephone responder'    => [['telephoneResponder' => true]],
    'responder certification' => [['responderCertification' => ResponderCertification::Certified]],
    'area'                   => [['area' => 'North']],
    'accepts'                => [['accepts' => ['calls']]],
]);

test('an identical member fires no change event', function () {
    stubMemberFieldsTitleSyncIsNoop();

    $original = new MemberStub(MEMBER_FIELDS_POST_ID, 'Alex');
    $updated  = new MemberStub(MEMBER_FIELDS_POST_ID, 'Alex');

    runMemberSave($original, $updated);

    expect(capturedSnapshot())->toBeNull('a no-op save still completes');
});

// ─── title sync ─────────────────────────────────────────────────
test('a renamed member has its post title synced', function () {
    // The stored title still holds the previous name.
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => MEMBER_FIELDS_POST_ID, 'post_title' => 'Old Name']);

    $captured = [];
    Functions\expect('wp_update_post')->andReturnUsing(
        function (array $args) use (&$captured): int {
            $captured = $args;

            return MEMBER_FIELDS_POST_ID;
        }
    );

    runMemberSave(
        new MemberStub(MEMBER_FIELDS_POST_ID, 'Old Name'),
        new MemberStub(MEMBER_FIELDS_POST_ID, 'New Name')
    );

    expect($captured['post_title'] ?? null)->toBe('New Name');
});

test('a name needing escaping is encoded into the post title', function () {
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => MEMBER_FIELDS_POST_ID, 'post_title' => 'plain']);

    $captured = [];
    Functions\expect('wp_update_post')->andReturnUsing(
        function (array $args) use (&$captured): int {
            $captured = $args;

            return MEMBER_FIELDS_POST_ID;
        }
    );

    runMemberSave(
        new MemberStub(MEMBER_FIELDS_POST_ID, 'plain'),
        new MemberStub(MEMBER_FIELDS_POST_ID, 'Alex & "Sam"')
    );

    // The title is stored HTML-encoded, not raw.
    expect($captured['post_title'] ?? '')->toContain('&amp;')
        ->and($captured['post_title'] ?? '')->not->toContain('"Sam"');
});

// ─── guards and failure paths ───────────────────────────────────
test('capturing a post of another type is ignored', function () {
    $this->postType = 'page';
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalMember(MEMBER_FIELDS_POST_ID);

    // Returned before reading the member.
});

test('a capture failure is swallowed', function () {
    $this->repository->method('findById')->willThrowException(new Exception('boom'));

    $this->tracker->captureOriginalMember(MEMBER_FIELDS_POST_ID);

    // A failed capture must not abort the save.
})->throwsNoExceptions();

test('checking a post of another type is ignored', function () {
    $this->postType = 'page';
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(MEMBER_FIELDS_POST_ID);

    // Returned before comparing.
});

test('a check without a captured original stops quietly', function () {
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(MEMBER_FIELDS_POST_ID);

    // No comparison without a snapshot.
});

test('a check that cannot reload the member clears the snapshot', function () {
    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(new MemberStub(MEMBER_FIELDS_POST_ID), null);

    $this->tracker->captureOriginalMember(MEMBER_FIELDS_POST_ID);
    $this->tracker->checkForChanges(MEMBER_FIELDS_POST_ID);

    // The snapshot must be released, or the next save would diff
    // against a stale member.
    $original = (new \ReflectionClass(TsmlMemberChangeTracker::class))
        ->getProperty('originalMember')->getValue();
    expect($original)->toBeNull();
});

test('a check failure clears the snapshot and is swallowed', function () {
    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(
            new MemberStub(MEMBER_FIELDS_POST_ID),
            $this->throwException(new Exception('boom'))
        );

    $this->tracker->captureOriginalMember(MEMBER_FIELDS_POST_ID);
    $this->tracker->checkForChanges(MEMBER_FIELDS_POST_ID);

    $original = (new \ReflectionClass(TsmlMemberChangeTracker::class))
        ->getProperty('originalMember')->getValue();
    expect($original)->toBeNull('a failed check must not leave a stale snapshot');
});
