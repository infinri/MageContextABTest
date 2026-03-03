<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Integration\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ReconciliationRepository / ResourceModel.
 * Covers: ENF-SYS-005 IR-1 (idempotent upsert uniqueness),
 *         ENF-SYS-005 IR-2 (CAS transition atomicity),
 *         Phase B DI-5 (unique constraint on invoice_id + order_item_id)
 *
 * These tests REQUIRE a real database and cannot be replaced by mocks.
 */
class ReconciliationRepositoryTest extends TestCase
{
    private ?ReconciliationResource $resource = null;
    private ?ResourceConnection $connection = null;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->resource = $objectManager->get(ReconciliationResource::class);
        $this->connection = $objectManager->get(ResourceConnection::class);
    }

    /**
     * IR-1: INSERT ON DUPLICATE KEY UPDATE prevents duplicate rows.
     * Insert same (invoice_id, order_item_id) twice → row count = 1, attempts = 2.
     */
    public function testUpsertCreatesRecordOnFirstCall(): void
    {
        $attempts = $this->resource->upsertRecord(
            invoiceId: 1001,
            orderItemId: 2001,
            orderId: 3001,
            sku: 'TEST-SKU-001',
            qtyCaptured: 2.0,
            stockId: 1
        );

        $this->assertEquals(1, $attempts, 'First upsert must return attempts = 1');

        $rows = $this->fetchRecordsByInvoiceAndItem(1001, 2001);
        $this->assertCount(1, $rows, 'Exactly one row must exist after first upsert');
        $this->assertEquals('pending', $rows[0]['status']);
        $this->assertEquals(1, (int) $rows[0]['attempts']);
    }

    /**
     * IR-1: Second upsert for same key increments attempts, does not create duplicate.
     */
    public function testUpsertIncrementsAttemptsOnDuplicateKey(): void
    {
        $this->resource->upsertRecord(1002, 2002, 3002, 'TEST-SKU-002', 3.0, 1);
        $attempts = $this->resource->upsertRecord(1002, 2002, 3002, 'TEST-SKU-002', 3.0, 1);

        $this->assertEquals(2, $attempts, 'Second upsert must return attempts = 2');

        $rows = $this->fetchRecordsByInvoiceAndItem(1002, 2002);
        $this->assertCount(1, $rows, 'Still exactly one row after duplicate upsert');
        $this->assertEquals(2, (int) $rows[0]['attempts']);
    }

    /**
     * IR-2: CAS UPDATE WHERE status='pending' returns affected=1 on first call.
     */
    public function testTransitionStatusSucceedsFromCorrectState(): void
    {
        $this->resource->upsertRecord(1003, 2003, 3003, 'TEST-SKU-003', 1.0, 1);
        $rows = $this->fetchRecordsByInvoiceAndItem(1003, 2003);
        $entityId = (int) $rows[0]['entity_id'];

        $affected = $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );

        $this->assertEquals(1, $affected, 'CAS transition from pending→reconciled must affect 1 row');

        $rows = $this->fetchRecordsByInvoiceAndItem(1003, 2003);
        $this->assertEquals('reconciled', $rows[0]['status']);
    }

    /**
     * IR-2: CAS UPDATE WHERE status='pending' returns affected=0 when status already changed.
     * Second actor attempting same transition gets graceful failure.
     */
    public function testTransitionStatusReturnsZeroWhenStatusAlreadyChanged(): void
    {
        $this->resource->upsertRecord(1004, 2004, 3004, 'TEST-SKU-004', 1.0, 1);
        $rows = $this->fetchRecordsByInvoiceAndItem(1004, 2004);
        $entityId = (int) $rows[0]['entity_id'];

        // First transition: pending → reconciled (succeeds)
        $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );

        // Second transition attempt: pending → reconciled (must fail gracefully)
        $affected = $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );

        $this->assertEquals(0, $affected, 'Second CAS attempt must get affected_rows = 0');

        // Status must still be 'reconciled', not corrupted
        $rows = $this->fetchRecordsByInvoiceAndItem(1004, 2004);
        $this->assertEquals('reconciled', $rows[0]['status']);
    }

    /**
     * IR-2: Terminal state cannot be transitioned away from.
     */
    public function testReleasedStateCannotBeTransitioned(): void
    {
        $this->resource->upsertRecord(1005, 2005, 3005, 'TEST-SKU-005', 1.0, 1);
        $rows = $this->fetchRecordsByInvoiceAndItem(1005, 2005);
        $entityId = (int) $rows[0]['entity_id'];

        // pending → reconciled → released
        $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );
        $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_RECONCILED,
            ReconciliationRecordInterface::STATUS_RELEASED
        );

        // Attempt: released → reconciled (must fail)
        $affected = $this->resource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_RELEASED,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );

        $this->assertEquals(0, $affected, 'Released state must be terminal — no transitions allowed');
    }

    protected function tearDown(): void
    {
        $conn = $this->connection->getConnection();
        $table = $this->connection->getTableName('custom_partial_capture_reconciliation');
        $conn->delete($table, ['invoice_id IN (?)' => [1001, 1002, 1003, 1004, 1005]]);
    }

    private function fetchRecordsByInvoiceAndItem(int $invoiceId, int $orderItemId): array
    {
        $conn = $this->connection->getConnection();
        $table = $this->connection->getTableName('custom_partial_capture_reconciliation');
        $select = $conn->select()
            ->from($table)
            ->where('invoice_id = ?', $invoiceId)
            ->where('order_item_id = ?', $orderItemId);
        return $conn->fetchAll($select);
    }
}
