<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

use Unity\Groups\Interfaces\GroupView;

/**
 * Concrete TSML Group View class
 */
class TsmlGroupView implements GroupView
{
    private int $id;
    private string $title;
    private string $email;
    private string $groupEmail;
    private bool $groupEmailActive;
    private array $meetings;
    private string $link;
    private array $contacts;
    private array $members;
    /**
     * TsmlGroupView constructor
     * 
     * @param int    $id       Post ID
     * @param string $title    Group title
     * @param string $email    Email address
     * @param string $groupEmail Group email address
     * @param bool   $groupEmailActive Whether the group email is active
     * @param array  $meetings Array of Meeting objects
     * @param string $link     Link URL
     * @param array  $contacts Array of Contact objects
     * @param array  $members  Array of Member objects
     */
    public function __construct(
        int $id = 0,
        string $title = '',
        string $email = '',
        string $groupEmail = '',
        bool $groupEmailActive = false,
        array $meetings = [],
        string $link = '',
        array $contacts = [],
        array $members = [],
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->email = $email;
        $this->groupEmail = $groupEmail;
        $this->groupEmailActive = $groupEmailActive;
        $this->meetings = $meetings;
        $this->link = $link;
        $this->contacts = $contacts;
        $this->members = $members;
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
    public function getGroupEmail(): string
    {
        return $this->groupEmail;
    }

    /**
     * {@inheritdoc}
     */
    public function isGroupEmailActive(): bool
    {
        return $this->groupEmailActive;
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
    public function getMembers(): array
    {
        return $this->members;
    }
}
