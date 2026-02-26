<?php

declare(strict_types=1);

namespace Custom\TestData\Setup\Patch\Data;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;

class CreateTestVipCoupon implements DataPatchInterface
{
    private const COUPON_CODE = 'VIP-TEST20';
    private const DISCOUNT_AMOUNT = 20.00;

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var RuleInterfaceFactory
     */
    private RuleInterfaceFactory $ruleFactory;

    /**
     * @var RuleRepositoryInterface
     */
    private RuleRepositoryInterface $ruleRepository;

    /**
     * @var CouponInterfaceFactory
     */
    private CouponInterfaceFactory $couponFactory;

    /**
     * @var CouponRepositoryInterface
     */
    private CouponRepositoryInterface $couponRepository;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param RuleInterfaceFactory $ruleFactory
     * @param RuleRepositoryInterface $ruleRepository
     * @param CouponInterfaceFactory $couponFactory
     * @param CouponRepositoryInterface $couponRepository
     * @param State $appState
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        RuleInterfaceFactory $ruleFactory,
        RuleRepositoryInterface $ruleRepository,
        CouponInterfaceFactory $couponFactory,
        CouponRepositoryInterface $couponRepository,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
        $this->couponFactory = $couponFactory;
        $this->couponRepository = $couponRepository;
        $this->appState = $appState;
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set — safe to ignore
        }

        $this->createVipCouponRule();

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * Create a VIP cart price rule with a 20% discount coupon.
     *
     * @return void
     */
    private function createVipCouponRule(): void
    {
        /** @var Rule $rule */
        $rule = $this->ruleFactory->create();
        $rule->setName('VIP 20% Off Coupon')
            ->setDescription('VIP 20% discount for testing — requires VIP validation')
            ->setIsActive(1)
            ->setCustomerGroupIds([0, 1, 2, 3]) // NOT LOGGED IN, General, Wholesale, Retailer
            ->setWebsiteIds([1])
            ->setCouponType(Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction(Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(self::DISCOUNT_AMOUNT)
            ->setDiscountQty(0)
            ->setDiscountStep(0)
            ->setStopRulesProcessing(0)
            ->setIsAdvanced(1)
            ->setSortOrder(0)
            ->setApplyToShipping(0)
            ->setTimesUsed(0)
            ->setUsesPerCustomer(0)
            ->setUsesPerCoupon(0);

        $savedRule = $this->ruleRepository->save($rule);

        /** @var \Magento\SalesRule\Api\Data\CouponInterface $coupon */
        $coupon = $this->couponFactory->create();
        $coupon->setCode(self::COUPON_CODE)
            ->setRuleId((int) $savedRule->getRuleId())
            ->setIsPrimary(true)
            ->setType(0);

        $this->couponRepository->save($coupon);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [
            CreateTestCoupon::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
