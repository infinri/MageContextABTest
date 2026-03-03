<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface ReconciliationSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return ReconciliationRecordInterface[]
     */
    public function getItems(): array;

    /**
     * @param ReconciliationRecordInterface[] $items
     * @return self
     */
    public function setItems(array $items): self;
}
