<?php

declare(strict_types=1);

namespace Custom\TestData\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateTestProducts implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var ProductInterfaceFactory
     */
    private ProductInterfaceFactory $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CategoryLinkManagementInterface
     */
    private CategoryLinkManagementInterface $categoryLinkManagement;

    /**
     * @var CategoryInterfaceFactory
     */
    private CategoryInterfaceFactory $categoryFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ProductInterfaceFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param StoreManagerInterface $storeManager
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param CategoryInterfaceFactory $categoryFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param State $appState
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ProductInterfaceFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        CategoryLinkManagementInterface $categoryLinkManagement,
        CategoryInterfaceFactory $categoryFactory,
        CategoryRepositoryInterface $categoryRepository,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->storeManager = $storeManager;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->appState = $appState;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set — safe to ignore
        }

        $this->createProducts();

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * Create the test products inside an emulated admin area.
     *
     * @return void
     * @throws CouldNotSaveException
     */
    public function createProducts(): void
    {
        $parentCategoryId = 2; // Default Category

        // Create "Test Products" category under Default Category
        $category = $this->categoryFactory->create();
        $category->setName('Test Products');
        $category->setParentId($parentCategoryId);
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setAttributeSetId($category->getDefaultAttributeSetId());
        $savedCategory = $this->categoryRepository->save($category);
        $testCategoryId = (int) $savedCategory->getId();

        $products = [
            ['sku' => 'test-1', 'name' => 'Test Product 1', 'price' => 150.00],
            ['sku' => 'test-2', 'name' => 'Test Product 2', 'price' => 150.00],
            ['sku' => 'test-3', 'name' => 'Test Product 3', 'price' => 150.00],
            ['sku' => 'test-4', 'name' => 'Test Product 4', 'price' => 150.00],
        ];

        $websiteId = (int) $this->storeManager->getWebsite()->getId();

        foreach ($products as $data) {
            $product = $this->productFactory->create();
            $product->setTypeId(Type::TYPE_SIMPLE);
            $product->setAttributeSetId($product->getDefaultAttributeSetId());
            $product->setSku($data['sku']);
            $product->setName($data['name']);
            $product->setPrice($data['price']);
            $product->setStatus(Status::STATUS_ENABLED);
            $product->setVisibility(Visibility::VISIBILITY_BOTH);
            $product->setWebsiteIds([$websiteId]);
            $product->setStockData([
                'qty'         => 99,
                'is_in_stock' => 1,
            ]);

            $saved = $this->productRepository->save($product);

            $this->categoryLinkManagement->assignProductToCategories(
                $saved->getSku(),
                [$testCategoryId]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [
            CreateTestCustomers::class,
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
