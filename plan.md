# Implementation Plan — Custom Coupon Validation Module

## 1. Module Identity

- **Vendor/Module**: `Custom_CouponValidation`
- **Path**: `app/code/Custom/CouponValidation`
- **Magento**: Open Source 2.4.8-p3
- **Sequence dependencies**: `Magento_SalesRule`, `Magento_Quote`, `Magento_Customer`

---

## 2. System Areas Affected

| Area | Impact |
|---|---|
| **Coupon application flow** | Intercepted via plugin on `Magento\SalesRule\Model\CouponManagement` (or the service contract `Magento\Quote\Api\CouponManagementInterface`) to inject runtime validation before the coupon is set on the quote. |
| **Quote totals** | No changes — discount calculation remains entirely owned by Magento core. |
| **Sales rule configuration** | No changes — TEST10 and VIP-TEST20 rules remain untouched in admin. |
| **Customer groups** | Read-only — group ID is inspected but never modified. |
| **Frontend messaging** | Warning/error messages are added to the Magento message manager during coupon application. |
| **Logging** | New dedicated log handler writing to `var/log/custom_coupon_validation.log`. |

---

## 3. Architectural Approach

### 3.1 Interception Point

A **before-plugin** on `Magento\Quote\Api\CouponManagementInterface::set` is the cleanest interception point.

- This is the service contract used by **frontend**, **REST**, and **GraphQL** coupon application.
- A `beforeSet` plugin receives `$cartId` and `$couponCode` before the coupon is applied.
- On validation failure, the plugin throws `\Magento\Framework\Exception\CouldNotSaveException` (or `NoSuchEntityException`), preventing the coupon from being set — this is the same exception type the core method throws, so all consumers (frontend, REST, GraphQL) already handle it gracefully.
- On validation success, the plugin returns nothing (passes through), letting Magento's native flow proceed unmodified.

**Why not an after-plugin or observer?**
- An after-plugin would fire *after* the coupon is already set — too late to prevent it without triggering a second quote recollection.
- An observer on `sales_quote_collect_totals_before` would execute on every totals recollection, not just coupon application — violating performance constraints.

### 3.2 Validation Execution Order

Within the `beforeSet` plugin, validations execute in this order:

1. **Customer Group Restriction** (hard block — cheapest check, runs first)
2. **VIP Coupon Validation** (hard block for `VIP-` prefixed codes)
3. **High-Value Cart Warning** (non-blocking — always proceeds)

This ordering ensures the cheapest rejection happens first (fail-fast), and the non-blocking warning only fires after all hard validations pass.

### 3.3 VIP Validation Integration

- A dedicated `VipValidatorInterface` defines the contract:
  ```
  public function validate(string $couponCode, CartInterface $quote): bool;
  ```
- A concrete `VipValidator` implementation encapsulates the custom VIP validation logic.
- The validator is injected into the plugin via constructor DI (interface-bound in `di.xml`).
- The plugin calls the validator **only** for coupon codes starting with `VIP-`.
- If validation **passes**: plugin returns `null` → Magento's native `set()` executes normally → core handles discount calculation.
- If validation **fails**: plugin throws `CouldNotSaveException` → coupon is never set → no discount calculation occurs.

This design:
- Does **not** replace the sales rule validator.
- Does **not** recalculate totals.
- Does **not** bypass Magento's rule evaluation engine.
- Executes **before** discount calculation is finalized (per constraint).

---

## 4. Module Structure

```
app/code/Custom/CouponValidation/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── di.xml
│   └── frontend/
│       └── di.xml          (if area-specific config is needed)
├── Api/
│   └── VipValidatorInterface.php
├── Model/
│   └── VipValidator.php
├── Plugin/
│   └── CouponApplyValidationPlugin.php
├── Logger/
│   ├── Handler.php
│   └── Logger.php
└── Test/
    └── Unit/
        ├── Plugin/
        │   └── CouponApplyValidationPluginTest.php
        └── Model/
            └── VipValidatorTest.php
```

---

## 5. Component Details

### 5.1 `CouponApplyValidationPlugin`

- **Type**: Before-plugin on `Magento\Quote\Api\CouponManagementInterface::set`
- **Injected dependencies** (all via constructor):
  - `Magento\Quote\Api\CartRepositoryInterface` — to load the quote by `$cartId`
  - `Api\VipValidatorInterface` — for VIP coupon validation
  - `Logger\Logger` — custom logger
  - `Magento\Framework\Message\ManagerInterface` — to add warning messages
- **Method**: `beforeSet(CouponManagementInterface $subject, $cartId, $couponCode)`
- **Logic**:
  1. Load quote via `CartRepositoryInterface::getActive($cartId)`.
  2. Get customer group ID from quote.
  3. **Customer Group Check**: If group ID === 3 → throw `CouldNotSaveException` with message "This coupon is not valid for your customer group."
  4. **VIP Check**: If `$couponCode` starts with `VIP-` → call `VipValidatorInterface::validate()`. If returns `false` → log failure → throw `CouldNotSaveException`.
  5. **High-Value Cart Warning**: If visible item count > 3 AND subtotal > 500 → add warning message via MessageManager → log the event.
  6. Return `null` (let original method proceed).

### 5.2 `VipValidatorInterface` / `VipValidator`

- Interface in `Api/` with a single method: `validate(string $couponCode, CartInterface $quote): bool`.
- Concrete implementation in `Model/VipValidator.php`.
- For the initial implementation, the validator will contain a stub validation mechanism (e.g., verifying the coupon code format and basic criteria). This is designed so the actual VIP validation logic can be swapped out later by rebinding the interface in `di.xml`.
- The interface-based approach satisfies ARCH-DI-001 (depend on abstractions) and ARCH-EXT-001 (extensible without core modification).

### 5.3 `Logger\Handler` / `Logger\Logger`

- `Handler` extends `Magento\Framework\Logger\Handler\Base`, writing to `var/log/custom_coupon_validation.log`.
- `Logger` extends `Monolog\Logger` (standard Magento custom logger pattern).
- Configured via `di.xml` virtual type or explicit class binding.

### 5.4 DI Configuration (`di.xml`)

```xml
<!-- Interface preference -->
<preference for="Custom\CouponValidation\Api\VipValidatorInterface"
            type="Custom\CouponValidation\Model\VipValidator" />

<!-- Plugin declaration -->
<type name="Magento\Quote\Api\CouponManagementInterface">
    <plugin name="custom_coupon_apply_validation"
            type="Custom\CouponValidation\Plugin\CouponApplyValidationPlugin"
            sortOrder="10" />
</type>

<!-- Logger configuration -->
<type name="Custom\CouponValidation\Logger\Handler">
    <arguments>
        <argument name="fileName" xsi:type="string">/var/log/custom_coupon_validation.log</argument>
    </arguments>
</type>
<type name="Custom\CouponValidation\Logger\Logger">
    <arguments>
        <argument name="name" xsi:type="string">custom_coupon_validation</argument>
        <argument name="handlers" xsi:type="array">
            <item name="system" xsi:type="object">Custom\CouponValidation\Logger\Handler</item>
        </argument>
    </arguments>
</type>
```

The plugin is registered globally (not area-restricted) because the service contract `CouponManagementInterface::set` is the unified entry point for frontend, REST, and GraphQL. Area isolation is enforced by the validation logic itself being appropriate for all entry points, as required by the constraints ("Behavior must remain consistent across all supported entry points").

---

## 6. Rule Integrity Preservation

| Concern | How it's preserved |
|---|---|
| **TEST10 rule config** | Never touched. Plugin only inspects coupon code and quote data at runtime. |
| **VIP-TEST20 rule config** | Never touched. VIP validation is a runtime gate *before* the coupon is set on the quote. If validation passes, the rule applies normally via Magento core. |
| **Discount calculation** | Entirely owned by Magento core. Plugin never touches totals, collectors, or rule evaluation. |
| **Stop rules processing** | Unaffected — plugin operates before `set()`, not during totals collection. |
| **Admin behavior** | Admin uses `Magento\Quote\Api\CouponManagementInterface` for backend order creation, but the plugin's customer group check applies only to group ID 3 (which is a valid runtime check in all contexts per "consistent across all entry points"). |

---

## 7. Frontend, REST, GraphQL & Admin Behavior

### Frontend (Luma checkout)
- Coupon is applied via AJAX call to `CouponManagementInterface::set`.
- Plugin intercepts → validates → throws exception or adds warning message.
- Exception message is displayed to customer via the standard error response.
- Warning message is added via `MessageManager` and displayed on the next page render / AJAX response.

### REST API
- `POST /V1/carts/:cartId/coupons/:couponCode` routes to `CouponManagementInterface::set`.
- Plugin intercepts identically.
- `CouldNotSaveException` returns HTTP 400 with error message in JSON body.

### GraphQL
- `applyCouponToCart` mutation resolves through `CouponManagementInterface::set`.
- Plugin intercepts identically.
- Exception propagates as a GraphQL error.

### Admin
- Admin order creation uses different code paths (`Magento\Sales\Model\AdminOrder\Create`), which does **not** go through `CouponManagementInterface::set` — so the plugin does not fire during admin order creation. Admin behavior is unaffected.

---

## 8. Logging Strategy

### When to log
- **High-value cart warning triggered** → log with outcome `"high_value_cart_warning"`
- **VIP validation failure** → log with outcome `"vip_validation_failed"`
- Normal success cases are **not** logged (per performance constraint: "No excessive logging during normal coupon success cases").

### Log format
Each entry includes:
```
[datetime] custom_coupon_validation.INFO: Coupon validation event {
    "quote_id": 42,
    "customer_group_id": 1,
    "subtotal": 650.00,
    "item_count": 5,
    "coupon_code": "VIP-TEST20",
    "validation_outcome": "vip_validation_failed"
}
```

### Log destination
`var/log/custom_coupon_validation.log` via custom Monolog handler.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|---|---|
| **Plugin breaks existing coupon flow** | Plugin only throws exceptions or adds messages — never modifies the quote directly. If the plugin itself errors, `\Throwable` is caught and logged, then re-thrown (per PHP-ERR-002). |
| **Performance overhead** | Single `CartRepositoryInterface::getActive()` call per coupon application (quote is likely already loaded). No extra totals recollection. |
| **MessageManager warnings not visible in REST/GraphQL** | `MessageManager` is session-based and primarily affects frontend. For REST/GraphQL, the warning could alternatively be returned as a custom response extension. However, the requirement specifies warnings during "frontend checkout" — so `MessageManager` is the correct channel. REST/GraphQL will still get the warning if they consume messages. |
| **Admin coupon application affected** | Admin uses different code paths that don't route through `CouponManagementInterface::set` — no impact. Verified by Magento admin order creation flow. |
| **VIP validation logic is a stub** | Interface-based design allows swapping the implementation later without changing the plugin. Initial stub provides a clear contract. |
| **Customer Group ID 3 hardcoded** | Could be made configurable via admin system config in the future, but the requirement is explicit about group ID 3. Constant extraction keeps it maintainable. |

---

## 10. Edge Cases

1. **Guest customer applies coupon** — Guest has group ID 0 (NOT LOGGED IN). Not group 3 → passes group check. VIP/high-value checks apply normally.
2. **VIP- coupon used by group 3 customer** — Group check fires first (fail-fast) → rejected before VIP validation runs.
3. **Empty coupon code** — Magento core validates this before the service method is called. Plugin never sees it.
4. **Coupon removal** — `CouponManagementInterface::remove` is a separate method. Plugin only intercepts `set` — removal is unaffected.
5. **Multiple coupons** — Magento Open Source supports one coupon per quote. The plugin handles the single-coupon flow.
6. **Subtotal exactly $500** — Requirement says "exceeds $500" → strictly greater than. `$subtotal > 500`, not `>=`.
7. **Exactly 3 items** — Requirement says "more than 3" → strictly greater than. `$itemCount > 3`, not `>=`.
8. **Case sensitivity of VIP- prefix** — Coupon codes in Magento are case-sensitive by default. `VIP-` check uses case-sensitive `str_starts_with()`.
9. **Quote has no items** — Item count 0 and subtotal 0 → high-value check doesn't trigger. Other validations proceed normally.

---

## 11. Test Scenarios

### Unit Tests

#### `CouponApplyValidationPluginTest`
| Test | Input | Expected |
|---|---|---|
| Customer group 3 is rejected | Group ID = 3, any coupon | `CouldNotSaveException` with group message |
| Customer group 1 passes group check | Group ID = 1 | No exception from group check |
| VIP coupon fails validation | Code = `VIP-TEST20`, validator returns `false` | `CouldNotSaveException` |
| VIP coupon passes validation | Code = `VIP-TEST20`, validator returns `true` | No exception |
| Non-VIP coupon skips VIP validation | Code = `TEST10` | VIP validator never called |
| High-value cart triggers warning | Items > 3 AND subtotal > 500 | Warning message added, no exception |
| Low-value cart no warning | Items = 2, subtotal = 100 | No warning message |
| High items but low subtotal no warning | Items = 5, subtotal = 100 | No warning message |
| High subtotal but few items no warning | Items = 2, subtotal = 600 | No warning message |

#### `VipValidatorTest`
| Test | Input | Expected |
|---|---|---|
| Valid VIP code passes | Valid VIP code + quote | Returns `true` |
| Invalid VIP code fails | Invalid VIP code + quote | Returns `false` |

### Integration Test Scenarios (manual or future automated)
| Scenario | Entry Point | Expected |
|---|---|---|
| Customer A applies TEST10 | Frontend | Coupon applied, $10 discount |
| Customer B applies TEST10 | Frontend | Rejected: "not valid for your customer group" |
| Customer A applies VIP-TEST20 (valid) | Frontend | Coupon applied, 20% discount |
| Customer A applies VIP-TEST20 (invalid) | Frontend | Rejected |
| High-value cart + valid coupon | Frontend | Warning shown, coupon applied |
| Customer A applies TEST10 via REST | REST API | Coupon applied, $10 discount |
| Customer B applies TEST10 via REST | REST API | HTTP 400, group rejection message |
| Admin applies TEST10 for group 3 customer | Admin | Coupon applied (plugin doesn't intercept admin) |

---

## 12. Implementation Sequence

1. Module skeleton: `registration.php`, `etc/module.xml`
2. Logger: `Logger/Handler.php`, `Logger/Logger.php`
3. VIP validator interface and implementation: `Api/VipValidatorInterface.php`, `Model/VipValidator.php`
4. Plugin: `Plugin/CouponApplyValidationPlugin.php`
5. DI configuration: `etc/di.xml`
6. Unit tests: `Test/Unit/Plugin/CouponApplyValidationPluginTest.php`, `Test/Unit/Model/VipValidatorTest.php`
7. Verification: `setup:upgrade`, `setup:di:compile`, `cache:flush`
