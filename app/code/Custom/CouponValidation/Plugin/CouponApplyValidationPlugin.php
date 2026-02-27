<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Plugin;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Custom\CouponValidation\Logger\Logger;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;

class CouponApplyValidationPlugin
{
    private const BLOCKED_GROUP_ID = 3;
    private const VIP_PREFIX = 'VIP-';
    private const HIGH_VALUE_ITEM_THRESHOLD = 3;
    private const HIGH_VALUE_SUBTOTAL_THRESHOLD = 500;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var VipValidatorInterface */
    private $vipValidator;

    /** @var Logger */
    private $logger;

    /** @var MessageManagerInterface */
    private $messageManager;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        VipValidatorInterface $vipValidator,
        Logger $logger,
        MessageManagerInterface $messageManager
    ) {
        $this->cartRepository = $cartRepository;
        $this->vipValidator = $vipValidator;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
    }

    /**
     * @param CouponManagementInterface $subject
     * @param int $cartId
     * @param string $couponCode
     * @return array|null
     * @throws CouldNotSaveException
     */
    public function beforeSet(CouponManagementInterface $subject, $cartId, $couponCode)
    {
        try {
            /** @var \Magento\Quote\Api\Data\CartInterface $quote */
            $quote = $this->cartRepository->getActive($cartId);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            throw $e;
        }

        $customerGroupId = (int) $quote->getCustomer()->getGroupId();

        if ($customerGroupId === self::BLOCKED_GROUP_ID) {
            throw new CouldNotSaveException(
                __('This coupon is not valid for your customer group.')
            );
        }

        if (str_starts_with($couponCode, self::VIP_PREFIX)) {
            if (!$this->vipValidator->validate($couponCode, $quote)) {
                $this->logEvent($quote, $couponCode, 'vip_validation_failed');
                throw new CouldNotSaveException(
                    __('The provided coupon code is not valid.')
                );
            }
        }

        $itemCount = $quote->getItemsCount();
        $subtotal = (float) $quote->getSubtotal();

        if ($itemCount > self::HIGH_VALUE_ITEM_THRESHOLD
            && $subtotal > self::HIGH_VALUE_SUBTOTAL_THRESHOLD
        ) {
            $this->messageManager->addWarningMessage(
                __('High value cart – coupon restrictions may apply.')
            );
            $this->logEvent($quote, $couponCode, 'high_value_cart_warning');
        }

        return null;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param string $couponCode
     * @param string $outcome
     * @return void
     */
    private function logEvent(\Magento\Quote\Api\Data\CartInterface $quote, string $couponCode, string $outcome): void
    {
        $this->logger->info('Coupon validation event', [
            'quote_id' => $quote->getId(),
            'customer_group_id' => (int) $quote->getCustomer()->getGroupId(),
            'subtotal' => (float) $quote->getSubtotal(),
            'item_count' => $quote->getItemsCount(),
            'coupon_code' => $couponCode,
            'validation_outcome' => $outcome,
        ]);
    }
}
