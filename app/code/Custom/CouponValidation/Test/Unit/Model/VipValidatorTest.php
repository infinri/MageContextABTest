<?php

declare(strict_types=1);

namespace Custom\CouponValidation\Test\Unit\Model;

use Custom\CouponValidation\Model\VipValidator;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

class VipValidatorTest extends TestCase
{
    /** @var VipValidator */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new VipValidator();
    }

    public function testValidVipCodePasses(): void
    {
        $quote = $this->createMock(CartInterface::class);

        $this->assertTrue($this->validator->validate('VIP-TEST20', $quote));
    }

    public function testNonVipCodeFails(): void
    {
        $quote = $this->createMock(CartInterface::class);

        $this->assertFalse($this->validator->validate('TEST10', $quote));
    }

    public function testEmptyCodeFails(): void
    {
        $quote = $this->createMock(CartInterface::class);

        $this->assertFalse($this->validator->validate('', $quote));
    }

    public function testLowercaseVipPrefixFails(): void
    {
        $quote = $this->createMock(CartInterface::class);

        $this->assertFalse($this->validator->validate('vip-test', $quote));
    }
}
