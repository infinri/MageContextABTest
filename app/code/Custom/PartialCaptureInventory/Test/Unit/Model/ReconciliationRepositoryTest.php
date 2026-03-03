<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Unit\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterface;
use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterfaceFactory;
use Custom\PartialCaptureInventory\Api\ReconciliationRepositoryInterface;
use Custom\PartialCaptureInventory\Model\ReconciliationRecord;
use Custom\PartialCaptureInventory\Model\ReconciliationRecordFactory;
use Custom\PartialCaptureInventory\Model\ReconciliationRepository;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord as ReconciliationResource;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord\Collection;
use Custom\PartialCaptureInventory\Model\ResourceModel\ReconciliationRecord\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReconciliationRepository.
 * Covers: Phase B DI-1 through DI-5 (persistence-based invariants),
 *         ENF-PRE-002 (persistence verification), FW-M2-002 (no Model::load)
 */
class ReconciliationRepositoryTest extends TestCase
{
    private ReconciliationResource|MockObject $resource;
    private ReconciliationRecordFactory|MockObject $recordFactory;
    private CollectionFactory|MockObject $collectionFactory;
    private ReconciliationSearchResultsInterfaceFactory|MockObject $searchResultsFactory;
    private CollectionProcessorInterface|MockObject $collectionProcessor;
    private ReconciliationRepository $repository;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ReconciliationResource::class);
        $this->recordFactory = $this->createMock(ReconciliationRecordFactory::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactory = $this->createMock(ReconciliationSearchResultsInterfaceFactory::class);
        $this->collectionProcessor = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new ReconciliationRepository(
            $this->resource,
            $this->recordFactory,
            $this->collectionFactory,
            $this->searchResultsFactory,
            $this->collectionProcessor
        );
    }

    public function testGetByIdReturnsRecordWhenExists(): void
    {
        $record = $this->createMock(ReconciliationRecord::class);
        $record->method('getEntityId')->willReturn(42);

        $this->recordFactory->method('create')->willReturn($record);
        $this->resource->expects($this->once())
            ->method('load')
            ->with($record, 42);

        $result = $this->repository->getById(42);
        $this->assertEquals(42, $result->getEntityId());
    }

    public function testGetByIdThrowsNoSuchEntityWhenNotFound(): void
    {
        $record = $this->createMock(ReconciliationRecord::class);
        $record->method('getEntityId')->willReturn(null);

        $this->recordFactory->method('create')->willReturn($record);

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById(999);
    }

    public function testSaveReturnsRecordOnSuccess(): void
    {
        $record = $this->createMock(ReconciliationRecord::class);
        $this->resource->expects($this->once())->method('save')->with($record);

        $result = $this->repository->save($record);
        $this->assertSame($record, $result);
    }

    public function testSaveThrowsCouldNotSaveOnFailure(): void
    {
        $record = $this->createMock(ReconciliationRecord::class);
        $this->resource->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(CouldNotSaveException::class);
        $this->repository->save($record);
    }

    public function testGetByOrderIdReturnsRecords(): void
    {
        $record1 = $this->createMock(ReconciliationRecord::class);
        $record2 = $this->createMock(ReconciliationRecord::class);

        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([$record1, $record2]);

        $this->collectionFactory->method('create')->willReturn($collection);
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(ReconciliationRecordInterface::ORDER_ID, 100);

        $result = $this->repository->getByOrderId(100);
        $this->assertCount(2, $result);
    }

    public function testGetByOrderIdReturnsEmptyArrayWhenNoneExist(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->repository->getByOrderId(999);
        $this->assertCount(0, $result);
    }

    public function testGetListAppliesSearchCriteria(): void
    {
        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([]);
        $collection->method('getSize')->willReturn(0);

        $searchResults = $this->createMock(ReconciliationSearchResultsInterface::class);
        $searchResults->expects($this->once())->method('setSearchCriteria')->with($searchCriteria);
        $searchResults->expects($this->once())->method('setItems')->with([]);
        $searchResults->expects($this->once())->method('setTotalCount')->with(0);

        $this->collectionFactory->method('create')->willReturn($collection);
        $this->searchResultsFactory->method('create')->willReturn($searchResults);
        $this->collectionProcessor->expects($this->once())
            ->method('process')
            ->with($searchCriteria, $collection);

        $result = $this->repository->getList($searchCriteria);
        $this->assertSame($searchResults, $result);
    }
}
