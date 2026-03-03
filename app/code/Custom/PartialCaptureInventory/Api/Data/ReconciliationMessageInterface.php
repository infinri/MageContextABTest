<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Api\Data;

/**
 * Queue message DTO for reconciliation processing.
 * Carries only invoice_id — consumer resolves all other data from repositories.
 *
 * @api
 */
interface ReconciliationMessageInterface
{
    /**
     * @return int
     */
    public function getInvoiceId(): int;

    /**
     * @param int $invoiceId
     * @return self
     */
    public function setInvoiceId(int $invoiceId): self;
}
