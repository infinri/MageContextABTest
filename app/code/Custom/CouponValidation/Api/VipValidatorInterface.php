<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Api;

use Magento\Quote\Api\Data\CartInterface;

interface VipValidatorInterface
{
    /**
     * @param string $couponCode
     * @param CartInterface $quote
     * @return bool
     */
    public function validate(string $couponCode, CartInterface $quote): bool;
}
