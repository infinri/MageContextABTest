<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Test\Unit\Plugin;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Custom\CouponValidation\Plugin\CouponApplyValidationPlugin;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CouponApplyValidationPluginTest extends TestCase
{
    private CouponApplyValidationPlugin $plugin;
    private CartRepositoryInterface|MockObject $cartRepository;
    private VipValidatorInterface|MockObject $vipValidator;
    private MessageManagerInterface|MockObject $messageManager;
    private LoggerInterface|MockObject $logger;
    private CouponManagementInterface|MockObject $subject;
    private CartInterface|MockObject $quote;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->vipValidator = $this->createMock(VipValidatorInterface::class);
        $this->messageManager = $this->createMock(MessageManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = $this->createMock(CouponManagementInterface::class);
        $this->quote = $this->createMock(CartInterface::class);

        $this->cartRepository->method('getActive')->willReturn($this->quote);

        $this->plugin = new CouponApplyValidationPlugin(
            $this->cartRepository,
            $this->vipValidator,
            $this->messageManager,
            $this->logger
        );
    }

    public function testCustomerGroup3IsRejected(): void
    {
        $this->quote->method('getCustomerGroupId')->willReturn(3);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('This coupon is not valid for your customer group.');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testCustomerGroup1IsAllowed(): void
    {
        $this->configureQuote(1, 2, 100.00);

        $result = $this->plugin->beforeSet($this->subject, 1, 'TEST10');

        $this->assertSame([1, 'TEST10'], $result);
    }

    public function testCustomerGroup0IsAllowed(): void
    {
        $this->configureQuote(0, 1, 50.00);

        $result = $this->plugin->beforeSet($this->subject, 1, 'TEST10');

        $this->assertSame([1, 'TEST10'], $result);
    }

    public function testVipCouponValidationFailThrowsException(): void
    {
        $this->configureQuote(1, 1, 100.00);
        $this->vipValidator->method('validate')->willReturn(false);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('The provided VIP coupon is not valid.');

        $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');
    }

    public function testVipCouponValidationPassAllowsCoupon(): void
    {
        $this->configureQuote(1, 2, 100.00);
        $this->vipValidator->method('validate')->willReturn(true);

        $result = $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');

        $this->assertSame([1, 'VIP-TEST20'], $result);
    }

    public function testNonVipCouponDoesNotInvokeVipValidator(): void
    {
        $this->configureQuote(1, 2, 100.00);
        $this->vipValidator->expects($this->never())->method('validate');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testHighValueCartTriggersWarningAndLog(): void
    {
        $this->configureQuote(1, 4, 600.00);

        $this->messageManager->expects($this->once())
            ->method('addNoticeMessage')
            ->with(__('High value cart – coupon restrictions may apply.'));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'High value cart warning',
                $this->callback(function (array $context): bool {
                    return $context['validation_outcome'] === 'high_value_cart_warning'
                        && $context['item_count'] === 4
                        && $context['subtotal'] === 600.00;
                })
            );

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testExactly3ItemsDoesNotTriggerWarning(): void
    {
        $this->configureQuote(1, 3, 600.00);

        $this->messageManager->expects($this->never())->method('addNoticeMessage');
        $this->logger->expects($this->never())->method('info');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testExactly500SubtotalDoesNotTriggerWarning(): void
    {
        $this->configureQuote(1, 4, 500.00);

        $this->messageManager->expects($this->never())->method('addNoticeMessage');
        $this->logger->expects($this->never())->method('info');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testGroup3WithVipCouponRejectedAtGroupCheck(): void
    {
        $this->quote->method('getCustomerGroupId')->willReturn(3);
        $this->vipValidator->expects($this->never())->method('validate');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('This coupon is not valid for your customer group.');

        $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');
    }

    public function testVipCouponOnHighValueCartBothTrigger(): void
    {
        $this->configureQuote(1, 5, 750.00);
        $this->vipValidator->method('validate')->willReturn(true);

        $this->messageManager->expects($this->once())->method('addNoticeMessage');
        $this->logger->expects($this->once())->method('info');

        $result = $this->plugin->beforeSet($this->subject, 1, 'VIP-SPECIAL');

        $this->assertSame([1, 'VIP-SPECIAL'], $result);
    }

    private function configureQuote(int $groupId, int $itemCount, float $subtotal): void
    {
        $this->quote->method('getCustomerGroupId')->willReturn($groupId);
        $this->quote->method('getItemsCount')->willReturn($itemCount);
        $this->quote->method('getSubtotal')->willReturn($subtotal);
    }
}
