# Architectural Plan — Coupon Validation & High-Value Cart Behavior

## 1. Module Identity

- **Vendor/Module**: `Custom_CouponValidation`
- **Location**: `app/code/Custom/CouponValidation`
- **Magento version**: 2.4.8-p3 (Open Source)

---

## 2. System Areas Affected

| Area | Impact | Mechanism |
|---|---|---|
| **Coupon application (frontend)** | Primary target — all three validation rules fire here | Plugin on `Magento\SalesRule\Model\CouponManagementService` or equivalent service contract |
| **Coupon application (REST)** | Must behave identically to frontend | Same plugin — REST routes through the same service contract |
| **Coupon application (GraphQL)** | Must behave identically to frontend | Same plugin — GraphQL resolvers delegate to the same service layer |
| **Admin order creation** | Must NOT be affected | Plugin scoped to `frontend`/`webapi_rest`/`graphql` areas only; admin area excluded |
| **Sales rule processing / discount calculation** | Must NOT be touched | No modifications to rule validators, totals collectors, or discount calculators |
| **Logging subsystem** | New custom log channel added | Dedicated `Logger\Handler` writing to `var/log/custom_coupon_validation.log` |

---

## 3. High-Level Architecture

```
┌──────────────────────────────────────────────────┐
│            Coupon Apply Request                   │
│  (Frontend / REST / GraphQL)                      │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│  Plugin (before)                                  │
│  CouponApplyValidationPlugin                      │
│                                                   │
│  1. Load quote, extract metadata                  │
│  2. Customer Group check (Group ID = 3 → reject)  │
│  3. VIP prefix check → delegate to VipValidator   │
│  4. High-value cart check → add notice message     │
│  5. Log when required                             │
│                                                   │
│  If rejected → throw CouldNotSaveException        │
│  If passed  → return to original method           │
└──────────────┬───────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────┐
│  Magento Core: CouponManagementService::set()    │
│  (Unmodified — handles rule matching, discount    │
│   calculation, totals collection)                 │
└──────────────────────────────────────────────────┘
```

The plugin operates **before** core processing. Rejected coupons never reach the discount engine. Accepted coupons proceed through Magento's native pipeline with zero modifications.

---

## 4. Detailed Component Design

### 4.1 Plugin — `CouponApplyValidationPlugin`

**Target**: `Magento\Quote\Api\CouponManagementInterface::set`

This is the service contract used by REST (`/V1/carts/:cartId/coupons/:couponCode`), GraphQL (`applyCouponToCart`), and the frontend checkout. A single `beforeSet` plugin covers all three entry points uniformly.

**Responsibilities** (executed in order):

1. **Load the quote** via `CartRepositoryInterface::getActive($cartId)`.
2. **Customer Group Restriction** — if `$quote->getCustomerGroupId() == 3`, throw `CouldNotSaveException` with message: *"This coupon is not valid for your customer group."*
3. **VIP Coupon Validation** — if `$couponCode` starts with `VIP-`, delegate to `VipValidatorInterface`. On failure, log and throw `CouldNotSaveException`.
4. **High-Value Cart Warning** — if visible item count > 3 AND subtotal > 500, add a notice message via `MessageManagerInterface` and log a structured entry.

**Why `beforeSet`?**
- Runs before Magento applies the coupon, so rejected coupons never modify totals.
- Does not wrap or replace the original method — Magento core logic executes untouched when the plugin returns.
- No risk of recursive execution or interference with stop-rules processing.

### 4.2 VIP Validator — `VipValidatorInterface` / `VipValidator`

**Interface**: `Custom\CouponValidation\Api\VipValidatorInterface`

```
public function validate(string $couponCode, CartInterface $quote): bool;
```

**Default implementation**: `Custom\CouponValidation\Model\VipValidator`

- Contains the custom VIP validation logic, fully decoupled from the plugin.
- Returns `true` (pass) or `false` (fail).
- Swappable via `di.xml` preference — allows future extension without modifying existing code.

**Current implementation scope**: Since the environment has no external VIP service defined, the default implementation will validate that the coupon code matches an active sales rule (i.e., the `VIP-TEST20` rule exists and is active). This proves the mechanism works without inventing business logic not specified in the requirements.

### 4.3 Logger — `Logger\CouponValidationLogger`

**Handler**: `Custom\CouponValidation\Logger\Handler`
- Extends `Magento\Framework\Logger\Handler\Base`
- Writes to `var/log/custom_coupon_validation.log`
- Log level: `DEBUG` (captures all validation events)

**Logger**: `Custom\CouponValidation\Logger\CouponValidationLogger`
- Extends `Monolog\Logger`
- Injected into the plugin and VIP validator via DI

**Structured log entry format** (JSON):
```json
{
  "quote_id": 42,
  "customer_group_id": 1,
  "subtotal": 750.00,
  "item_count": 5,
  "coupon_code": "VIP-TEST20",
  "validation_outcome": "high_value_cart_warning"
}
```

**When logging occurs** (per requirements):
- High-value cart warning triggered → logged
- VIP validation failure → logged
- Normal coupon success → **not logged** (per performance constraint)

### 4.4 Message Delivery

- **Frontend**: `MessageManagerInterface::addNoticeMessage()` for the high-value cart warning. This renders as a non-blocking yellow notice in checkout.
- **REST/GraphQL**: The notice message propagates through Magento's standard message response mechanism. For REST/GraphQL the warning is informational and does not block — the coupon still applies.
- **Error messages** (group restriction, VIP failure): Delivered via `CouldNotSaveException`, which Magento translates to appropriate HTTP 4xx / GraphQL error responses automatically.

---

## 5. File Structure

```
app/code/Custom/CouponValidation/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml                          # Plugin declaration, VIP validator preference, logger config
│   └── frontend/
│       └── di.xml                      # (only if area-scoping is needed; see §6)
├── Api/
│   └── VipValidatorInterface.php
├── Model/
│   └── VipValidator.php
├── Plugin/
│   └── CouponApplyValidationPlugin.php
├── Logger/
│   ├── CouponValidationLogger.php
│   └── Handler.php
└── Test/
    └── Unit/
        ├── Plugin/
        │   └── CouponApplyValidationPluginTest.php
        └── Model/
            └── VipValidatorTest.php
```

---

## 6. Area Scoping Strategy

**Decision**: Register the plugin in the **global** `etc/di.xml`.

**Rationale**:
- The requirements state behavior must remain **consistent across frontend, REST, and GraphQL**.
- `CouponManagementInterface::set` is invoked in `frontend`, `webapi_rest`, and `graphql` areas.
- A global plugin covers all three without duplication.
- **Admin safety**: Admin order creation uses `Magento\Sales\Model\AdminOrder\Create` which does NOT go through `CouponManagementInterface::set` for coupon application — it uses a different code path. Therefore the plugin will not fire during admin operations.

If during testing admin area interference is detected, the fallback is to register three area-specific `di.xml` files (`frontend/`, `webapi_rest/`, `graphql/`).

---

## 7. Constraint Compliance Matrix

| Constraint | How Satisfied |
|---|---|
| No core file modifications | Plugin-only approach; all code in `Custom/CouponValidation` |
| No rule duplication/replacement | TEST10 and VIP-TEST20 remain untouched in admin |
| Runtime enforcement only | Plugin executes at request time; no config/rule changes |
| No sales rule condition modification | Validation is external to rule conditions |
| VIP validation before discount calc | `beforeSet` plugin runs before Magento processes the coupon |
| No discount recalculation | Plugin never touches totals; only gates entry to core |
| No totals collector override | No custom collector registered |
| No class preference overrides | Plugin interception only (no `<preference>`) except for VipValidatorInterface binding |
| No recursive execution | `beforeSet` does not call `set()` |
| No stop-rules interference | Plugin is pre-validation only; never touches rule execution |
| No JS checkout hacks | Server-side only |
| No direct DB queries in logic | Uses service contracts (`CartRepositoryInterface`) |
| Declarative schema if needed | No custom tables required by current scope |
| No extra quote recollection | Plugin reads quote state; does not trigger `collectTotals()` |
| No excessive success-case logging | Only logs warnings and failures |
| Passes setup:upgrade, di:compile, cache:flush | Standard module structure |

---

## 8. Rule Integrity Preservation

- **TEST10**: Continues to work for all customer groups at the Magento rule level. The plugin adds a runtime gate: group ID 3 is rejected before the rule is ever evaluated. For groups 0, 1, 2 — behavior is completely unchanged.
- **VIP-TEST20**: Continues to exist as an active rule with no conditions. The plugin intercepts `VIP-` prefixed codes and runs custom validation first. On success, Magento evaluates and applies the rule normally. On failure, the coupon is rejected before rule evaluation.
- **Neither rule's admin configuration is modified** — sort order, customer groups, conditions, activation status all remain as-is.

---

## 9. VIP Validation Integration Detail

```
Request: set(cartId, "VIP-TEST20")
         │
         ▼
    beforeSet plugin
         │
    ┌────┴────┐
    │ Prefix  │
    │ VIP-?   │──── No ──→ Skip VIP check, continue to other checks
    └────┬────┘
         │ Yes
         ▼
    VipValidator::validate("VIP-TEST20", $quote)
         │
    ┌────┴────┐
    │  Pass?  │──── Yes ──→ Return from plugin; Magento core applies rule normally
    └────┬────┘
         │ No
         ▼
    Log failure → Throw CouldNotSaveException
    (Coupon never reaches Magento's rule engine)
```

**Key guarantees**:
- VIP validation is **separate** from Magento's sales rule validator.
- On pass: zero modification to Magento's discount pipeline — the native `SalesRule` module handles everything.
- On fail: coupon is blocked via exception before `CouponManagementInterface::set` executes.
- No rule replacement, no totals recalculation, no bypass of the rule evaluation engine.

---

## 10. Logging Strategy

### What is logged

| Event | Logged? | Level |
|---|---|---|
| High-value cart warning triggered | ✅ Yes | INFO |
| VIP validation failure | ✅ Yes | WARNING |
| Customer group rejection | ❌ No (not required by spec) | — |
| Normal coupon success | ❌ No (performance constraint) | — |

### Log entry structure

Every log entry is a JSON object containing exactly the six required fields:

- `quote_id` — `$quote->getId()`
- `customer_group_id` — `$quote->getCustomerGroupId()`
- `subtotal` — `$quote->getSubtotal()`
- `item_count` — `$quote->getItemsCount()` (visible items)
- `coupon_code` — the coupon code being applied
- `validation_outcome` — one of: `high_value_cart_warning`, `vip_validation_failed`

### Log destination

`var/log/custom_coupon_validation.log` — dedicated file, no pollution of system.log or exception.log.

---

## 11. Frontend, REST, and GraphQL Behavior

### Frontend Checkout

- Customer applies coupon via checkout coupon form.
- Plugin intercepts via `CouponManagementInterface::set`.
- Warning messages appear as Magento notice messages in checkout.
- Error messages cause coupon application to fail with user-facing message.

### REST API

- `PUT /V1/carts/:cartId/coupons/:couponCode` → same service contract.
- Plugin fires identically.
- `CouldNotSaveException` → HTTP 400 with error message in JSON response body.
- Warning messages are available in the response message pool.

### GraphQL

- `applyCouponToCart` mutation → resolves through same service contract.
- Plugin fires identically.
- `CouldNotSaveException` → GraphQL error response.
- Warning messages available through standard Magento message mechanism.

### Admin

- Admin order creation uses a different internal path (`Magento\Sales\Model\AdminOrder\Create::applyCoupon`).
- The plugin on `CouponManagementInterface::set` does NOT fire.
- Admin behavior remains completely unaffected.

---

## 12. Risks & Mitigations

| Risk | Likelihood | Mitigation |
|---|---|---|
| Plugin fires in admin area | Low | `CouponManagementInterface::set` is not used in admin order flow; verified by tracing admin coupon path |
| Quote not fully loaded when plugin runs | Low | Use `CartRepositoryInterface::getActive()` which loads full quote with items |
| `getItemsCount()` vs `getItemsQty()` confusion | Medium | Use `getItemsCount()` for distinct visible line items; verify against "more than 3 total visible items" requirement |
| Subtotal not yet calculated at `beforeSet` time | Low | Subtotal is set during `collectTotals()` which runs before coupon application in checkout flow; but verify in REST/GraphQL paths |
| VIP validator default implementation too trivial | Low | Interface contract allows swapping in real validation later; current implementation proves the hook works |
| Message display issues in REST/GraphQL for warnings | Medium | Warning is non-blocking and informational; REST/GraphQL may not surface notice messages to end users — acceptable per spec since coupon still applies |
| Exception type mismatch in GraphQL | Low | `CouldNotSaveException` is handled by Magento's GraphQL error formatting |

---

## 13. Edge Cases

1. **Empty coupon code** — Magento core validates this before our plugin; no action needed.
2. **Guest customer (group 0)** — Not group 3; passes group check; normal flow.
3. **Customer group 3 with VIP coupon** — Group check runs first; rejected before VIP check.
4. **VIP coupon on high-value cart with group 1** — VIP validation runs; if passes, high-value warning also triggers; both log entries written.
5. **Non-VIP coupon on high-value cart** — Warning triggers, coupon applies normally.
6. **Cart with exactly 3 items and $500 subtotal** — Does NOT trigger warning (requires MORE than 3 AND exceeds $500).
7. **Cart with 4 items and $499 subtotal** — Does NOT trigger (subtotal must exceed $500).
8. **Multiple coupon applications in same session** — Each call is independent; no state leakage.
9. **Coupon removal** — Plugin is on `set`, not `remove`; removal is unaffected.

---

## 14. Test Scenarios

### Unit Tests

**`CouponApplyValidationPluginTest`**:
- Test: Customer group 3 → `CouldNotSaveException` with correct message
- Test: Customer group 1 → no exception
- Test: VIP coupon + validation fail → `CouldNotSaveException`
- Test: VIP coupon + validation pass → no exception
- Test: High-value cart (>3 items, >$500) → notice message added, log written
- Test: Cart with 3 items, $600 → no warning (not MORE than 3)
- Test: Cart with 4 items, $500 → no warning (not EXCEEDS $500)
- Test: Non-VIP coupon → VIP validator not invoked
- Test: Group 3 + VIP coupon → rejected at group check, VIP validator not invoked

**`VipValidatorTest`**:
- Test: Valid VIP coupon → returns true
- Test: Invalid VIP coupon → returns false

### Integration Tests (Manual / Future Automation)

- Apply TEST10 as Customer A (group 1) → coupon applies, $10 discount
- Apply TEST10 as Customer B (group 3) → coupon rejected, no discount
- Apply VIP-TEST20 as Customer A → VIP validation runs, coupon applies if valid
- Apply coupon with high-value cart → warning message displayed, coupon still applies
- Apply coupon via REST API → identical behavior
- Apply coupon via GraphQL → identical behavior
- Create admin order with TEST10 → coupon applies normally (no plugin interference)
- Verify `var/log/custom_coupon_validation.log` contains correct entries after warning/VIP failure

---

## 15. Validation Execution Order

Within the `beforeSet` plugin, checks execute in this order:

1. **Customer Group Restriction** (hard block — cheapest check, no I/O)
2. **VIP Validation** (hard block — only for VIP-prefixed codes)
3. **High-Value Cart Warning** (soft warning — non-blocking)

This ordering ensures:
- Rejected customers never reach VIP validation (unnecessary work avoided)
- VIP failures are caught before any warning logic
- Warnings only fire for coupons that will actually be applied

---

## 16. Dependencies

- `Magento_Quote` — `CartRepositoryInterface`, `CouponManagementInterface`
- `Magento_SalesRule` — indirect (rules are evaluated by core after plugin passes)
- `Magento_Framework` — Logger, MessageManager, Exception types

No third-party dependencies. No custom database tables required.

---

## 17. Deployment Steps

```bash
bin/magento module:enable Custom_CouponValidation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

---

*This plan is Phase 1 only. No code will be written until this plan is approved.*
