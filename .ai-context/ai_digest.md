# AI Context Digest

> **This file is the AI entry point.** Read this before any raw JSON.

- **Version:** 2.0.0
- **Generated:** 2026-02-27 17:30:45 UTC
- **Repository:** `/home/lucio.saldivar/workspaces/MageContextABTest`
- **Target:** magento
- **Duration:** 112.58s

---

## System Summary

- **Modules:** 0
- **Composer packages (Magento ecosystem):** 414
- **Cross-module dependencies:** 3
- **Average instability:** 0.333
- **Plugins:** 836
- **Intercepted methods:** 0
- **Max plugin depth:** 0
- **Cross-module plugins:** 716
- **Observers:** 447
- **Events tracked:** 257
- **High-risk events:** 2
- **DI preferences:** 0
- **Virtual types:** 784
- **Proxies:** 198
- **Core overrides:** 1464
- **Total deviations:** 5260
  - critical: 2112
  - high: 1634
  - medium: 814
  - low: 700
- **Files classified:** 17131
- **Layer violations:** 0
- **Architectural debt items:** 1
- **Circular dependencies:** 0
- **God modules:** 0
- **Performance risk indicators:** 63
- **High-risk modules (modifiability):** 0

---

## Top 10 Architectural Hotspots

No hotspot data available.

---

## Most Overridden Classes

| Class | Override Count |
|-------|---------------|
| `Magento\AdminAdobeIms\Api\Data\ImsWebapiInterface` | 1 |
| `Magento\AdminAdobeIms\Api\Data\ImsWebapiSearchResultsInterface` | 1 |
| `Magento\AdminAdobeIms\Api\ImsWebapiRepositoryInterface` | 1 |
| `Magento\AdminAdobeIms\Api\TokenReaderInterface` | 1 |
| `Magento\AdobeImsApi\Api\ConfigInterface` | 1 |
| `Magento\AdobeImsApi\Api\Data\TokenResponseInterface` | 1 |
| `Magento\AdobeImsApi\Api\Data\UserProfileInterface` | 1 |
| `Magento\AdobeImsApi\Api\FlushUserTokensInterface` | 1 |
| `Magento\AdobeImsApi\Api\GetAccessTokenInterface` | 1 |
| `Magento\AdobeImsApi\Api\GetImageInterface` | 1 |

---

## Deepest Plugin Stacks

No plugin stacks deeper than 5. Max depth: 0.

---

## Highest Risk Events

| Event | Listeners | Cross-Module | Risk Score |
|-------|-----------|--------------|------------|
| `customer_logout` | 10 | 7 | 0.773 |
| `controller_action_predispatch` | 10 | 6 | 0.727 |

---

## DI Area Overrides

No area-specific DI resolution conflicts detected.

---

## Most Unstable Modules (Coupling)

| Module | Afferent (Ca) | Efferent (Ce) | Instability |
|--------|---------------|---------------|-------------|
| `Magento_SomeModule` | 0 | 2 | 1 |
| `Magento_Framework` | 1 | 0 | 0 |
| `Magento_Store` | 1 | 0 | 0 |

---

## Deviation Summary

**Total:** 5260

- **Critical:** 2112
- **High:** 1634
- **Medium:** 814
- **Low:** 700

**By type:**

- `object_manager_usage`: 2112
- `core_preference_override`: 1555
- `core_plugin`: 817
- `core_class_extension`: 700
- `direct_sql`: 75
- `template_override`: 1

---

## Layer Violations

No cross-layer violations detected.

---

## Architectural Debt

**Total debt items:** 1

- **[MEDIUM]** Class Magento\Framework\App\CacheInterface overridden by 2 modules

---

## Performance Risk Indicators

**Total indicators:** 63

- **[HIGH]** Plugin depth 17 on `Magento\Catalog\Model\ResourceModel\Product`
- **[HIGH]** 11 observers on `sales_model_service_quote_submit_success`
- **[HIGH]** Plugin depth 10 on `Magento\Framework\App\FrontControllerInterface`
- **[HIGH]** Plugin depth 9 on `Magento\Bundle\Api\ProductLinkManagementInterface`
- **[HIGH]** Plugin depth 9 on `Magento\Catalog\Model\ResourceModel\Category`
- **[HIGH]** Plugin depth 8 on `Magento\Framework\View\Layout`
- **[HIGH]** Plugin depth 8 on `Magento\InventoryIndexer\Indexer\Stock\Strategy\Sync`
- **[HIGH]** Plugin depth 7 on `Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper`
- **[HIGH]** Plugin depth 7 on `Magento\Catalog\Model\Product`
- **[HIGH]** Plugin depth 7 on `Magento\Store\Model\ResourceModel\Store`

---

## Modifiability Risk (Top 10)

| Module | Risk Score | Coupling | Plugins | Core Overrides | Churn | Deviations |
|--------|------------|----------|---------|----------------|-------|------------|
| `Magento_SomeModule` | 0.35 | 1 | 0 | 2 | 0 | 0 |

---

## Hotspot Ranking (Churn + Centrality)

| Module | Score | Churn | Centrality |
|--------|-------|-------|------------|
| `Magento_SomeModule` | 1 | 0 | 2 |
| `Magento_Store` | 0.8667 | 0 | 1 |
| `Magento_Framework` | 0.8667 | 0 | 1 |

---

## Quick Lookup Guide

Use these indexes for O(1) lookups instead of scanning raw extractor output:

| Query | Index File | Key |
|-------|-----------|-----|
| Where is class X defined? | `indexes/symbol_index.json` | `symbols[].class_id` |
| What module owns file Y? | `indexes/file_index.json` | `files[].file_id` |
| All facts about class X | `reverse_index/reverse_index.json` | `by_class[class_id]` |
| All facts about module M | `reverse_index/reverse_index.json` | `by_module[module_id]` |
| Who listens to event E? | `reverse_index/reverse_index.json` | `by_event[event_id]` |
| What handles route R? | `reverse_index/reverse_index.json` | `by_route[route_id]` |
| Area-specific modules | `allocation_view/areas.json` | `areas[area].modules` |

- 16702 symbols in symbol index (14518 classs, 2169 interfaces, 15 traits)
- 28182 files in file index

---

## Available Data Files

| View | File | Items |
|------|------|-------|
| module_view | `module_view/modules.json` | 419 |
| module_view | `module_view/dependencies.json` | 13 |
| module_view | `module_view/layer_classification.json` | 17135 |
| runtime_view | `runtime_view/execution_paths.json` | 1511 |
| runtime_view | `runtime_view/di_resolution_map.json` | 2491 |
| runtime_view | `runtime_view/plugin_chains.json` | 1329 |
| runtime_view | `runtime_view/event_graph.json` | 733 |
| module_view | `module_view/layout_handles.json` | 4292 |
| runtime_view | `runtime_view/route_map.json` | 132 |
| runtime_view | `runtime_view/cron_map.json` | 65 |
| runtime_view | `runtime_view/cli_commands.json` | 127 |
| module_view | `module_view/ui_components.json` | 343 |
| module_view | `module_view/db_schema_patches.json` | 715 |
| module_view | `module_view/api_surface.json` | 1018 |
| quality_metrics | `quality_metrics/custom_deviations.json` | 5263 |
| quality_metrics | `quality_metrics/custom_deviations.md` | 5263 |
| quality_metrics | `quality_metrics/modifiability.json` | 5 |
| quality_metrics | `quality_metrics/performance.json` | 68 |
| quality_metrics | `quality_metrics/architectural_debt.json` | 10 |
| quality_metrics | `quality_metrics/hotspot_ranking.json` | 9 |
| allocation_view | `allocation_view/areas.json` | 10 |
| runtime_view | `runtime_view/call_graph.json` | 2299 |
| runtime_view | `runtime_view/service_contracts.json` | 396 |
| runtime_view | `runtime_view/repository_patterns.json` | 150 |
| module_view | `module_view/entity_relationships.json` | 1177 |
| runtime_view | `runtime_view/plugin_seam_timing.json` | 1616 |
| runtime_view | `runtime_view/safe_api_matrix.json` | 3458 |
| module_view | `module_view/dto_data_interfaces.json` | 637 |
| runtime_view | `runtime_view/implementation_patterns.json` | 693 |
| . | `repo_map.json` | 52533 |
| quality_metrics | `quality_metrics/git_churn_hotspots.json` | 2 |
| indexes | `indexes/symbol_index.json` | 16710 |
| indexes | `indexes/file_index.json` | 28185 |
