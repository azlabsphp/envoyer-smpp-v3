<?php

declare(strict_types=1);

/*
 * This file is part of the drewlabs namespace.
 *
 * (c) Sidoine Azandrew <azandrewdevelopper@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drewlabs\Envoyer\Drivers\Smpp;

use Drewlabs\Envoyer\Contracts\ClientSecretKeyAware;
use Drewlabs\Envoyer\Contracts\ServerConfigInterface;

class ClientSecretKeyServer implements ClientSecretKeyAware, ServerConfigInterface
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string|null
     */
    private $client;

    /**
     * @var string|null
     */
    private $secret;

    /**
     * Creates class instance.
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function __construct(string $host, int $port = 8080, string $client = null, string $secret = null)
    {
        $this->host = $host;
        $this->port = $port ?? 8080;
        $this->client = $client;
        $this->secret = $secret;
    }

    public function __toString(): string
    {
        return null !== $this->port ? sprintf('%s:%d', $this->getHost(), $this->getHostPort()) : sprintf('%s', $this->getHost());
    }

    public function getClientId(): string
    {
        return $this->client ?? '';
    }

    public function getClientSecret(): string
    {
        return $this->secret ?? '';
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getHostPort()
    {
        return $this->port;
    }

    /**
     * immutable `host` setter implementation.
     *
     * @return static
     */
    public function withHost(string $host)
    {
        $self = clone $this;
        $self->host = $host;

        return $self;
    }

    /**
     * immutable `port` setter implementation.
     *
     * @return static
     */
    public function withPort(string $host)
    {
        $self = clone $this;
        $self->host = $host;

        return $self;
    }

    /**
     * immutable credentials setter method.
     *
     * @return static
     */
    public function withAuthCredential(string $user, string $password)
    {
        $self = clone $this;
        $self->client = $user;
        $self->secret = $password;

        return $self;
    }
}
