<?php

declare(strict_types=1);

namespace TsmlForUnity\PrivacyPolicies;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\PrivacyPolicies\Interfaces\PrivacyPolicy;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;
use function get_field;
use function get_post;

/**
 * TSML Privacy Policy Factory
 *
 * Builds TsmlPrivacyPolicy objects either from a persisted post (via ACF
 * field reads) or from raw values supplied by the caller. Mirrors the
 * shape of TsmlMemberFactory.
 */
class TsmlPrivacyPolicyFactory implements PrivacyPolicyFactory
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(int $id): PrivacyPolicy
    {
        $post = get_post($id);

        // post_title is the source of truth for the title field. Decode
        // any HTML entities that wp_insert_post may have introduced so
        // callers see the original characters they wrote.
        $title = ($post && isset($post->post_title))
            ? html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';

        // Use post_modified_gmt (UTC) rather than post_modified (site-local
        // timezone) so the value is safe to expose via REST and to compare
        // across sites — matches the convention in TsmlMemberFactory.
        $updated = ($post && isset($post->post_modified_gmt)) ? $post->post_modified_gmt : '';

        $policy = (string) (get_field(TsmlPrivacyPolicyFields::FIELD_POLICY, $id) ?? '');
        $version = (string) (get_field(TsmlPrivacyPolicyFields::FIELD_VERSION, $id) ?? '');
        $active = (bool) (get_field(TsmlPrivacyPolicyFields::FIELD_ACTIVE, $id) ?? false);

        return new TsmlPrivacyPolicy(
            $id,
            $title,
            $policy,
            $version,
            $active,
            $updated
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createNew(
        int $id,
        string $title = '',
        string $policy = '',
        string $version = '',
        bool $active = false,
        string $updated = ''
    ): PrivacyPolicy {
        return new TsmlPrivacyPolicy(
            $id,
            $title,
            $policy,
            $version,
            $active,
            $updated
        );
    }
}
