<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model\Resolver;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\ReconciliationRepositoryInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PartialCaptureInventoryStatus implements ResolverInterface
{
    public function __construct(
        private readonly ReconciliationRepositoryInterface $reconciliationRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     *
     * ENF-SEC-001: Access boundary enforcement.
     * - Guest/anonymous: denied
     * - Customer: ownership check (order.customer_id must match context user_id)
     * - Admin/Integration: unrestricted
     *
     * ENF-SEC-002: entity_id (DB primary key) excluded from response.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $userId = (int) $context->getUserId();
        $userType = (int) $context->getUserType();
        $orderId = (int) ($args['order_id'] ?? 0);

        $this->enforceAccessBoundary($userId, $userType, $orderId);

        $records = $this->reconciliationRepository->getByOrderId($orderId);

        return array_map([$this, 'formatRecord'], $records);
    }

    /**
     * ENF-SEC-001: Authentication ≠ authorization.
     * Ownership must be verified in code, not just declared.
     */
    private function enforceAccessBoundary(int $userId, int $userType, int $orderId): void
    {
        if ($userId === 0 || $userType === UserContextInterface::USER_TYPE_GUEST) {
            throw new GraphQlAuthorizationException(
                __('The current customer isn\'t authorized.')
            );
        }

        if ($userType === UserContextInterface::USER_TYPE_ADMIN
            || $userType === UserContextInterface::USER_TYPE_INTEGRATION
        ) {
            return;
        }

        if ($userType === UserContextInterface::USER_TYPE_CUSTOMER) {
            $order = $this->orderRepository->get($orderId);
            if ((int) $order->getCustomerId() !== $userId) {
                throw new GraphQlAuthorizationException(
                    __('The current customer isn\'t authorized.')
                );
            }
        }
    }

    /**
     * ENF-SEC-002: Only approved fields exposed. No entity_id.
     */
    private function formatRecord(ReconciliationRecordInterface $record): array
    {
        return [
            'invoice_id' => $record->getInvoiceId(),
            'order_item_id' => $record->getOrderItemId(),
            'order_id' => $record->getOrderId(),
            'sku' => $record->getSku(),
            'qty_captured' => $record->getQtyCaptured(),
            'stock_id' => $record->getStockId(),
            'status' => $record->getStatus(),
            'created_at' => $record->getCreatedAt(),
            'updated_at' => $record->getUpdatedAt(),
        ];
    }
}
