<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Field Constants for TSML Position
 */
final class TsmlPositionFields
{
    public const MINIMUM_SOBRIETY = 'position-minimum-sobriety';
    public const TERM_YEARS = 'position-term-years';
    public const EMAIL_ADDRESS = 'position-generic-email-address';
    public const LONG_NAME = 'position-long-name';
    public const SHORT_DESCRIPTION = 'position-short-description';
    public const SUMMARY = 'position-summary';
    
    public const POST_TYPE = 'intergroup-position';

    private function __construct()
    {
    }
}
