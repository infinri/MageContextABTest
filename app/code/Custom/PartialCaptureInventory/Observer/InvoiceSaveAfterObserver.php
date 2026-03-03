<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Observer;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationMessageInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationMessageInterfaceFactory;
use Custom\PartialCaptureInventory\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

class InvoiceSaveAfterObserver implements ObserverInterface
{
    public const TOPIC_NAME = 'custom.partialcapture.reconciliation.process';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly ReconciliationMessageInterfaceFactory $messageFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var \Magento\Sales\Api\Data\InvoiceInterface $invoice */
        $invoice = $observer->getEvent()->getData('invoice');
        if ($invoice === null) {
            return;
        }

        $invoiceId = (int) $invoice->getEntityId();
        if ($invoiceId === 0) {
            return;
        }

        try {
            /** @var ReconciliationMessageInterface $message */
            $message = $this->messageFactory->create();
            $message->setInvoiceId($invoiceId);
            $this->publisher->publish(self::TOPIC_NAME, $message);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish reconciliation message', [
                'exception' => $e,
                'event' => 'pci_publish_failed',
                'invoice_id' => $invoiceId,
            ]);
        }
    }
}
