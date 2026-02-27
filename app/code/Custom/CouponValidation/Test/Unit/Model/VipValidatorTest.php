<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Test\Unit\Model;

use Custom\CouponValidation\Model\VipValidator;
use Magento\Quote\Api\Data\CartInterface;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VipValidatorTest extends TestCase
{
    private VipValidator $validator;
    private CouponFactory|MockObject $couponFactory;
    private RuleFactory|MockObject $ruleFactory;
    private LoggerInterface|MockObject $logger;
    private CartInterface|MockObject $quote;

    protected function setUp(): void
    {
        $this->couponFactory = $this->createMock(CouponFactory::class);
        $this->ruleFactory = $this->createMock(RuleFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->quote = $this->createMock(CartInterface::class);

        $this->quote->method('getId')->willReturn(42);
        $this->quote->method('getCustomerGroupId')->willReturn(1);
        $this->quote->method('getSubtotal')->willReturn(200.00);
        $this->quote->method('getItemsCount')->willReturn(2);

        $this->validator = new VipValidator(
            $this->couponFactory,
            $this->ruleFactory,
            $this->logger
        );
    }

    public function testValidCouponWithActiveRuleReturnsTrue(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(1);
        $coupon->method('getRuleId')->willReturn(10);
        $this->couponFactory->method('create')->willReturn($coupon);

        $rule = $this->createMock(Rule::class);
        $rule->method('getId')->willReturn(10);
        $rule->method('getIsActive')->willReturn(true);
        $this->ruleFactory->method('create')->willReturn($rule);

        $this->assertTrue($this->validator->validate('VIP-TEST20', $this->quote));
    }

    public function testCouponNotFoundReturnsFalse(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(null);
        $this->couponFactory->method('create')->willReturn($coupon);

        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->validator->validate('VIP-INVALID', $this->quote));
    }

    public function testInactiveRuleReturnsFalse(): void
    {
        $coupon = $this->createMock(Coupon::class);
        $coupon->method('getId')->willReturn(1);
        $coupon->method('getRuleId')->willReturn(10);
        $this->couponFactory->method('create')->willReturn($coupon);

        $rule = $this->createMock(Rule::class);
        $rule->method('getId')->willReturn(10);
        $rule->method('getIsActive')->willReturn(false);
        $this->ruleFactory->method('create')->willReturn($rule);

        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->validator->validate('VIP-TEST20', $this->quote));
    }

    public function testExceptionReturnsFalseAndLogs(): void
    {
        $this->couponFactory->method('create')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('DB error', $this->arrayHasKey('exception'));

        $this->assertFalse($this->validator->validate('VIP-TEST20', $this->quote));
    }
}
