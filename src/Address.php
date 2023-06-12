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

/**
 * Primitive class for encapsulating smpp addresses.
 *
 * @author hd@onlinecity.dk
 */
class Address
{
    public $typeOfNumber;
    public $numberingPlanIndicator; // numbering-plan-indicator
    public $value;

    /**
     * Construct a new object of class Address.
     *
     * @param string $value
     * @param int    $typeOfNumber
     * @param int    $numberingPlanIndicator
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        $value,
        $typeOfNumber = SMPP::TON_UNKNOWN,
        $numberingPlanIndicator = SMPP::NPI_UNKNOWN
    ) {
        // Address-Value field may contain 10 octets (12-length-type), see 3GPP TS 23.040 v 9.3.0 - section 9.1.2.5 page 46.
        if (SMPP::TON_ALPHANUMERIC === $typeOfNumber && \strlen($value) > 11) {
            throw new \InvalidArgumentException('Alphanumeric address may only contain 11 chars');
        }
        if (SMPP::TON_INTERNATIONAL === $typeOfNumber && SMPP::NPI_E164 === $numberingPlanIndicator && \strlen($value) > 15) {
            throw new \InvalidArgumentException('E164 address may only contain 15 digits');
        }

        $this->value = (string) $value;
        $this->typeOfNumber = $typeOfNumber;
        $this->numberingPlanIndicator = $numberingPlanIndicator;
    }
}
