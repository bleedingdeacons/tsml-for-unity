<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Members\Interfaces\Member;
use Unity\Members\PreferredContact;
use Unity\Members\ResponderCertification;

covers(\TsmlForUnity\Members\TsmlMemberFactory::class);

/**
 * The GDPR acceptance fields as unset.
 *
 * The factory reads all five on every createFromSource, so they need a
 * value even for tests that say nothing about GDPR.
 */
const GDPR_FIELDS_UNSET = [
        TsmlMemberFields::FIELD_GDPR_ACCEPTED => false,
        TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT => '',
    ];

beforeEach(function () {
    $this->factory = new TsmlMemberFactory();

    // Every createFromSource reads post_modified_gmt for the updated
    // timestamp; no test here asserts on it.
    Functions\expect('get_post')
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
});

it('creates member with basic fields', function () {
    $postId = 123;

    stubMemberFields([
        TsmlMemberFields::FIELD_ANONYMOUS_NAME => 'John D.',
        TsmlMemberFields::FIELD_PERSONAL_EMAIL => 'john@example.com',
        TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME => true,
        TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE => false,
        TsmlMemberFields::FIELD_ANONYMOUS_PROFILE => 'Anonymous profile text',
        TsmlMemberFields::FIELD_INTERGROUP_POSITION => 5,
        TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION => '2024-01-01',
        TsmlMemberFields::FIELD_HOME_GROUP => 42,
        TsmlMemberFields::FIELD_HOMEGROUP_GSR => true,
        TsmlMemberFields::FIELD_MEETING_PO => null,
        TsmlMemberFields::FIELD_MOBILE_NUMBER => '555-1234',
        TsmlMemberFields::FIELD_LANDLINE_NUMBER => '0117 496 0000',
        TsmlMemberFields::FIELD_PREFERRED_CONTACT => 'Landline',
        TsmlMemberFields::FIELD_TWELFTH_STEPPER => true,
        TsmlMemberFields::FIELD_TELEPHONE_RESPONDER => true,
        TsmlMemberFields::FIELD_RESPONDER_CERTIFICATION => 'Certified',
        TsmlMemberFields::FIELD_AREA => 'North London',
        TsmlMemberFields::FIELD_ACCEPTS => ['phone', 'email'],
    ]);

    mockGdprFields($postId);

    $member = $this->factory->createFromSource($postId);

    expect($member)->toBeInstanceOf(Member::class)
        ->and($member->getId())->toBe($postId)
        ->and($member->getAnonymousName())->toBe('John D.')
        ->and($member->showAnonymousName())->toBeTrue()
        ->and($member->showMemberProfile())->toBeFalse()
        ->and($member->getAnonymousProfile())->toBe('Anonymous profile text')
        ->and($member->getIntergroupPosition())->toBe(5)
        ->and($member->getIntergroupPositionRotation())->toBe('2024-01-01')
        ->and($member->getHomeGroup())->toBe(42)
        ->and($member->isGSR())->toBeTrue()
        ->and($member->getMeetingPO())->toBeNull()
        ->and($member->getPersonalEmail())->toBe('john@example.com')
        ->and($member->getMobileNumber())->toBe('555-1234')
        ->and($member->getLandlineNumber())->toBe('0117 496 0000')
        ->and($member->getPreferredContact())->toBe(PreferredContact::Landline)
        ->and($member->isTwelfthStepper())->toBeTrue()
        ->and($member->isTelephoneResponder())->toBeTrue()
        ->and($member->getResponderCertification())->toBe(ResponderCertification::Certified)
        ->and($member->getArea())->toBe('North London')
        ->and($member->getAccepts())->toBe(['phone', 'email']);
});

it('handles home group as array', function () {
    $postId = 124;

    // ACF hands back real WP_Post objects, and the factory type-checks for
    // them, so a stdClass would silently fall through to the ID default.
    $wpPost1 = new \WP_Post(['ID' => 99, 'post_type' => 'tsml_group']);
    $wpPost2 = new \WP_Post(['ID' => 100, 'post_type' => 'tsml_group']);

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_HOME_GROUP => [$wpPost1, $wpPost2],  // ACF relationship field returns array of WP_Post objects
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getHomeGroup())->toBe(99); // Should use ID from first WP_Post object
});

it('handles home group as wp post object', function () {
    $postId = 127;

    $wpPost = new \WP_Post(['ID' => 42, 'post_type' => 'tsml_group', 'post_title' => 'Test Group']);

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_HOME_GROUP => $wpPost,  // ACF post object field returns single WP_Post
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getHomeGroup())->toBe(42); // Should use ID from WP_Post object
});

it('handles home group as numeric array', function () {
    $postId = 128;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_HOME_GROUP => [55, 56],  // Array of numeric IDs (legacy format)
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getHomeGroup())->toBe(55); // Should use first numeric ID
});

it('handles empty home group array', function () {
    $postId = 125;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_HOME_GROUP => [],  // Empty array
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getHomeGroup())->toBe(0); // Should default to 0
});

it('handles null home group', function () {
    $postId = 129;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_HOME_GROUP => null,  // get_field returns null
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getHomeGroup())->toBe(0); // Should default to 0
});

it('handles null fields with defaults', function () {
    $postId = 126;

    // Mock all fields returning null
    Functions\expect('get_field')
        ->andReturn(null);

    $member = $this->factory->createFromSource($postId);

    expect($member)->toBeInstanceOf(Member::class)
        ->and($member->getId())->toBe($postId)
        ->and($member->getAnonymousName())->toBe('')
        ->and($member->showAnonymousName())->toBeFalse()
        ->and($member->showMemberProfile())->toBeFalse()
        ->and($member->getAnonymousProfile())->toBe('')
        ->and($member->getIntergroupPosition())->toBe(0)
        ->and($member->getIntergroupPositionRotation())->toBe('')
        ->and($member->getHomeGroup())->toBe(0)
        ->and($member->isGSR())->toBeFalse()
        ->and($member->getMeetingPO())->toBeNull()
        ->and($member->getPersonalEmail())->toBe('')
        ->and($member->getMobileNumber())->toBe('')
        ->and($member->isTwelfthStepper())->toBeFalse()
        ->and($member->isTelephoneResponder())->toBeFalse()
        ->and($member->getResponderCertification())->toBe(ResponderCertification::None)
        ->and($member->getArea())->toBe('')
        ->and($member->getAccepts())->toBe([]);
});

/**
 * Stub every field the factory reads, with defaults.
 *
 * A single call, because a test cannot layer a second get_field stub on
 * top of this one — see stubFields(). Per-test values go in $overrides.
 *
 * @param array<string, mixed> $overrides Field name => value.
 */
function mockDefaultFields(int $postId, array $overrides = []): void
{
    stubMemberFields(array_merge([
        TsmlMemberFields::FIELD_ANONYMOUS_NAME => '',
        TsmlMemberFields::FIELD_PERSONAL_EMAIL => '',
        TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME => false,
        TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE => false,
        TsmlMemberFields::FIELD_ANONYMOUS_PROFILE => '',
        TsmlMemberFields::FIELD_INTERGROUP_POSITION => 0,
        TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION => '',
        TsmlMemberFields::FIELD_HOMEGROUP_GSR => false,
        TsmlMemberFields::FIELD_MEETING_PO => null,
        TsmlMemberFields::FIELD_MOBILE_NUMBER => '',
        TsmlMemberFields::FIELD_LANDLINE_NUMBER => '',
        // Conditional logic hides this field for a member with no
        // landline, so ACF returns nothing for it.
        TsmlMemberFields::FIELD_PREFERRED_CONTACT => null,
        TsmlMemberFields::FIELD_TWELFTH_STEPPER => false,
        TsmlMemberFields::FIELD_TELEPHONE_RESPONDER => false,
        // Conditional logic hides this field for a non-responder, so ACF
        // returns nothing for it.
        TsmlMemberFields::FIELD_RESPONDER_CERTIFICATION => null,
        TsmlMemberFields::FIELD_AREA => '',
        TsmlMemberFields::FIELD_ACCEPTS => null,
    ], GDPR_FIELDS_UNSET, $overrides));
}

function mockGdprFields(int $postId): void
{
    stubMemberFields([
        TsmlMemberFields::FIELD_GDPR_ACCEPTED => false,
        TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT => '',
    ]);
}

test('a member with no landline reads back as preferring mobile', function () {
    $postId = 400;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_MOBILE_NUMBER => '07700 900123',
        TsmlMemberFields::FIELD_LANDLINE_NUMBER => '',
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getLandlineNumber())->toBe('')
        ->and($member->getPreferredContact())->toBe(PreferredContact::Mobile);
});

/*
 * ACF keeps the last saved value of a field its conditional logic later
 * hides, so deleting a member's landline leaves 'Landline' in postmeta.
 * Reading that back as-is would point the helpline at a number that is
 * no longer there.
 */
test('a stale landline preference is dropped when the number goes', function () {
    $postId = 401;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_MOBILE_NUMBER => '07700 900123',
        TsmlMemberFields::FIELD_LANDLINE_NUMBER => '',
        TsmlMemberFields::FIELD_PREFERRED_CONTACT => 'Landline',
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getPreferredContact())->toBe(PreferredContact::Mobile);
});

test('a member with a landline keeps the saved preference', function () {
    $postId = 402;

    mockDefaultFields($postId, [
        TsmlMemberFields::FIELD_LANDLINE_NUMBER => '0117 496 0000',
        TsmlMemberFields::FIELD_PREFERRED_CONTACT => 'Landline',
    ]);

    $member = $this->factory->createFromSource($postId);

    expect($member->getPreferredContact())->toBe(PreferredContact::Landline);
});

/*
 * createNew() settles the same invariant as createFromSource(), because
 * an importer can hand it a preference with no number behind it —
 * Reconcile does exactly that when a spreadsheet column is blank.
 */
test('create new refuses a landline preference with no landline', function () {
    $member = $this->factory->createNew(
        id: 403,
        landlineNumber: '',
        preferredContact: PreferredContact::Landline
    );

    expect($member->getPreferredContact())->toBe(PreferredContact::Mobile);
});

test('create new keeps a landline preference that has a number', function () {
    $member = $this->factory->createNew(
        id: 404,
        landlineNumber: '0117 496 0000',
        preferredContact: PreferredContact::Landline
    );

    expect($member->getLandlineNumber())->toBe('0117 496 0000')
        ->and($member->getPreferredContact())->toBe(PreferredContact::Landline);
});

/**
 * Stub get_field() for a whole set of fields at once.
 *
 * One expectation, dispatching on the field name. Stacking
 * Functions\expect('get_field')->with(...) calls does not work the way the
 * WP_Mock equivalent did: Brain Monkey keeps one stub per function per
 * test, and the first one registered answers every call whatever its
 * ->with() says. The failure is silent, so the mapping is explicit.
 *
 * @param array<string, mixed> $fields Field name => value.
 */
function stubMemberFields(array $fields): void
{
    Functions\expect('get_field')->andReturnUsing(
        static fn (string $field, int $postId): mixed => $fields[$field] ?? null
    );
}
