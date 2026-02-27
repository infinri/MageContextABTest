<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Plugin;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Psr\Log\LoggerInterface;

class CouponApplyValidationPlugin
{
    private const CUSTOMER_GROUP_BLOCKED = 3;
    private const HIGH_VALUE_ITEM_THRESHOLD = 3;
    private const HIGH_VALUE_SUBTOTAL_THRESHOLD = 500.00;
    private const VIP_PREFIX = 'VIP-';

    private CartRepositoryInterface $cartRepository;
    private VipValidatorInterface $vipValidator;
    private MessageManagerInterface $messageManager;
    private LoggerInterface $logger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        VipValidatorInterface $vipValidator,
        MessageManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->vipValidator = $vipValidator;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @param CouponManagementInterface $subject
     * @param int $cartId
     * @param string $couponCode
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSet(CouponManagementInterface $subject, $cartId, $couponCode): array
    {
        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $this->cartRepository->getActive($cartId);

        $this->validateCustomerGroup($quote);
        $this->validateVipCoupon($couponCode, $quote);
        $this->checkHighValueCart($couponCode, $quote);

        return [$cartId, $couponCode];
    }

    /**
     * @throws CouldNotSaveException
     */
    private function validateCustomerGroup(\Magento\Quote\Api\Data\CartInterface $quote): void
    {
        if ((int)$quote->getCustomerGroupId() === self::CUSTOMER_GROUP_BLOCKED) {
            throw new CouldNotSaveException(
                __('This coupon is not valid for your customer group.')
            );
        }
    }

    /**
     * @throws CouldNotSaveException
     */
    private function validateVipCoupon(string $couponCode, \Magento\Quote\Api\Data\CartInterface $quote): void
    {
        if (stripos($couponCode, self::VIP_PREFIX) !== 0) {
            return;
        }

        if (!$this->vipValidator->validate($couponCode, $quote)) {
            throw new CouldNotSaveException(
                __('The provided VIP coupon is not valid.')
            );
        }
    }

    private function checkHighValueCart(string $couponCode, \Magento\Quote\Api\Data\CartInterface $quote): void
    {
        $itemCount = (int)$quote->getItemsCount();
        $subtotal = (float)$quote->getSubtotal();

        if ($itemCount > self::HIGH_VALUE_ITEM_THRESHOLD && $subtotal > self::HIGH_VALUE_SUBTOTAL_THRESHOLD) {
            $this->messageManager->addNoticeMessage(
                __('High value cart – coupon restrictions may apply.')
            );

            $this->logger->info(
                'High value cart warning',
                [
                    'quote_id' => $quote->getId(),
                    'customer_group_id' => $quote->getCustomerGroupId(),
                    'subtotal' => $subtotal,
                    'item_count' => $itemCount,
                    'coupon_code' => $couponCode,
                    'validation_outcome' => 'high_value_cart_warning',
                ]
            );
        }
    }
}
