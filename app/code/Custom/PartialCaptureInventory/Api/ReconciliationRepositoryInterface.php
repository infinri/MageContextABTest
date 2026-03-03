<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Api;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface ReconciliationRepositoryInterface
{
    /**
     * @param int $entityId
     * @return ReconciliationRecordInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): ReconciliationRecordInterface;

    /**
     * @param ReconciliationRecordInterface $record
     * @return ReconciliationRecordInterface
     * @throws CouldNotSaveException
     */
    public function save(ReconciliationRecordInterface $record): ReconciliationRecordInterface;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return ReconciliationSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ReconciliationSearchResultsInterface;

    /**
     * @param int $orderId
     * @return ReconciliationRecordInterface[]
     */
    public function getByOrderId(int $orderId): array;
}
