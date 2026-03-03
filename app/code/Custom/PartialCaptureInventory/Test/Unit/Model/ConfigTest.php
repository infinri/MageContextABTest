<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Unit\Model;

use Custom\PartialCaptureInventory\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Config model.
 * Covers: ENF-SYS-004 (policy vs mechanism), FW-M2-RT-003 (system.xml + ScopeConfigInterface)
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface|MockObject $scopeConfig;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('custom_partial_capture/general/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('custom_partial_capture/general/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    public function testGetMaxRetriesReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('custom_partial_capture/general/max_retries', ScopeInterface::SCOPE_STORE)
            ->willReturn('5');

        $this->assertEquals(5, $this->config->getMaxRetries());
    }

    public function testGetMaxRetriesReturnsDefaultWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('custom_partial_capture/general/max_retries', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertEquals(3, $this->config->getMaxRetries());
    }

    public function testGetEligibleOrderStatesReturnsConfiguredStates(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('custom_partial_capture/general/eligible_order_states', ScopeInterface::SCOPE_STORE)
            ->willReturn('processing,complete');

        $this->assertEquals(['processing', 'complete'], $this->config->getEligibleOrderStates());
    }

    public function testGetEligibleOrderStatesReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('custom_partial_capture/general/eligible_order_states', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertEquals(['processing'], $this->config->getEligibleOrderStates());
    }
}
