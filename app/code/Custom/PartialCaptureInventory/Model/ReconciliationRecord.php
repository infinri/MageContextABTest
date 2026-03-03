<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Magento\Framework\Model\AbstractModel;

class ReconciliationRecord extends AbstractModel implements ReconciliationRecordInterface
{
    protected function _construct(): void
    {
        $this->_init(ReconciliationResource::class);
    }

    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, (int) $entityId);
    }

    public function getInvoiceId(): int
    {
        return (int) $this->getData(self::INVOICE_ID);
    }

    public function setInvoiceId(int $invoiceId): self
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
    }

    public function getOrderItemId(): int
    {
        return (int) $this->getData(self::ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $orderItemId): self
    {
        return $this->setData(self::ORDER_ITEM_ID, $orderItemId);
    }

    public function getOrderId(): int
    {
        return (int) $this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getSku(): string
    {
        return (string) $this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getQtyCaptured(): float
    {
        return (float) $this->getData(self::QTY_CAPTURED);
    }

    public function setQtyCaptured(float $qtyCaptured): self
    {
        return $this->setData(self::QTY_CAPTURED, $qtyCaptured);
    }

    public function getStockId(): int
    {
        return (int) $this->getData(self::STOCK_ID);
    }

    public function setStockId(int $stockId): self
    {
        return $this->setData(self::STOCK_ID, $stockId);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getAttempts(): int
    {
        return (int) $this->getData(self::ATTEMPTS);
    }

    public function setAttempts(int $attempts): self
    {
        return $this->setData(self::ATTEMPTS, $attempts);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
