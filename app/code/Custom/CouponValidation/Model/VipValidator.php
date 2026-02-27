<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Model;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Magento\Quote\Api\Data\CartInterface;

class VipValidator implements VipValidatorInterface
{
    /**
     * @param string $couponCode
     * @param CartInterface $quote
     * @return bool
     */
    public function validate(string $couponCode, CartInterface $quote): bool
    {
        return str_starts_with($couponCode, 'VIP-');
    }
}
