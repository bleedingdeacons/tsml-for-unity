<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Field Constants for TSML Group
 *
 * These field names correspond to the TSML plugin's group post type meta fields.
 */
final class TsmlGroupFields
{
    /**
     * The WordPress custom post type for groups (from TSML plugin)
     */
    public const POST_TYPE = 'tsml_group';
//    public const GROUP_POST_TYPE = 'home-group';

    /**
     * Group title field
     */
    public const TITLE = 'group-title';

    /**
     * Email field
     */
    public const EMAIL = 'email';

    /**
     * Meeting field (for storing associated meeting IDs)
     */
    public const MEETING = 'meeting';

    /**
     * Group notes field
     */
    public const GROUP_NOTES = 'group_notes';

    /**
     * Website field
     */
    public const WEBSITE = 'website';

    /**
     * Phone field
     */
    public const PHONE = 'phone';

    /**
     * Venmo handle field
     */
    public const VENMO = 'venmo';

    /**
     * PayPal handle field
     */
    public const PAYPAL = 'paypal';

    /**
     * Square handle field
     */
    public const SQUARE = 'square';

    /**
     * District ID field
     */
    public const DISTRICT_ID = 'district_id';

    /**
     * Last contact date field
     */
    public const LAST_CONTACT = 'last_contact';

    /**
     * Contact field prefix (contact_1_name, contact_1_email, etc.)
     */
    public const CONTACT_PREFIX = 'contact_';

    /**
     * Maximum number of contacts supported
     */
    public const MAX_CONTACTS = 3;

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
