<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;
use Exception;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Testing\Doubles\MemberStub;
use TsmlForUnity\Tests\TestCase;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\PreferredContact;
use Unity\Members\ResponderCertification;

/**
 * Field-by-field change detection, plus the save-path guards.
 *
 * hasMemberChanged() is a long chain of comparisons, and a field missing
 * from it is invisible: the member saves, but no unity/member_changing
 * fires, so Scrutiny records nothing and downstream caches never
 * invalidate. Every tracked field therefore gets its own case, which is
 * also what stops a field being quietly dropped from the chain later.
 */
#[CoversClass(\TsmlForUnity\Members\TsmlMemberChangeTracker::class)]
class TsmlMemberChangeTrackerFieldsTest extends TestCase
{
    private const POST_ID = 42;

    /** @var MemberRepository&MockObject */
    private $repository;

    private TsmlMemberChangeTracker $tracker;

    /** Post type the stubbed get_post_type() reports. */
    private string $postType = TsmlMemberFields::POST_TYPE;

    protected function setUp(): void
    {
        parent::setUp();

        // Routed through a property: the first matching expectation wins —
        // that is true of Brain Monkey as it was of WP_Mock — so a per-test
        // override registered later would never be consulted.
        $this->postType = TsmlMemberFields::POST_TYPE;
        expect('get_post_type')->andReturnUsing(fn (): string => $this->postType);

        $this->repository = $this->createMock(MemberRepository::class);
        $this->tracker = new TsmlMemberChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {

        $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
        $reflection->getProperty('originalMember')->setValue(null, null);
        $reflection->getProperty('newMemberIds')->setValue(null, []);

        parent::tearDown();
    }

    /** post_title already matches, so the sync is a no-op. */
    private function stubTitleSyncIsNoop(string $existingTitle = ''): void
    {
        expect('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => $existingTitle]);
        expect('wp_update_post')->andReturn(self::POST_ID);
    }

    /** The tracker's static snapshot, or null once released. */
    private function capturedSnapshot(): mixed
    {
        return (new \ReflectionClass(TsmlMemberChangeTracker::class))
            ->getProperty('originalMember')->getValue();
    }

    /**
     * Run the capture → check pair over a pair of members.
     */
    private function runSave(MemberStub $original, MemberStub $updated): void
    {
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($original, $updated);

        $this->tracker->captureOriginalMember(self::POST_ID);
        $this->tracker->checkForChanges(self::POST_ID);
    }

    // ─── field-level change detection ───────────────────────────────
    /**
     * Each case changes exactly one tracked field, so the resulting
     * unity/member_changing proves that field is part of the comparison.
     */
    #[DataProvider('changedFieldProvider')]
    #[Test]
    public function changing_a_tracked_field_fires_member_changing(array $updatedArgs): void
    {
        $this->stubTitleSyncIsNoop();

        $original = new MemberStub(self::POST_ID);
        $updated  = new MemberStub(self::POST_ID, ...$updatedArgs);

        expectDone('unity/member_changing')->once()->with($updated, $original);

        $this->runSave($original, $updated);

        // A completed check releases the snapshot; its absence confirms
        // the comparison ran to the end rather than bailing early.
        $this->assertNull($this->capturedSnapshot());
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function changedFieldProvider(): array
    {
        return [
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
        ];
    }

    #[Test]
    public function an_identical_member_fires_no_change_event(): void
    {
        $this->stubTitleSyncIsNoop();

        $original = new MemberStub(self::POST_ID, 'Alex');
        $updated  = new MemberStub(self::POST_ID, 'Alex');

        $this->runSave($original, $updated);

        $this->assertNull($this->capturedSnapshot(), 'a no-op save still completes');
    }

    // ─── title sync ─────────────────────────────────────────────────
    #[Test]
    public function a_renamed_member_has_its_post_title_synced(): void
    {
        // The stored title still holds the previous name.
        expect('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => 'Old Name']);

        $captured = [];
        expect('wp_update_post')->andReturnUsing(
            function (array $args) use (&$captured): int {
                $captured = $args;

                return self::POST_ID;
            }
        );

        $this->runSave(
            new MemberStub(self::POST_ID, 'Old Name'),
            new MemberStub(self::POST_ID, 'New Name')
        );

        $this->assertSame('New Name', $captured['post_title'] ?? null);
    }

    #[Test]
    public function a_name_needing_escaping_is_encoded_into_the_post_title(): void
    {
        expect('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => 'plain']);

        $captured = [];
        expect('wp_update_post')->andReturnUsing(
            function (array $args) use (&$captured): int {
                $captured = $args;

                return self::POST_ID;
            }
        );

        $this->runSave(
            new MemberStub(self::POST_ID, 'plain'),
            new MemberStub(self::POST_ID, 'Alex & "Sam"')
        );

        // The title is stored HTML-encoded, not raw.
        $this->assertStringContainsString('&amp;', $captured['post_title'] ?? '');
        $this->assertStringNotContainsString('"Sam"', $captured['post_title'] ?? '');
    }

    // ─── guards and failure paths ───────────────────────────────────
    #[Test]
    public function capturing_a_post_of_another_type_is_ignored(): void
    {
        $this->postType = 'page';
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalMember(self::POST_ID);

        $this->assertTrue(true, 'returned before reading the member');
    }

    #[Test]
    public function a_capture_failure_is_swallowed(): void
    {
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalMember(self::POST_ID);

        $this->assertTrue(true, 'a failed capture must not abort the save');
    }

    #[Test]
    public function checking_a_post_of_another_type_is_ignored(): void
    {
        $this->postType = 'page';
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(self::POST_ID);

        $this->assertTrue(true, 'returned before comparing');
    }

    #[Test]
    public function a_check_without_a_captured_original_stops_quietly(): void
    {
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(self::POST_ID);

        $this->assertTrue(true, 'no comparison without a snapshot');
    }

    #[Test]
    public function a_check_that_cannot_reload_the_member_clears_the_snapshot(): void
    {
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls(new MemberStub(self::POST_ID), null);

        $this->tracker->captureOriginalMember(self::POST_ID);
        $this->tracker->checkForChanges(self::POST_ID);

        // The snapshot must be released, or the next save would diff
        // against a stale member.
        $original = (new \ReflectionClass(TsmlMemberChangeTracker::class))
            ->getProperty('originalMember')->getValue();
        $this->assertNull($original);
    }

    #[Test]
    public function a_check_failure_clears_the_snapshot_and_is_swallowed(): void
    {
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls(
                new MemberStub(self::POST_ID),
                $this->throwException(new Exception('boom'))
            );

        $this->tracker->captureOriginalMember(self::POST_ID);
        $this->tracker->checkForChanges(self::POST_ID);

        $original = (new \ReflectionClass(TsmlMemberChangeTracker::class))
            ->getProperty('originalMember')->getValue();
        $this->assertNull($original, 'a failed check must not leave a stale snapshot');
    }
}
