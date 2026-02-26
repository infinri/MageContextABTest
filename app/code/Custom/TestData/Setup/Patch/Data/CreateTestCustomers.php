<?php

declare(strict_types=1);

namespace Custom\TestData\Setup\Patch\Data;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateTestCustomers implements DataPatchInterface
{
    private const PASSWORD = 'Test1234!';

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var CustomerInterfaceFactory
     */
    private CustomerInterfaceFactory $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CustomerRegistry
     */
    private CustomerRegistry $customerRegistry;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerInterfaceFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param CustomerRegistry $customerRegistry
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerInterfaceFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        CustomerRegistry $customerRegistry
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->customerRegistry = $customerRegistry;
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $websiteId = (int) $this->storeManager->getWebsite()->getId();
        $storeId = (int) $this->storeManager->getStore()->getId();

        $customers = [
            [
                'email'     => 'customer.a@example.com',
                'firstname' => 'Test',
                'lastname'  => 'Normal',
                'group_id'  => 1, // General — used to test "normal" case
            ],
            [
                'email'     => 'customer.b@example.com',
                'firstname' => 'Test',
                'lastname'  => 'Rejected',
                'group_id'  => 3, // Retailer — used to test rejection logic
            ],
        ];

        foreach ($customers as $data) {
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->setStoreId($storeId);
            $customer->setEmail($data['email']);
            $customer->setFirstname($data['firstname']);
            $customer->setLastname($data['lastname']);
            $customer->setGroupId($data['group_id']);

            $saved = $this->customerRepository->save($customer, $this->encryptor->getHash(self::PASSWORD, true));
            $this->customerRegistry->remove($saved->getId());
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
