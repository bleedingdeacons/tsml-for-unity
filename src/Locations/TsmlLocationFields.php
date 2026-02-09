<?php

declare(strict_types=1);

namespace TsmlForUnity\Locations;

/**
 * Field Constants for TSML Location
 *
 * These field names correspond to the TSML plugin's location post type meta fields.
 */
final class TsmlLocationFields
{
    /**
     * The WordPress custom post type for locations (from TSML plugin)
     */
    public const POST_TYPE = 'tsml_location';

    /**
     * Formatted address field
     */
    public const ADDRESS = 'formatted_address';

    /**
     * City field
     */
    public const CITY = 'city';

    /**
     * State/province field
     */
    public const STATE = 'state';

    /**
     * Postal/zip code field
     */
    public const POSTAL_CODE = 'postal_code';

    /**
     * Country field
     */
    public const COUNTRY = 'country';

    /**
     * Region taxonomy
     */
    public const REGION_TAXONOMY = 'tsml_region';

    /**
     * Location notes/description field
     */
    public const NOTES = 'location_notes';

    /**
     * Latitude coordinate field
     */
    public const LATITUDE = 'latitude';

    /**
     * Longitude coordinate field
     */
    public const LONGITUDE = 'longitude';

    /**
     * Timezone field
     */
    public const TIMEZONE = 'timezone';

    /**
     * Location ID meta key used by meetings to reference their location
     */
    public const LOCATION_ID_META_KEY = 'location_id';

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
