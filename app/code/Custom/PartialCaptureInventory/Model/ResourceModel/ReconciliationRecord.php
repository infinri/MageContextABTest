<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model\ResourceModel;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ReconciliationRecord extends AbstractDb
{
    public const TABLE_NAME = 'custom_partial_capture_reconciliation';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ReconciliationRecordInterface::ENTITY_ID);
    }

    /**
     * Idempotent upsert: INSERT ON DUPLICATE KEY UPDATE.
     * Uses unique constraint on (invoice_id, order_item_id) as idempotency key.
     * Returns the current attempts count after upsert.
     *
     * ENF-SYS-001 Race Window 1: duplicate delivery handled by DB unique constraint.
     * ENF-SYS-003: No read-then-write — single atomic SQL statement.
     */
    public function upsertRecord(
        int $invoiceId,
        int $orderItemId,
        int $orderId,
        string $sku,
        float $qtyCaptured,
        int $stockId
    ): int {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $connection->insertOnDuplicate(
            $table,
            [
                ReconciliationRecordInterface::INVOICE_ID => $invoiceId,
                ReconciliationRecordInterface::ORDER_ITEM_ID => $orderItemId,
                ReconciliationRecordInterface::ORDER_ID => $orderId,
                ReconciliationRecordInterface::SKU => $sku,
                ReconciliationRecordInterface::QTY_CAPTURED => $qtyCaptured,
                ReconciliationRecordInterface::STOCK_ID => $stockId,
                ReconciliationRecordInterface::STATUS => ReconciliationRecordInterface::STATUS_PENDING,
                ReconciliationRecordInterface::ATTEMPTS => 1,
            ],
            [
                ReconciliationRecordInterface::ATTEMPTS => new \Zend_Db_Expr(
                    ReconciliationRecordInterface::ATTEMPTS . ' + 1'
                ),
            ]
        );

        $select = $connection->select()
            ->from($table, [ReconciliationRecordInterface::ATTEMPTS])
            ->where(ReconciliationRecordInterface::INVOICE_ID . ' = ?', $invoiceId)
            ->where(ReconciliationRecordInterface::ORDER_ITEM_ID . ' = ?', $orderItemId);

        return (int) $connection->fetchOne($select);
    }

    /**
     * Atomic CAS status transition.
     * UPDATE ... SET status = :new WHERE entity_id = :id AND status = :old
     * Returns affected rows: 1 = success, 0 = already transitioned (contention or terminal).
     *
     * ENF-SYS-003: Atomic compare-and-swap. Second actor gets affected_rows = 0.
     */
    public function transitionStatus(int $entityId, string $fromStatus, string $toStatus): int
    {
        $connection = $this->getConnection();

        return (int) $connection->update(
            $this->getMainTable(),
            [ReconciliationRecordInterface::STATUS => $toStatus],
            [
                ReconciliationRecordInterface::ENTITY_ID . ' = ?' => $entityId,
                ReconciliationRecordInterface::STATUS . ' = ?' => $fromStatus,
            ]
        );
    }

    /**
     * Fetch entity_id for a given (invoice_id, order_item_id) pair.
     * Used after upsert to obtain the entity_id for subsequent CAS transitions.
     */
    public function getEntityIdByInvoiceAndItem(int $invoiceId, int $orderItemId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [ReconciliationRecordInterface::ENTITY_ID])
            ->where(ReconciliationRecordInterface::INVOICE_ID . ' = ?', $invoiceId)
            ->where(ReconciliationRecordInterface::ORDER_ITEM_ID . ' = ?', $orderItemId);

        $result = $connection->fetchOne($select);
        return $result !== false ? (int) $result : null;
    }
}
