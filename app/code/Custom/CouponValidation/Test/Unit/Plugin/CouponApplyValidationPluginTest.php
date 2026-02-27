<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Test\Unit\Plugin;

use Custom\CouponValidation\Api\VipValidatorInterface;
use Custom\CouponValidation\Logger\Logger;
use Custom\CouponValidation\Plugin\CouponApplyValidationPlugin;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CouponApplyValidationPluginTest extends TestCase
{
    /** @var CouponApplyValidationPlugin */
    private $plugin;

    /** @var CartRepositoryInterface|MockObject */
    private $cartRepository;

    /** @var VipValidatorInterface|MockObject */
    private $vipValidator;

    /** @var Logger|MockObject */
    private $logger;

    /** @var MessageManagerInterface|MockObject */
    private $messageManager;

    /** @var CouponManagementInterface|MockObject */
    private $subject;

    /** @var CartInterface|MockObject */
    private $quote;

    /** @var CustomerInterface|MockObject */
    private $customer;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->vipValidator = $this->createMock(VipValidatorInterface::class);
        $this->logger = $this->createMock(Logger::class);
        $this->messageManager = $this->createMock(MessageManagerInterface::class);
        $this->subject = $this->createMock(CouponManagementInterface::class);
        $this->quote = $this->createMock(CartInterface::class);
        $this->customer = $this->createMock(CustomerInterface::class);

        $this->quote->method('getCustomer')->willReturn($this->customer);
        $this->cartRepository->method('getActive')->willReturn($this->quote);

        $this->plugin = new CouponApplyValidationPlugin(
            $this->cartRepository,
            $this->vipValidator,
            $this->logger,
            $this->messageManager
        );
    }

    public function testCustomerGroup3IsRejected(): void
    {
        $this->customer->method('getGroupId')->willReturn(3);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('This coupon is not valid for your customer group.');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testCustomerGroup1PassesGroupCheck(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(1);
        $this->quote->method('getSubtotal')->willReturn(100.0);

        $result = $this->plugin->beforeSet($this->subject, 1, 'TEST10');

        $this->assertNull($result);
    }

    public function testVipCouponFailsValidation(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->vipValidator->method('validate')->willReturn(false);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('The provided coupon code is not valid.');

        $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');
    }

    public function testVipCouponPassesValidation(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->vipValidator->method('validate')->willReturn(true);
        $this->quote->method('getItemsCount')->willReturn(1);
        $this->quote->method('getSubtotal')->willReturn(100.0);

        $result = $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');

        $this->assertNull($result);
    }

    public function testNonVipCouponSkipsVipValidation(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(1);
        $this->quote->method('getSubtotal')->willReturn(100.0);

        $this->vipValidator->expects($this->never())->method('validate');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testHighValueCartTriggersWarning(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(5);
        $this->quote->method('getSubtotal')->willReturn(600.0);
        $this->quote->method('getId')->willReturn(42);

        $this->messageManager->expects($this->once())
            ->method('addWarningMessage')
            ->with(__('High value cart – coupon restrictions may apply.'));

        $this->logger->expects($this->once())
            ->method('info');

        $result = $this->plugin->beforeSet($this->subject, 1, 'TEST10');

        $this->assertNull($result);
    }

    public function testLowValueCartNoWarning(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(2);
        $this->quote->method('getSubtotal')->willReturn(100.0);

        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testHighItemsLowSubtotalNoWarning(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(5);
        $this->quote->method('getSubtotal')->willReturn(100.0);

        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testHighSubtotalFewItemsNoWarning(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getItemsCount')->willReturn(2);
        $this->quote->method('getSubtotal')->willReturn(600.0);

        $this->messageManager->expects($this->never())->method('addWarningMessage');

        $this->plugin->beforeSet($this->subject, 1, 'TEST10');
    }

    public function testVipFailureIsLogged(): void
    {
        $this->customer->method('getGroupId')->willReturn(1);
        $this->quote->method('getId')->willReturn(42);
        $this->vipValidator->method('validate')->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Coupon validation event', $this->callback(function ($context) {
                return $context['validation_outcome'] === 'vip_validation_failed'
                    && $context['coupon_code'] === 'VIP-TEST20';
            }));

        $this->expectException(CouldNotSaveException::class);

        $this->plugin->beforeSet($this->subject, 1, 'VIP-TEST20');
    }
}
