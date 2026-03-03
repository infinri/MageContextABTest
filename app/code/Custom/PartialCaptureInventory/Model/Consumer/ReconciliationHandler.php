<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model\Consumer;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationMessageInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\ReconciliationRepositoryInterface;
use Custom\PartialCaptureInventory\Model\Config;
use Custom\PartialCaptureInventory\Model\ReconciliationRecordFactory;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ReconciliationHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ReconciliationRepositoryInterface $reconciliationRepository,
        private readonly ReconciliationResource $reconciliationResource,
        private readonly ReconciliationRecordFactory $recordFactory,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process a reconciliation message from the queue.
     *
     * ENF-SYS-002: No MSI salability re-evaluation. Invoice existence = authority.
     * ENF-SYS-003: State transitions via atomic CAS on resource model.
     * ENF-SYS-001: Idempotent upsert handles duplicate delivery.
     * ENF-OPS-002: Idempotent processing — same message twice = same outcome.
     */
    public function process(ReconciliationMessageInterface $message): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $invoiceId = $message->getInvoiceId();

        try {
            $invoice = $this->invoiceRepository->get($invoiceId);
            $orderId = (int) $invoice->getOrderId();
            $order = $this->orderRepository->get($orderId);

            if (!in_array($order->getState(), $this->config->getEligibleOrderStates(), true)) {
                $this->logger->info('Order state not eligible for reconciliation', [
                    'event' => 'pci_order_state_skip',
                    'invoice_id' => $invoiceId,
                    'order_id' => $orderId,
                    'state' => $order->getState(),
                ]);
                return;
            }

            $stockId = $this->resolveStockId((int) $order->getStoreId());
            $this->processInvoiceItems($invoice->getItems(), $invoiceId, $orderId, $stockId);
        } catch (\Throwable $e) {
            $this->logger->error('Reconciliation processing failed', [
                'exception' => $e,
                'event' => 'pci_processing_failed',
                'invoice_id' => $invoiceId,
            ]);
            throw $e;
        }
    }

    /**
     * Process each invoice item: upsert record, check attempts, transition status.
     *
     * @param InvoiceItemInterface[] $items
     */
    private function processInvoiceItems(array $items, int $invoiceId, int $orderId, int $stockId): void
    {
        $maxRetries = $this->config->getMaxRetries();

        foreach ($items as $item) {
            $qty = (float) $item->getQty();
            if ($qty <= 0.0) {
                continue;
            }

            $orderItemId = (int) $item->getOrderItemId();
            $sku = (string) $item->getSku();

            $attempts = $this->reconciliationResource->upsertRecord(
                $invoiceId,
                $orderItemId,
                $orderId,
                $sku,
                $qty,
                $stockId
            );

            if ($attempts >= $maxRetries) {
                $this->handleMaxRetriesExceeded($invoiceId, $orderItemId);
                continue;
            }

            $this->transitionToReconciled($invoiceId, $orderItemId);
        }
    }

    private function handleMaxRetriesExceeded(int $invoiceId, int $orderItemId): void
    {
        $entityId = $this->reconciliationResource->getEntityIdByInvoiceAndItem($invoiceId, $orderItemId);
        if ($entityId === null) {
            return;
        }

        $affected = $this->reconciliationResource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_FAILED
        );

        if ($affected > 0) {
            $this->logger->critical('Reconciliation record exhausted retries', [
                'event' => 'pci_max_retries_exceeded',
                'entity_id' => $entityId,
                'invoice_id' => $invoiceId,
                'order_item_id' => $orderItemId,
            ]);
        }
    }

    private function transitionToReconciled(int $invoiceId, int $orderItemId): void
    {
        $entityId = $this->reconciliationResource->getEntityIdByInvoiceAndItem($invoiceId, $orderItemId);
        if ($entityId === null) {
            return;
        }

        $affected = $this->reconciliationResource->transitionStatus(
            $entityId,
            ReconciliationRecordInterface::STATUS_PENDING,
            ReconciliationRecordInterface::STATUS_RECONCILED
        );

        if ($affected === 0) {
            $this->logger->info('Reconciliation record already transitioned', [
                'event' => 'pci_already_transitioned',
                'entity_id' => $entityId,
                'invoice_id' => $invoiceId,
                'order_item_id' => $orderItemId,
            ]);
        }
    }

    /**
     * Resolve MSI stock ID from the order's store → website → sales channel mapping.
     * FW-M2-RT-006: Uses StockResolverInterface with website sales channel.
     */
    private function resolveStockId(int $storeId): int
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteCode = $store->getWebsite()->getCode();
        $stock = $this->stockResolver->execute('website', $websiteCode);
        return (int) $stock->getStockId();
    }
}
