<?php

declare(strict_types=1);

namespace Custom\PartialCaptureInventory\Model;

use Custom\PartialCaptureInventory\Api\Data\ReconciliationSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ReconciliationSearchResults extends SearchResults implements ReconciliationSearchResultsInterface
{
}
