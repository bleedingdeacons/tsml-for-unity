<?php

declare(strict_types=1);

namespace TsmlForUnity\Contacts;

use Unity\Contact\Interfaces\ContactFactoryInterface;
use Unity\Contact\Interfaces\ContactInterface;

/**
 * Class TsmlContactFactory
 *
 * Factory for creating TsmlContact objects.
 */
class TsmlContactFactory implements ContactFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(array $source): ContactInterface
    {
        return new TsmlContact(
            $source['name'] ?? '',
            $source['email'] ?? '',
            $source['phone'] ?? ''
        );
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $name = '', string $email = '', string $phone = ''): ContactInterface
    {
        return new TsmlContact($name, $email, $phone);
    }
}
