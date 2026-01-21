<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Groups\Interfaces\GroupInterface;
use Unity\Contact\Interfaces\ContactInterface;
use Unity\Meetings\Interfaces\MeetingInterface;

/**
 * Class Group
 *
 * Implementation of GroupInterface.
 */
class TsmlGroup implements GroupInterface
{
    private int $id;
    private string $title;
    private string $email;
    private array $meetings;
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
     * Constructor.
     *
     * @param int $id Group ID
     * @param string $title Group title
     * @param string $email Group email address
     * @param MeetingInterface[] $meetings Array of Meeting objects
     * @param string $link Group link/URL
     * @param string $groupNotes Group notes/description
     * @param string $website Group website URL
     * @param string $phone Group phone number
     * @param string $venmo Venmo handle for contributions
     * @param string $paypal PayPal username for contributions
     * @param string $square Square Cash App cashtag for contributions
     * @param int|null $districtId District ID
     * @param string|null $lastContact Last contact timestamp
     * @param ContactInterface[] $contacts Array of Contact objects
     */
    public function __construct(
        int $id,
        string $title = '',
        string $email = '',
        array $meetings = [],
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
        $this->meetings = $meetings;
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
    public function getMeetings(): array
    {
        return $this->meetings;
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
        // A group is considered valid if it has an ID and a title
        return $this->id > 0 && !empty($this->title);
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupNotes(): string
    {
        return $this->groupNotes;
    }

    /**
     * {@inheritdoc}
     */
    public function getWebsite(): string
    {
        return $this->website;
    }

    /**
     * {@inheritdoc}
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * {@inheritdoc}
     */
    public function getVenmo(): string
    {
        return $this->venmo;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaypal(): string
    {
        return $this->paypal;
    }

    /**
     * {@inheritdoc}
     */
    public function getSquare(): string
    {
        return $this->square;
    }

    /**
     * {@inheritdoc}
     */
    public function getDistrictId(): ?int
    {
        return $this->districtId;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastContact(): ?string
    {
        return $this->lastContact;
    }

    /**
     * {@inheritdoc}
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * {@inheritdoc}
     */
    public function hasContributionOptions(): bool
    {
        return !empty($this->venmo)
            || !empty($this->paypal)
            || !empty($this->square);
    }
}