<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Positions\Interfaces\PositionFactoryInterface;
use Unity\Positions\Interfaces\PositionInterface;
use Unity\Positions\Position;
use Unity\Positions\PositionFields;
use function get_fields;
use function get_permalink;
use function get_post;

/**
 * Concrete Position Factory class
 */
class TsmlPositionFactory implements PositionFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(int $sourceId): ?PositionInterface
    {
        $post = get_post($sourceId);

        if (!$post || $post->post_type !== PositionFields::POSITION_POST_TYPE) {
            return null;
        }

        $acfData = [];

        if (function_exists('get_fields')) {
            $acfData = get_fields($sourceId) ?: [];
        }

        $acfData = array_merge([
            PositionFields::MINIMUM_SOBRIETY => 6,
            PositionFields::TERM_YEARS => 1,
            PositionFields::EMAIL_ADDRESS => '',
            PositionFields::LONG_NAME => '',
            PositionFields::SHORT_DESCRIPTION => '',
            PositionFields::SUMMARY => '',
        ], $acfData);

        $link = get_permalink($sourceId) ?: '';

        return new Position(
            $sourceId,
            (int) $acfData[PositionFields::MINIMUM_SOBRIETY],
            (int) $acfData[PositionFields::TERM_YEARS],
            (string) $acfData[PositionFields::EMAIL_ADDRESS],
            (string) $acfData[PositionFields::LONG_NAME],
            (string) $acfData[PositionFields::SHORT_DESCRIPTION],
            (string) $acfData[PositionFields::SUMMARY],
            $link
        );
    }
}
