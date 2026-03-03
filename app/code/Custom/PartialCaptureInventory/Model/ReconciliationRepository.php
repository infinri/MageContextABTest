<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterfaceFactory;
use Custom\PartialCaptureInventory\Api\ReconciliationRepositoryInterface;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ReconciliationRepository implements ReconciliationRepositoryInterface
{
    public function __construct(
        private readonly ReconciliationResource $resource,
        private readonly ReconciliationRecordFactory $recordFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly ReconciliationSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function getById(int $entityId): ReconciliationRecordInterface
    {
        /** @var ReconciliationRecord $record */
        $record = $this->recordFactory->create();
        $this->resource->load($record, $entityId);

        if (!$record->getEntityId()) {
            throw new NoSuchEntityException(
                __('Reconciliation record with id "%1" does not exist.', $entityId)
            );
        }

        return $record;
    }

    public function save(ReconciliationRecordInterface $record): ReconciliationRecordInterface
    {
        try {
            /** @var ReconciliationRecord $record */
            $this->resource->save($record);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(
                __('Could not save reconciliation record: %1', $e->getMessage()),
                $e
            );
        }

        return $record;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): ReconciliationSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var ReconciliationSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function getByOrderId(int $orderId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(ReconciliationRecordInterface::ORDER_ID, $orderId);

        return $collection->getItems();
    }
}
