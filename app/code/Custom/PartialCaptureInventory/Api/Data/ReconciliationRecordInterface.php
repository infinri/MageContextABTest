<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Api\Data;

/**
 * @api
 */
interface ReconciliationRecordInterface
{
    public const ENTITY_ID = 'entity_id';
    public const INVOICE_ID = 'invoice_id';
    public const ORDER_ITEM_ID = 'order_item_id';
    public const ORDER_ID = 'order_id';
    public const SKU = 'sku';
    public const QTY_CAPTURED = 'qty_captured';
    public const STOCK_ID = 'stock_id';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_RELEASED = 'released';
    public const STATUS_FAILED = 'failed';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return self
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return int
     */
    public function getInvoiceId(): int;

    /**
     * @param int $invoiceId
     * @return self
     */
    public function setInvoiceId(int $invoiceId): self;

    /**
     * @return int
     */
    public function getOrderItemId(): int;

    /**
     * @param int $orderItemId
     * @return self
     */
    public function setOrderItemId(int $orderItemId): self;

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     * @return self
     */
    public function setOrderId(int $orderId): self;

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @param string $sku
     * @return self
     */
    public function setSku(string $sku): self;

    /**
     * @return float
     */
    public function getQtyCaptured(): float;

    /**
     * @param float $qtyCaptured
     * @return self
     */
    public function setQtyCaptured(float $qtyCaptured): self;

    /**
     * @return int
     */
    public function getStockId(): int;

    /**
     * @param int $stockId
     * @return self
     */
    public function setStockId(int $stockId): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self;

    /**
     * @return int
     */
    public function getAttempts(): int;

    /**
     * @param int $attempts
     * @return self
     */
    public function setAttempts(int $attempts): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt): self;
}
