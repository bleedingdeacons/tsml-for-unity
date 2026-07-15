<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Tests\Fixtures\MemberStub;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberFactory;
use WP_Mock;

/**
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
 *
 * @covers \TsmlForUnity\Members\TsmlMemberRepository
 */
class TsmlMemberRepositoryTest extends TestCase
{
    /** @var MemberFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlMemberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(MemberFactory::class);
        $this->repository = new TsmlMemberRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Helper: stub get_post / get_post_type for findById's pre-check
     * so the factory's createFromSource() is reached.
     */
    private function stubExistingPost(int $postId): void
    {
        WP_Mock::userFunction('get_post')
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
    private function allowAnyUpdateFieldCalls(): void
    {
        WP_Mock::userFunction('update_field')->andReturn(true);
    }

    // ─── update() fires unity/member_changing ───────────────────────

    /**
     * @test
     */
    public function update_fires_member_changing_with_original_and_updated_members(): void
    {
        $postId = 23462;

        // Two distinct member objects: original (mobile = old) and
        // updated (mobile = new). The factory returns the original on
        // the first findById() (before writes) and the updated on the
        // second (after writes).
        $original = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'OLD-MOBILE');
        $updated  = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');

        $this->stubExistingPost($postId);
        $this->factory->expects($this->exactly(2))
            ->method('createFromSource')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        WP_Mock::expectAction('unity/member_changing', $updated, $original);

        $caller = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', 'NEW-MOBILE');
        $result = $this->repository->update($caller);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function update_does_not_fire_member_changing_when_wp_update_post_fails(): void
    {
        $postId = 23462;

        $original = new MemberStub($postId);

        $this->stubExistingPost($postId);
        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($postId)
            ->willReturn($original);

        // Simulate wp_update_post returning a WP_Error.
        $error = new \stdClass();
        WP_Mock::userFunction('wp_update_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field calls and no event fired — assert by absence.

        $caller = new MemberStub($postId, 'Anon');
        $result = $this->repository->update($caller);

        $this->assertFalse($result);
        // If the action had fired, WP_Mock::tearDown() would not flag
        // it because we didn't expect-and-fail; but the more important
        // assertion is that we never reached findField/createFromSource
        // a second time (verified by the once() expectation above).
    }

    /**
     * @test
     */
    public function update_returns_false_for_zero_post_id_and_does_nothing(): void
    {
        // Zero ID never reaches findById, wp_update_post, or update_field.
        // No WP_Mock expectations: any call would fail the test.

        $caller = new MemberStub(0, 'Anon');
        $result = $this->repository->update($caller);

        $this->assertFalse($result);
    }

    // ─── save() insert path fires unity/member_created ──────────────

    /**
     * @test
     */
    public function save_insert_fires_member_created_after_writes(): void
    {
        $newPostId = 99999;

        // Caller submits a Member with id=0 (insert).
        $caller = new MemberStub(0, 'New Anon');

        // After insert + updateFields, findById is called once to
        // re-read the persisted state. The factory returns the
        // member as it exists in storage.
        $persisted = new MemberStub($newPostId, 'New Anon');

        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($newPostId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        // findById's pre-check
        WP_Mock::userFunction('get_post')
            ->with($newPostId)
            ->andReturn((object) [
                'ID' => $newPostId,
                'post_type' => TsmlMemberFields::POST_TYPE,
            ]);

        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($newPostId)
            ->willReturn($persisted);

        WP_Mock::expectAction('unity/member_created', $persisted);

        $result = $this->repository->save($caller);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function save_insert_does_not_fire_member_created_when_wp_insert_post_fails(): void
    {
        $caller = new MemberStub(0, 'New Anon');

        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field, no get_post, no createFromSource: a
        // failure to insert returns false before any of those.

        $result = $this->repository->save($caller);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function save_with_existing_id_delegates_to_update_and_fires_member_changing(): void
    {
        // save() with id > 0 must delegate to update() — verified by
        // observing the same unity/member_changing event update() fires,
        // not unity/member_created.

        $postId = 23462;

        $original = new MemberStub($postId, 'Old Anon');
        $updated  = new MemberStub($postId, 'New Anon');

        $this->stubExistingPost($postId);
        $this->factory->expects($this->exactly(2))
            ->method('createFromSource')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        $this->allowAnyUpdateFieldCalls();

        WP_Mock::expectAction('unity/member_changing', $updated, $original);

        $caller = new MemberStub($postId, 'New Anon');
        $result = $this->repository->save($caller);

        $this->assertTrue($result);
    }

    // ─── findByEmail() ──────────────────────────────────────────────

    /**
     * @test
     */
    public function find_by_email_returns_member_when_acf_field_matches(): void
    {
        $postId = 4242;
        $email  = 'member@example.test';

        // findByEmail() builds a get_posts query that:
        //  - filters by the members CPT and 'publish' status (defaults)
        //  - limits to one post (numberposts => 1)
        //  - meta_query keys on the personal-email ACF field
        WP_Mock::userFunction('get_posts')
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
            ->andReturn([(object) ['ID' => $postId]]);

        $this->stubExistingPost($postId);

        $expected = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, $email);
        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($postId)
            ->willReturn($expected);

        $result = $this->repository->findByEmail($email);

        $this->assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function find_by_email_returns_null_when_no_member_matches(): void
    {
        // No matching posts → findAll() returns [] → findByEmail() returns null.
        WP_Mock::userFunction('get_posts')->once()->andReturn([]);

        // Factory must not be called when there are no posts.
        $this->factory->expects($this->never())->method('createFromSource');

        $this->assertNull($this->repository->findByEmail('missing@example.test'));
    }

    /**
     * @test
     */
    public function find_by_email_returns_null_for_empty_string_without_querying(): void
    {
        // Empty input short-circuits before any DB work. No WP_Mock
        // expectations: a get_posts call would fail the test.
        $this->factory->expects($this->never())->method('createFromSource');

        $this->assertNull($this->repository->findByEmail(''));
    }

    // ─── findTelephoneResponders() ──────────────────────────────────

    /**
     * @test
     */
    public function find_telephone_responders_queries_the_responder_flag_and_returns_members(): void
    {
        $postId = 7777;

        // findTelephoneResponders() runs a single get_posts query that:
        //  - filters by the members CPT and 'publish' status
        //  - asks for ids only ('fields' => 'ids') so the build path
        //    goes straight through the factory, no per-post get_post
        //  - meta_query keys on the telephone-responder ACF field,
        //    matching the ACF true_false stored value '1'
        WP_Mock::userFunction('get_posts')
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

        $expected = new MemberStub($postId, 'Anon', false, false, '', 0, '', 0, false, null, '', '', false, true);
        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($postId)
            ->willReturn($expected);

        $result = $this->repository->findTelephoneResponders();

        $this->assertSame([$expected], $result);
    }

    /**
     * @test
     */
    public function find_telephone_responders_returns_empty_array_when_none_match(): void
    {
        // No matching posts → findAll() returns [] → method returns [].
        WP_Mock::userFunction('get_posts')->once()->andReturn([]);

        $this->factory->expects($this->never())->method('createFromSource');

        $this->assertSame([], $this->repository->findTelephoneResponders());
    }
}
