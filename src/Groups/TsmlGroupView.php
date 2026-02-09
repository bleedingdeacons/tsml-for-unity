<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

use Unity\Groups\Interfaces\GroupViewInterface;

/**
 * Concrete TSML Group View class
 */
class TsmlGroupView implements GroupViewInterface
{
    private int $id;
    private string $title;
    private string $email;
    private array $meetings;
    private string $link;

    /**
     * TsmlGroupView constructor
     * 
     * @param int    $id       Post ID
     * @param string $title    Group title
     * @param string $email    Email address
     * @param array  $meetings Array of Meeting objects
     * @param string $link     Link URL
     */
    public function __construct(
        int $id = 0,
        string $title = '',
        string $email = '',
        array $meetings = [],
        string $link = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->email = $email;
        $this->meetings = $meetings;
        $this->link = $link;
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
}
