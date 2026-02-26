# Environment Definition

## Magento Version

Magento Open Source 2.4.8-p3

---

## Installation State

- Clean vanilla Magento installation
- Sample data installed
- Default Luma frontend
- No third-party extensions installed
- No custom modules installed

---

## Customers

### Customer A (Normal Case)
- Email: customer.a@example.com
- Name: Test Normal
- Customer Group: General (ID 1)
- Password: Test1234!
- Purpose: Standard coupon application testing

### Customer B (Rejection Case)
- Email: customer.b@example.com
- Name: Test Rejected
- Customer Group: Retailer (ID 3)
- Password: Test1234!
- Purpose: Customer group rejection logic testing

---

## Sales Rules

### Coupon 1: TEST10

- Code: TEST10
- Rule Name: Test $10 Off Coupon
- Discount Type: Fixed amount off cart
- Discount Amount: $10.00
- Active: Yes
- Customer Groups:
    - NOT LOGGED IN (0)
    - General (1)
    - Wholesale (2)
    - Retailer (3)
- Restrictions: None
- Usage Limits: Unlimited

Important:
- TEST10 currently applies successfully to all customer groups.
- No built-in rule conditions restrict Customer Group ID 3.
- The implementation must introduce additional runtime validation without modifying this rule configuration.

---

### Coupon 2: VIP-TEST20

- Code: VIP-TEST20
- Rule Name: VIP 20% Off Coupon
- Description: VIP 20% discount for testing — requires VIP validation
- Discount Type: Percent off cart
- Discount Amount: 20%
- Coupon Type: Specific coupon
- Active: Yes
- Customer Groups:
    - NOT LOGGED IN (0)
    - General (1)
    - Wholesale (2)
    - Retailer (3)
- Website IDs: [1]
- Stop Rules Processing: No
- Advanced: Yes
- Sort Order: 0
- Apply to Shipping: No
- Uses Per Customer: Unlimited
- Uses Per Coupon: Unlimited
- No rule conditions configured.

Important:
- VIP-TEST20 currently applies successfully to all customer groups.
- There is NO built-in VIP validation logic.
- The implementation must introduce a custom VIP validation mechanism.
- The sales rule configuration itself must not be modified.

---

## Critical Rule Integrity Requirement

The existing TEST10 and VIP-TEST20 sales rule configurations:

- Must not be modified.
- Must not be restricted via admin configuration.
- Must not be replaced.
- Must remain usable for normal processing when custom validation passes.

All additional behavior must be enforced at runtime.

---

## APIs

Coupon application must function correctly in:

- Frontend checkout
- REST API
- GraphQL API

Behavior must remain consistent across all entry points.

---

## Logging

Custom logs must be written to:

var/log/custom_coupon_validation.log

Each log entry must include:

- Quote ID
- Customer Group ID
- Subtotal
- Item count
- Coupon code
- Validation outcome

---

## Database

If custom data storage is required:

- Use declarative schema.
- Do not use raw SQL inside business logic.

---

## Deployment

The environment supports:

- bin/magento setup:upgrade
- bin/magento setup:di:compile
- bin/magento cache:flush
