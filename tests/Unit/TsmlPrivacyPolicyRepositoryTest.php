<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFields;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyRepository;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyRepository;
use WP_Mock;

/**
 * Tests for TsmlPrivacyPolicyRepository.
 *
 * @covers \TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyRepository
 */
class TsmlPrivacyPolicyRepositoryTest extends TestCase
{
    use ActionExpectations;

    /** @var PrivacyPolicyFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlPrivacyPolicyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(PrivacyPolicyFactory::class);
        $this->repository = new TsmlPrivacyPolicyRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function policy(int $id = 0, string $title = 'Policy'): TsmlPrivacyPolicy
    {
        return new TsmlPrivacyPolicy($id, $title, 'body', '1.0', true, '');
    }

    /**
     * @test
     */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(PrivacyPolicyRepository::class, $this->repository);
    }

    // ─── findById ───────────────────────────────────────────────────

    /**
     * @test
     */
    public function find_by_id_returns_null_for_a_missing_post(): void
    {
        WP_Mock::userFunction('get_post')->with(9)->andReturn(null);

        $this->assertNull($this->repository->findById(9));
    }

    /**
     * @test
     */
    public function find_by_id_returns_null_for_the_wrong_post_type(): void
    {
        WP_Mock::userFunction('get_post')->with(9)->andReturn((object) ['post_type' => 'page']);

        $this->assertNull($this->repository->findById(9));
    }

    /**
     * @test
     */
    public function find_by_id_delegates_to_the_factory_for_a_matching_post(): void
    {
        WP_Mock::userFunction('get_post')->with(5)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );

        $expected = $this->policy(5);
        $this->factory->expects($this->once())
            ->method('createFromSource')->with(5)->willReturn($expected);

        $this->assertSame($expected, $this->repository->findById(5));
    }

    // ─── findActive / findAll / count ───────────────────────────────

    /**
     * @test
     */
    public function find_active_returns_null_when_no_active_policy_exists(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([]);

        $this->assertNull($this->repository->findActive());
    }

    /**
     * @test
     */
    public function find_active_reads_back_the_first_matching_policy(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([(object) ['ID' => 5]]);
        WP_Mock::userFunction('get_post')->with(5)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );

        $active = $this->policy(5);
        $this->factory->method('createFromSource')->with(5)->willReturn($active);

        $this->assertSame($active, $this->repository->findActive());
    }

    /**
     * @test
     */
    public function find_all_maps_posts_through_find_by_id(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([
            (object) ['ID' => 1],
            (object) ['ID' => 2],
        ]);
        WP_Mock::userFunction('get_post')->andReturnUsing(
            fn ($id) => (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );

        $a = $this->policy(1);
        $b = $this->policy(2);
        $this->factory->method('createFromSource')->willReturnMap([[1, $a], [2, $b]]);

        $this->assertSame([$a, $b], $this->repository->findAll());
    }

    /**
     * @test
     */
    public function count_returns_the_number_of_ids(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([10, 11, 12]);

        $this->assertSame(3, $this->repository->count());
    }

    /**
     * @test
     */
    public function count_is_zero_when_the_query_returns_a_non_array(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn(null);

        $this->assertSame(0, $this->repository->count());
    }

    // ─── save / create / update / delete ────────────────────────────

    /**
     * @test
     */
    public function save_inserts_a_new_policy_and_fires_created(): void
    {
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn(77);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('update_field')->andReturn(true);
        WP_Mock::userFunction('get_post')->with(77)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );

        $created = $this->policy(77);
        $this->factory->method('createFromSource')->with(77)->willReturn($created);

        WP_Mock::expectAction('unity/privacy_policy_created', $created);

        $this->assertTrue($this->repository->save($this->policy(0)));
    }

    /**
     * @test
     */
    public function save_returns_false_when_the_insert_fails(): void
    {
        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        $this->assertFalse($this->repository->save($this->policy(0)));
    }

    /**
     * @test
     */
    public function save_with_an_existing_id_delegates_to_update(): void
    {
        // update() path: no wp_insert_post, uses wp_update_post instead.
        WP_Mock::userFunction('get_post')->with(5)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );
        WP_Mock::userFunction('wp_update_post')->once()->andReturn(5);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('update_field')->andReturn(true);

        $persisted = $this->policy(5);
        $this->factory->method('createFromSource')->with(5)->willReturn($persisted);

        // Both before/after snapshots resolve to the same re-read instance.
        WP_Mock::expectAction('unity/privacy_policy_changing', $persisted, $persisted);

        $this->assertTrue($this->repository->save($this->policy(5)));
    }

    /**
     * @test
     */
    public function create_inserts_a_titled_post_and_returns_its_id(): void
    {
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn(88);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('get_post')->with(88)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );
        $created = $this->policy(88);
        $this->factory->method('createFromSource')->with(88)->willReturn($created);

        WP_Mock::expectAction('unity/privacy_policy_created', $created);

        $this->assertSame(88, $this->repository->create('New Policy'));
    }

    /**
     * @test
     */
    public function create_returns_zero_when_the_insert_fails(): void
    {
        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        $this->assertSame(0, $this->repository->create('New Policy'));
    }

    /**
     * @test
     */
    public function update_returns_false_for_a_zero_id(): void
    {
        $this->assertFalse($this->repository->update($this->policy(0)));
    }

    /**
     * @test
     */
    public function update_returns_false_when_wp_update_post_fails(): void
    {
        WP_Mock::userFunction('get_post')->with(5)->andReturn(
            (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
        );
        $this->factory->method('createFromSource')->with(5)->willReturn($this->policy(5));

        $error = new \stdClass();
        WP_Mock::userFunction('wp_update_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        $this->assertFalse($this->repository->update($this->policy(5)));
    }

    /**
     * @test
     */
    public function delete_force_deletes_the_post(): void
    {
        WP_Mock::userFunction('wp_delete_post')->once()->with(5, true)->andReturn((object) ['ID' => 5]);

        $this->assertTrue($this->repository->delete(5));
    }

    /**
     * @test
     */
    public function delete_returns_false_when_the_post_cannot_be_removed(): void
    {
        WP_Mock::userFunction('wp_delete_post')->once()->with(5, true)->andReturn(false);

        $this->assertFalse($this->repository->delete(5));
    }
}
