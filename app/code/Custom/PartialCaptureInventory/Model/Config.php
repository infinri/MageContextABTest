<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'custom_partial_capture/general/enabled';
    private const XML_PATH_MAX_RETRIES = 'custom_partial_capture/general/max_retries';
    private const XML_PATH_ELIGIBLE_ORDER_STATES = 'custom_partial_capture/general/eligible_order_states';

    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_ELIGIBLE_STATES = ['processing'];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getMaxRetries(): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_MAX_RETRIES, ScopeInterface::SCOPE_STORE);
        return $value !== null ? (int) $value : self::DEFAULT_MAX_RETRIES;
    }

    public function getEligibleOrderStates(): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ELIGIBLE_ORDER_STATES,
            ScopeInterface::SCOPE_STORE
        );
        return $value ? explode(',', $value) : self::DEFAULT_ELIGIBLE_STATES;
    }
}
