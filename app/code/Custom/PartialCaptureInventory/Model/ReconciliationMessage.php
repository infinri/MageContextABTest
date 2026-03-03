<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationMessageInterface;

class ReconciliationMessage implements ReconciliationMessageInterface
{
    private int $invoiceId = 0;

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(int $invoiceId): self
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }
}
