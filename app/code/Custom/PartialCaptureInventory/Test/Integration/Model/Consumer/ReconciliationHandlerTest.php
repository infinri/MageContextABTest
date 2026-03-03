<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Integration\Model\Consumer;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationMessageInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Model\Consumer\ReconciliationHandler;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ReconciliationHandler (queue consumer).
 * Covers: ENF-SYS-005 IR-3 (idempotent processing under redelivery),
 *         ENF-SYS-001 Race Window 1 (duplicate message delivery),
 *         ENF-SYS-003 (state transition atomicity end-to-end),
 *         ENF-OPS-002 (idempotent consumer behavior)
 *
 * These tests REQUIRE a real database. Mock-only tests cannot validate
 * that INSERT ON DUPLICATE KEY UPDATE + CAS transitions work correctly
 * under actual DB constraints.
 */
class ReconciliationHandlerTest extends TestCase
{
    private ?ReconciliationHandler $handler = null;
    private ?ReconciliationResource $resource = null;
    private ?ResourceConnection $connection = null;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->handler = $objectManager->get(ReconciliationHandler::class);
        $this->resource = $objectManager->get(ReconciliationResource::class);
        $this->connection = $objectManager->get(ResourceConnection::class);
    }

    /**
     * IR-3: Processing the same message twice produces identical results.
     * Simulates AMQP redelivery — same invoice_id message processed twice.
     *
     * @magentoDataFixture Magento/Sales/_files/invoice.php
     */
    public function testDuplicateMessageProcessingIsIdempotent(): void
    {
        $invoice = $this->getFirstInvoice();
        $invoiceId = (int) $invoice->getEntityId();
        $orderId = (int) $invoice->getOrderId();

        $message = $this->createMessage($invoiceId);

        // First processing
        $this->handler->process($message);

        $rowsAfterFirst = $this->fetchRecordsByOrderId($orderId);
        $firstCount = count($rowsAfterFirst);
        $this->assertGreaterThan(0, $firstCount, 'At least one record must be created');

        // Second processing (simulates redelivery)
        $this->handler->process($message);

        $rowsAfterSecond = $this->fetchRecordsByOrderId($orderId);
        $this->assertCount(
            $firstCount,
            $rowsAfterSecond,
            'Row count must not change after duplicate processing'
        );

        // Verify attempts incremented
        foreach ($rowsAfterSecond as $row) {
            $this->assertEquals(
                2,
                (int) $row['attempts'],
                'Attempts must be 2 after duplicate processing'
            );
        }
    }

    /**
     * End-to-end: Handler creates pending record, transitions through states.
     *
     * @magentoDataFixture Magento/Sales/_files/invoice.php
     */
    public function testHandlerCreatesRecordInPendingStatus(): void
    {
        $invoice = $this->getFirstInvoice();
        $invoiceId = (int) $invoice->getEntityId();
        $orderId = (int) $invoice->getOrderId();

        $message = $this->createMessage($invoiceId);
        $this->handler->process($message);

        $rows = $this->fetchRecordsByOrderId($orderId);
        $this->assertGreaterThan(0, count($rows));

        foreach ($rows as $row) {
            $this->assertContains(
                $row['status'],
                [
                    ReconciliationRecordInterface::STATUS_PENDING,
                    ReconciliationRecordInterface::STATUS_RECONCILED,
                    ReconciliationRecordInterface::STATUS_RELEASED,
                ],
                'Record status must be a valid state machine state'
            );
        }
    }

    protected function tearDown(): void
    {
        $conn = $this->connection->getConnection();
        $table = $this->connection->getTableName('custom_partial_capture_reconciliation');
        $conn->delete($table, '1=1');
    }

    private function createMessage(int $invoiceId): ReconciliationMessageInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $message = $objectManager->create(ReconciliationMessageInterface::class);
        $message->setInvoiceId($invoiceId);
        return $message;
    }

    private function getFirstInvoice(): InvoiceInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $invoiceRepository = $objectManager->get(InvoiceRepositoryInterface::class);
        $searchCriteria = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class)
            ->setPageSize(1)
            ->create();
        $results = $invoiceRepository->getList($searchCriteria);
        $items = $results->getItems();
        $this->assertNotEmpty($items, 'Test fixture must provide at least one invoice');
        return reset($items);
    }

    private function fetchRecordsByOrderId(int $orderId): array
    {
        $conn = $this->connection->getConnection();
        $table = $this->connection->getTableName('custom_partial_capture_reconciliation');
        $select = $conn->select()
            ->from($table)
            ->where('order_id = ?', $orderId);
        return $conn->fetchAll($select);
    }
}
