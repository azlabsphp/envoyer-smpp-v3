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

use Drewlabs\Envoyer\Contracts\NotificationResult;

class Message implements NotificationResult
{
    /**
     * @var \Throwable
     */
    private $error;

    /**
     * @var string|\DateTimeInterface
     */
    private $createdAt;

    /**
     * @var string|int
     */
    private $id;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * Create class instance.
     *
     * @param int|string $messageId
     */
    public function __construct($messageId = null, int $statusCode = 200)
    {
        $this->id = $messageId;
        $this->createdAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s', time()));
        $this->statusCode = $statusCode ?? 200;
    }

    /**
     * Create new error result instance.
     *
     * @return static
     */
    public static function exception(\Throwable $exception)
    {
        $instance = new static(null, (int) $exception->getCode());
        $instance->error = $exception;

        return $instance;
    }

    public function date()
    {
        return $this->createdAt;
    }

    public function id()
    {
        return $this->id;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function isOk()
    {
        return $this->statusCode >= 200 && $this->statusCode <= 204;
    }

    /**
     * Checks if the result has error property set.
     *
     * @return bool
     */
    public function hasError()
    {
        return null !== $this->error;
    }

    /**
     * return the error (exception class) if it's set.
     *
     * @return \Throwable|null
     */
    public function getError()
    {
        return $this->error;
    }
}
