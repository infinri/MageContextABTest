# Technical & Architectural Constraints

## Core Integrity

- No Magento core file modifications.
- No editing or replacing existing sales rule configuration.
- Existing rules TEST10 and VIP-TEST20 must remain unchanged in admin.
- No rule duplication or rule replacement.

---

## Runtime Enforcement

- All additional validation must be enforced at runtime.
- The solution must not rely on modifying:
    - Customer group assignments
    - Sales rule conditions
    - Rule activation settings
    - Sort order settings

- Runtime enforcement must be isolated to validation logic only.
- Discount calculation logic must remain entirely owned by Magento core.

---

## VIP Validation Integration (Critical)

- VIP validation must execute before discount calculation is finalized.
- If VIP validation passes:
    - Magento’s native sales rule processing must continue unmodified.
    - Discount computation must not be reimplemented or duplicated.
- If VIP validation fails:
    - Coupon must be rejected without affecting unrelated rule processing.
- The implementation must not:
    - Replace the sales rule validator
    - Recalculate totals manually
    - Bypass Magento’s rule evaluation engine

---

## Interception & Extension

- No full class preference overrides unless absolutely unavoidable.
- If interception is used, it must not:
    - Break existing validation flow
    - Cause recursive execution
    - Interfere with stop rule processing behavior
    - Alter behavior for unrelated coupons
    - Change behavior of non-VIP coupons beyond defined requirements

- Custom validation must integrate cleanly into existing sales rule processing.

---

## Area Isolation

The implementation must:

- Apply only to frontend coupon application behavior.
- Not break:
    - Admin order creation
    - REST API coupon application
    - GraphQL coupon application

Behavior must remain consistent across all supported entry points.

---

## Architectural Discipline

- No JavaScript checkout hacks.
- No direct database queries inside business logic.
- Use Magento service contracts where applicable.
- Use declarative schema for custom tables.
- Do not duplicate core validation logic.
- Do not reimplement sales rule calculation.
- Do not override core totals collectors.

---

## Performance Constraints

- No additional full quote recollection cycles beyond Magento’s normal flow.
- No unnecessary repeated total recalculation.
- No excessive logging during normal coupon success cases.

---

## Code Quality

- Must pass:
    - bin/magento setup:upgrade
    - bin/magento setup:di:compile
    - bin/magento cache:flush
- Dependency injection configuration must be valid.
- Module must register cleanly.

---

## Structural Safety

The implementation must not:

- Introduce circular dependencies.
- Introduce global state.
- Create hidden coupling to unrelated modules.
- Break plugin execution ordering for existing interceptors.
- Create architectural debt through improper layer violations.
