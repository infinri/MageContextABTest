<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord;

use Custom\PartialCaptureInventory\Model\ReconciliationRecord;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ReconciliationRecord::class, ReconciliationResource::class);
    }
}
