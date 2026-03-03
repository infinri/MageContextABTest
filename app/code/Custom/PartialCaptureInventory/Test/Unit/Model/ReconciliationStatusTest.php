<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Test\Unit\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationRecordInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ReconciliationRecord status constants and state machine validity.
 * Covers: ENF-SYS-003 (state transition atomicity — constant definitions),
 *         Phase B DI-8 (status follows defined state machine)
 */
class ReconciliationStatusTest extends TestCase
{
    public function testStatusConstantsAreDefined(): void
    {
        $this->assertEquals('pending', ReconciliationRecordInterface::STATUS_PENDING);
        $this->assertEquals('reconciled', ReconciliationRecordInterface::STATUS_RECONCILED);
        $this->assertEquals('released', ReconciliationRecordInterface::STATUS_RELEASED);
        $this->assertEquals('failed', ReconciliationRecordInterface::STATUS_FAILED);
    }

    /**
     * @dataProvider legalTransitionsProvider
     */
    public function testLegalTransitionsAreComplete(string $from, string $to): void
    {
        $legal = [
            ReconciliationRecordInterface::STATUS_PENDING => [
                ReconciliationRecordInterface::STATUS_RECONCILED,
                ReconciliationRecordInterface::STATUS_FAILED,
            ],
            ReconciliationRecordInterface::STATUS_RECONCILED => [
                ReconciliationRecordInterface::STATUS_RELEASED,
            ],
        ];

        $this->assertArrayHasKey($from, $legal, "State '$from' must have defined transitions");
        $this->assertContains($to, $legal[$from], "Transition '$from' → '$to' must be legal");
    }

    public static function legalTransitionsProvider(): array
    {
        return [
            'pending to reconciled' => ['pending', 'reconciled'],
            'pending to failed' => ['pending', 'failed'],
            'reconciled to released' => ['reconciled', 'released'],
        ];
    }

    /**
     * @dataProvider illegalTransitionsProvider
     */
    public function testIllegalTransitionsAreRejected(string $from, string $to): void
    {
        $legal = [
            ReconciliationRecordInterface::STATUS_PENDING => [
                ReconciliationRecordInterface::STATUS_RECONCILED,
                ReconciliationRecordInterface::STATUS_FAILED,
            ],
            ReconciliationRecordInterface::STATUS_RECONCILED => [
                ReconciliationRecordInterface::STATUS_RELEASED,
            ],
            ReconciliationRecordInterface::STATUS_RELEASED => [],
            ReconciliationRecordInterface::STATUS_FAILED => [],
        ];

        $allowed = $legal[$from] ?? [];
        $this->assertNotContains($to, $allowed, "Transition '$from' → '$to' must be illegal");
    }

    public static function illegalTransitionsProvider(): array
    {
        return [
            'reconciled to pending (no rollback)' => ['reconciled', 'pending'],
            'released to pending (terminal)' => ['released', 'pending'],
            'released to reconciled (terminal)' => ['released', 'reconciled'],
            'failed to pending (requires manual)' => ['failed', 'pending'],
            'failed to reconciled (requires manual)' => ['failed', 'reconciled'],
        ];
    }
}
