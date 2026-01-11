<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Groups\Interfaces\GroupInterface;

/**
 * TSML Group entity class
 * 
 * Implements Unity's GroupInterface and adds additional fields specific to 
 * the 12 Step Meeting List plugin's tsml_group custom post type.
 */
class TsmlGroup implements GroupInterface
{
    private int $id;
    private string $title;
    private string $email;
    private array $meetingIds;
    private string $link;
    private string $groupNotes;
    private string $website;
    private string $phone;
    private string $venmo;
    private string $paypal;
    private string $square;
    private ?int $districtId;
    private ?string $lastContact;
    private array $contacts;

    /**
     * TsmlGroup constructor
     * 
     * @param int         $id          WordPress post ID
     * @param string      $title       Group title/name
     * @param string      $email       Group email address
     * @param array       $meetingIds  Associated meeting post IDs
     * @param string      $link        Permalink URL
     * @param string      $groupNotes  Group notes/description
     * @param string      $website     Group website URL
     * @param string      $phone       Group phone number
     * @param string      $venmo       Venmo handle for contributions
     * @param string      $paypal      PayPal username for contributions
     * @param string      $square      Square Cash App cashtag for contributions
     * @param int|null    $districtId  District ID
     * @param string|null $lastContact Last contact timestamp
     * @param array       $contacts    Array of contact information
     */
    public function __construct(
        int $id = 0,
        string $title = '',
        string $email = '',
        array $meetingIds = [],
        string $link = '',
        string $groupNotes = '',
        string $website = '',
        string $phone = '',
        string $venmo = '',
        string $paypal = '',
        string $square = '',
        ?int $districtId = null,
        ?string $lastContact = null,
        array $contacts = []
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->email = $email;
        $this->meetingIds = $meetingIds;
        $this->link = $link;
        $this->groupNotes = $groupNotes;
        $this->website = $website;
        $this->phone = $phone;
        $this->venmo = $venmo;
        $this->paypal = $paypal;
        $this->square = $square;
        $this->districtId = $districtId;
        $this->lastContact = $lastContact;
        $this->contacts = $contacts;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeetingIds(): array
    {
        return $this->meetingIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return $this->id > 0
            && !empty($this->title);
    }

    /**
     * Get the group notes/description
     * 
     * @return string Group notes
     */
    public function getGroupNotes(): string
    {
        return $this->groupNotes;
    }

    /**
     * Get the group website URL
     * 
     * @return string Website URL
     */
    public function getWebsite(): string
    {
        return $this->website;
    }

    /**
     * Get the group phone number
     * 
     * @return string Phone number
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Get the Venmo handle for 7th Tradition contributions
     * 
     * @return string Venmo handle (e.g., @AAGroupName)
     */
    public function getVenmo(): string
    {
        return $this->venmo;
    }

    /**
     * Get the PayPal username for 7th Tradition contributions
     * 
     * @return string PayPal username
     */
    public function getPaypal(): string
    {
        return $this->paypal;
    }

    /**
     * Get the Square Cash App cashtag for 7th Tradition contributions
     * 
     * @return string Square cashtag (e.g., $AAGroupName)
     */
    public function getSquare(): string
    {
        return $this->square;
    }

    /**
     * Get the district ID
     * 
     * @return int|null District ID or null if not set
     */
    public function getDistrictId(): ?int
    {
        return $this->districtId;
    }

    /**
     * Get the last contact timestamp
     * 
     * @return string|null Last contact timestamp or null if not set
     */
    public function getLastContact(): ?string
    {
        return $this->lastContact;
    }

    /**
     * Get the contacts array
     * 
     * @return array Array of contact information with keys like:
     *               - name: Contact name
     *               - email: Contact email
     *               - phone: Contact phone
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * Check if the group has any digital contribution options
     * 
     * @return bool True if any contribution option is available
     */
    public function hasContributionOptions(): bool
    {
        return !empty($this->venmo) 
            || !empty($this->paypal) 
            || !empty($this->square);
    }
}
