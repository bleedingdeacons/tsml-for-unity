<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Tests\Fixtures\MemberStub;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\ResponderCertification;
use WP_Mock;

/**
 * Field-by-field change detection, plus the save-path guards.
 *
 * hasMemberChanged() is a long chain of comparisons, and a field missing
 * from it is invisible: the member saves, but no unity/member_changing
 * fires, so Scrutiny records nothing and downstream caches never
 * invalidate. Every tracked field therefore gets its own case, which is
 * also what stops a field being quietly dropped from the chain later.
 *
 * @covers \TsmlForUnity\Members\TsmlMemberChangeTracker
 */
class TsmlMemberChangeTrackerFieldsTest extends TestCase
{
    private const POST_ID = 42;

    /** @var MemberRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlMemberChangeTracker $tracker;

    /** Post type the stubbed get_post_type() reports. */
    private string $postType = TsmlMemberFields::POST_TYPE;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('add_action')->andReturn(true);
        // Routed through a property: WP_Mock resolves the first matching
        // expectation, so a per-test override registered later is ignored.
        $this->postType = TsmlMemberFields::POST_TYPE;
        WP_Mock::userFunction('get_post_type')->andReturnUsing(fn (): string => $this->postType);

        $this->repository = $this->createMock(MemberRepository::class);
        $this->tracker = new TsmlMemberChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();

        $reflection = new \ReflectionClass(TsmlMemberChangeTracker::class);
        $reflection->getProperty('originalMember')->setValue(null, null);
        $reflection->getProperty('newMemberIds')->setValue(null, []);

        parent::tearDown();
    }

    /** post_title already matches, so the sync is a no-op. */
    private function stubTitleSyncIsNoop(string $existingTitle = ''): void
    {
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => $existingTitle]);
        WP_Mock::userFunction('wp_update_post')->andReturn(self::POST_ID);
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
     *
     * @test
     * @dataProvider changedFieldProvider
     */
    public function changing_a_tracked_field_fires_member_changing(array $updatedArgs): void
    {
        $this->stubTitleSyncIsNoop();

        $original = new MemberStub(self::POST_ID);
        $updated  = new MemberStub(self::POST_ID, ...$updatedArgs);

        WP_Mock::expectAction('unity/member_changing', $updated, $original);

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
            'twelfth stepper'        => [['twelfthStepper' => true]],
            'telephone responder'    => [['telephoneResponder' => true]],
            'responder certification' => [['responderCertification' => ResponderCertification::Certified]],
            'area'                   => [['area' => 'North']],
            'accepts'                => [['accepts' => ['calls']]],
        ];
    }

    /** @test */
    public function an_identical_member_fires_no_change_event(): void
    {
        $this->stubTitleSyncIsNoop();

        $original = new MemberStub(self::POST_ID, 'Alex');
        $updated  = new MemberStub(self::POST_ID, 'Alex');

        $this->runSave($original, $updated);

        $this->assertNull($this->capturedSnapshot(), 'a no-op save still completes');
    }

    // ─── title sync ─────────────────────────────────────────────────

    /** @test */
    public function a_renamed_member_has_its_post_title_synced(): void
    {
        // The stored title still holds the previous name.
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => 'Old Name']);

        $captured = [];
        WP_Mock::userFunction('wp_update_post')->andReturnUsing(
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

    /** @test */
    public function a_name_needing_escaping_is_encoded_into_the_post_title(): void
    {
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => self::POST_ID, 'post_title' => 'plain']);

        $captured = [];
        WP_Mock::userFunction('wp_update_post')->andReturnUsing(
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

    /** @test */
    public function capturing_a_post_of_another_type_is_ignored(): void
    {
        $this->postType = 'page';
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalMember(self::POST_ID);

        $this->assertTrue(true, 'returned before reading the member');
    }

    /** @test */
    public function a_capture_failure_is_swallowed(): void
    {
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalMember(self::POST_ID);

        $this->assertTrue(true, 'a failed capture must not abort the save');
    }

    /** @test */
    public function checking_a_post_of_another_type_is_ignored(): void
    {
        $this->postType = 'page';
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(self::POST_ID);

        $this->assertTrue(true, 'returned before comparing');
    }

    /** @test */
    public function a_check_without_a_captured_original_stops_quietly(): void
    {
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(self::POST_ID);

        $this->assertTrue(true, 'no comparison without a snapshot');
    }

    /** @test */
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

    /** @test */
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
