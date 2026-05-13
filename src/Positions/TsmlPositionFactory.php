<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Positions\Interfaces\PositionFactory;
use Unity\Positions\Interfaces\Position;

use function get_fields;
use function get_permalink;
use function get_post;

/**
 * Concrete Position Factory class
 */
class TsmlPositionFactory implements PositionFactory
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(int $sourceId): ?Position
    {
        $post = get_post($sourceId);

        if (!$post || $post->post_type !== TsmlPositionFields::POST_TYPE) {
            return null;
        }

        $acfData = [];

        if (function_exists('get_fields')) {
            $acfData = get_fields($sourceId) ?: [];
        }

        $acfData = array_merge([
            TsmlPositionFields::MINIMUM_SOBRIETY => 6,
            TsmlPositionFields::TERM_YEARS => 1,
            TsmlPositionFields::EMAIL_ADDRESS => '',
            TsmlPositionFields::LONG_NAME => '',
            TsmlPositionFields::SHORT_DESCRIPTION => '',
            TsmlPositionFields::SUMMARY => '',
        ], $acfData);

        $link = get_permalink($sourceId) ?: '';

        return new TsmlPosition(
            $sourceId,
            (int) $acfData[TsmlPositionFields::MINIMUM_SOBRIETY],
            (int) $acfData[TsmlPositionFields::TERM_YEARS],
            (string) $acfData[TsmlPositionFields::EMAIL_ADDRESS],
            html_entity_decode((string) $acfData[TsmlPositionFields::LONG_NAME], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            (string) $acfData[TsmlPositionFields::SHORT_DESCRIPTION],
            (string) $acfData[TsmlPositionFields::SUMMARY],
            $link,
            $post->post_modified_gmt ?? ''
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createNew(
        int $id,
        int $minimumSobriety = 6,
        int $termYears = 1,
        string $email = '',
        string $longName = '',
        string $shortDescription = '',
        string $summary = ''
    ): Position {
        $link = $id > 0 ? (get_permalink($id) ?: '') : '';

        return new TsmlPosition(
            $id,
            $minimumSobriety,
            $termYears,
            $email,
            $longName,
            $shortDescription,
            $summary,
            $link,
            ''
        );
    }
}
