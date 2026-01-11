<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Field Constants for TSML Group post type
 * 
 * These constants map to the post_meta keys used by the 12 Step Meeting List plugin
 * for the tsml_group custom post type.
 */
final class TsmlGroupFields
{
    /**
     * The WordPress post type for TSML groups
     */
    public const GROUP_POST_TYPE = 'tsml_group';

    /**
     * Group notes/description (post_meta key)
     */
    public const GROUP_NOTES = 'group_notes';

    /**
     * Group website URL
     */
    public const WEBSITE = 'website';

    /**
     * Group email address
     */
    public const EMAIL = 'email';

    /**
     * Group phone number
     */
    public const PHONE = 'phone';

    /**
     * Venmo handle for 7th Tradition contributions
     */
    public const VENMO = 'venmo';

    /**
     * PayPal username for 7th Tradition contributions
     */
    public const PAYPAL = 'paypal';

    /**
     * Square Cash App cashtag for 7th Tradition contributions
     */
    public const SQUARE = 'square';

    /**
     * District ID for the group
     */
    public const DISTRICT_ID = 'district_id';

    /**
     * Last updated timestamp
     */
    public const LAST_CONTACT = 'last_contact';

    /**
     * Contact fields prefix (followed by number 1-3)
     */
    public const CONTACT_PREFIX = 'contact_';

    /**
     * Maximum number of contacts supported
     */
    public const MAX_CONTACTS = 3;

    private function __construct()
    {
    }
}
