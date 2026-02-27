<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Model;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\RuleFactory;
use Psr\Log\LoggerInterface;

class VipValidator implements VipValidatorInterface
{
    private CouponFactory $couponFactory;
    private RuleFactory $ruleFactory;
    private LoggerInterface $logger;

    public function __construct(
        CouponFactory $couponFactory,
        RuleFactory $ruleFactory,
        LoggerInterface $logger
    ) {
        $this->couponFactory = $couponFactory;
        $this->ruleFactory = $ruleFactory;
        $this->logger = $logger;
    }

    public function validate(string $couponCode, CartInterface $quote): bool
    {
        try {
            /** @var \Magento\SalesRule\Model\Coupon $coupon */
            $coupon = $this->couponFactory->create();
            $coupon->loadByCode($couponCode);

            if (!$coupon->getId()) {
                $this->logFailure($couponCode, $quote, 'Coupon code not found');
                return false;
            }

            /** @var \Magento\SalesRule\Model\Rule $rule */
            $rule = $this->ruleFactory->create();
            $rule->load($coupon->getRuleId());

            if (!$rule->getId() || !$rule->getIsActive()) {
                $this->logFailure($couponCode, $quote, 'Associated rule is inactive or missing');
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    private function logFailure(string $couponCode, CartInterface $quote, string $reason): void
    {
        $this->logger->warning(
            'VIP validation failed',
            [
                'quote_id' => $quote->getId(),
                'customer_group_id' => $quote->getCustomerGroupId(),
                'subtotal' => $quote->getSubtotal(),
                'item_count' => $quote->getItemsCount(),
                'coupon_code' => $couponCode,
                'validation_outcome' => 'vip_validation_failed',
                'reason' => $reason,
            ]
        );
    }
}
