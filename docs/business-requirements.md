# Coupon Validation & High-Value Cart Behavior

## Objective

Enhance coupon validation behavior during frontend checkout to introduce additional business rules without modifying Magento core code.

---

## Functional Requirements

### 1. High-Value Cart Warning

When a customer applies a coupon during frontend checkout:

If:
- Cart contains more than 3 total visible items
  AND
- Cart subtotal exceeds $500

Then:
- Display a non-blocking warning message:

  "High value cart – coupon restrictions may apply."

The coupon should still be allowed to apply (unless restricted by other rules).

---

### 2. Customer Group Restriction

When a customer applies a coupon during frontend checkout:

If:
- Customer belongs to Customer Group ID = 3

Then:
- Prevent the coupon from being applied
- Display an error message:

  "This coupon is not valid for your customer group."

The coupon must not modify totals in this case.

---

### 3. VIP Coupon Codes

If a coupon code begins with the prefix:

VIP-

Then:

- Validate the coupon against a custom validation mechanism.
- If validation fails, prevent coupon application.
- If validation succeeds, allow normal coupon processing.

VIP validation must be handled separately from standard Magento sales rule conditions.

---

### 4. Logging

When either:

- A high-value cart warning is triggered
  OR
- A VIP validation failure occurs

Then:

- Log a structured entry including:
    - Quote ID
    - Customer Group ID
    - Subtotal
    - Item count
    - Coupon code
    - Validation outcome

Logs must be written to a dedicated custom log file.

---

## Non-Functional Requirements

- Solution must be implemented in a custom module.
- No Magento core files may be modified.
- The implementation must integrate cleanly with Magento’s existing sales rule system.
- The solution must not introduce regressions in standard coupon functionality.
- The solution must remain compatible with REST and GraphQL coupon application.
