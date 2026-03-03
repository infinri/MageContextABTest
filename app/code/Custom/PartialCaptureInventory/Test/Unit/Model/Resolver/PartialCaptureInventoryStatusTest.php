<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Unit\Model\Resolver;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\ReconciliationRepositoryInterface;
use Custom\PartialCaptureInventory\Model\Resolver\PartialCaptureInventoryStatus;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Authorization\Model\UserContextInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PartialCaptureInventoryStatus GraphQL resolver.
 * Covers: ENF-SEC-001 (access boundary — ownership enforcement),
 *         ENF-SEC-002 (data exposure minimization),
 *         Phase D security: authentication ≠ authorization
 */
class PartialCaptureInventoryStatusTest extends TestCase
{
    private ReconciliationRepositoryInterface|MockObject $reconciliationRepository;
    private OrderRepositoryInterface|MockObject $orderRepository;
    private LoggerInterface|MockObject $logger;
    private PartialCaptureInventoryStatus $resolver;

    protected function setUp(): void
    {
        $this->reconciliationRepository = $this->createMock(ReconciliationRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resolver = new PartialCaptureInventoryStatus(
            $this->reconciliationRepository,
            $this->orderRepository,
            $this->logger
        );
    }

    // --- ENF-SEC-001: Anonymous caller denied ---

    public function testResolveThrowsForAnonymousUser(): void
    {
        $context = $this->createContext(0, UserContextInterface::USER_TYPE_GUEST);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 100]
        );
    }

    // --- ENF-SEC-001: Customer without ownership denied ---

    public function testResolveThrowsWhenCustomerDoesNotOwnOrder(): void
    {
        $context = $this->createContext(5, UserContextInterface::USER_TYPE_CUSTOMER);
        $order = $this->createOrder(100, 999); // customer_id=999, not 5

        $this->orderRepository->method('get')->with(100)->willReturn($order);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 100]
        );
    }

    // --- ENF-SEC-001: Customer with ownership gets data ---

    public function testResolveReturnsDataWhenCustomerOwnsOrder(): void
    {
        $context = $this->createContext(5, UserContextInterface::USER_TYPE_CUSTOMER);
        $order = $this->createOrder(100, 5);

        $record = $this->createMock(ReconciliationRecordInterface::class);
        $record->method('getEntityId')->willReturn(1);
        $record->method('getInvoiceId')->willReturn(10);
        $record->method('getOrderItemId')->willReturn(20);
        $record->method('getSku')->willReturn('SKU-A');
        $record->method('getQtyCaptured')->willReturn(2.0);
        $record->method('getStatus')->willReturn('reconciled');

        $this->orderRepository->method('get')->with(100)->willReturn($order);
        $this->reconciliationRepository->method('getByOrderId')
            ->with(100)
            ->willReturn([$record]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 100]
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('SKU-A', $result[0]['sku']);
        $this->assertEquals('reconciled', $result[0]['status']);
    }

    // --- ENF-SEC-001: Admin gets data without ownership check ---

    public function testResolveReturnsDataForAdminWithoutOwnershipCheck(): void
    {
        $context = $this->createContext(1, UserContextInterface::USER_TYPE_ADMIN);

        $record = $this->createMock(ReconciliationRecordInterface::class);
        $record->method('getEntityId')->willReturn(1);
        $record->method('getInvoiceId')->willReturn(10);
        $record->method('getOrderItemId')->willReturn(20);
        $record->method('getSku')->willReturn('SKU-B');
        $record->method('getQtyCaptured')->willReturn(3.0);
        $record->method('getStatus')->willReturn('released');

        $this->reconciliationRepository->method('getByOrderId')
            ->with(200)
            ->willReturn([$record]);

        // Admin should NOT trigger order ownership check
        $this->orderRepository->expects($this->never())->method('get');

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 200]
        );

        $this->assertCount(1, $result);
        $this->assertEquals('SKU-B', $result[0]['sku']);
    }

    // --- ENF-SEC-001: Integration user gets data without ownership check ---

    public function testResolveReturnsDataForIntegrationUser(): void
    {
        $context = $this->createContext(1, UserContextInterface::USER_TYPE_INTEGRATION);

        $this->reconciliationRepository->method('getByOrderId')
            ->willReturn([]);

        $this->orderRepository->expects($this->never())->method('get');

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 300]
        );

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    // --- ENF-SEC-002: Response contains only approved fields ---

    public function testResolveDoesNotExposeInternalEntityId(): void
    {
        $context = $this->createContext(1, UserContextInterface::USER_TYPE_ADMIN);

        $record = $this->createMock(ReconciliationRecordInterface::class);
        $record->method('getEntityId')->willReturn(42);
        $record->method('getInvoiceId')->willReturn(10);
        $record->method('getOrderItemId')->willReturn(20);
        $record->method('getSku')->willReturn('SKU-C');
        $record->method('getQtyCaptured')->willReturn(1.0);
        $record->method('getStatus')->willReturn('pending');

        $this->reconciliationRepository->method('getByOrderId')->willReturn([$record]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            $context,
            $this->createMock(ResolveInfo::class),
            null,
            ['order_id' => 400]
        );

        // entity_id (DB primary key) must not be in GraphQL response
        $this->assertArrayNotHasKey('entity_id', $result[0]);
    }

    // --- Helpers ---

    private function createContext(int $userId, int $userType): ContextInterface|MockObject
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getUserId')->willReturn($userId);
        $context->method('getUserType')->willReturn($userType);
        return $context;
    }

    private function createOrder(int $orderId, int $customerId): OrderInterface|MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($orderId);
        $order->method('getCustomerId')->willReturn($customerId);
        return $order;
    }
}
