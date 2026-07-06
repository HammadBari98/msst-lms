# Lunch Fee Option — Design

## Problem

Different students pay different lunch amounts per month. Today, admin works around this by maintaining several separate fixed-price "Lunch" fee components (PKR 600, 1000, 1500) in `fee_components`, picking whichever one matches a given student when generating their slip. This doesn't scale to arbitrary per-student amounts and clutters the fee component list.

## Goals

- Let admin enter a free-form lunch amount per student when generating a fee slip (single-student flow), instead of picking from fixed presets.
- Let admin enter one shared lunch amount when bulk-generating slips for a whole class, applied identically to every student in that batch.
- Retire the 3 duplicate preset "Lunch" components without breaking the 10 existing historical fee slips that already reference them.

## Non-goals

- No per-student saved/default lunch amount — admin re-enters it each time a slip is generated.
- No ability to add or edit a lunch fee on an already-generated slip — this codebase has no "edit slip after generation" feature for any component, and this doesn't introduce one.
- No month-multiplication of the lunch amount — when multi-month generation is used, admin enters the total lunch charge for the whole slip once; it is not multiplied by the month count (unlike Base Monthly Tuition and other recurring components).

## Data model

One new lazily-created `fee_components` row, following the exact find-or-insert pattern already used for `Installment Charges` and `Previous Arrears & Late Fee`:

```sql
INSERT INTO fee_components (name, amount, type, description, is_optional, is_active)
VALUES ('Lunch Fee', 0, 'recurring', 'Custom per-student lunch charge, entered at slip generation time', 1, 1)
```

The `amount` column is a placeholder (0) — the real amount is always supplied per-generation via the form, exactly like `Installment Charges`.

**One-time data migration:** deactivate the 3 existing duplicate presets (`fee_components` rows named `Lunch` / `Lunch ` — ids 7, 8, 9 in the current data, but matched by name at migration time, not hardcoded ids) via:

```sql
UPDATE fee_components SET is_active = 0 WHERE TRIM(name) = 'Lunch' AND is_active = 1
```

This is idempotent (safe to run on every page load, matching the auto-patcher convention) — after the first run these rows are already `is_active = 0`, so it's a no-op thereafter. The 10 existing `fee_slip_components` rows referencing these ids are untouched (that table stores a snapshot of `component_name`/`amount` at generation time, not a live foreign-key dependency), so historical slips continue to display exactly as before.

## Single-student generation form (`generate_slip_for_student` handler + its modal)

**UI:** add an "Enable Lunch Fee" switch to the existing switches row (next to "Multi-Month Generation" and "Enable Installments"), matching their exact markup (`form-check form-switch`). When checked, reveal a `PKR` amount input (matching the existing `installment-fields-single` row's styling/show-hide pattern).

**JS:** the existing `refreshTotal()` function (which already sums `.optional-fee-cb:checked` amounts into the live total) gains one more line: if the lunch switch is checked, add the entered amount into `currentTotal` before dividing by installments.

**Backend:** after building `$component_data`/`$total_fee` (same point where `Previous Arrears & Late Fee` and `Installment Charges` are conditionally appended), add:

```php
$enable_lunch_fee = isset($_POST['enable_lunch_fee']);
$lunch_fee_amount = isset($_POST['lunch_fee_amount']) ? floatval($_POST['lunch_fee_amount']) : 0;
if ($enable_lunch_fee && $lunch_fee_amount > 0) {
    $stmt_lunch = $pdo->prepare("SELECT id FROM fee_components WHERE name = 'Lunch Fee' LIMIT 1");
    $stmt_lunch->execute();
    $lunch_comp_id = $stmt_lunch->fetchColumn();
    if (!$lunch_comp_id) {
        $pdo->exec("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES ('Lunch Fee', 0, 'recurring', 'Custom per-student lunch charge, entered at slip generation time', 1, 1)");
        $lunch_comp_id = $pdo->lastInsertId();
    }
    $total_fee += $lunch_fee_amount;
    $component_data[] = ['id' => $lunch_comp_id, 'name' => 'Lunch Fee', 'amount' => $lunch_fee_amount, 'type' => 'recurring'];
}
```

No month-multiplication is applied to `$lunch_fee_amount` (unlike tuition/other recurring components) — the entered value is used exactly as-is, per the goals above. It still flows through the existing installment-splitting (`$comp['amount'] / $installments`) automatically since it's just another entry in `$component_data`.

## Class bulk generation form (`generate_slips_for_class` handler + its modal)

**UI:** add the identical "Enable Lunch Fee" switch + amount input to the "Generate Fee Slips for Class" modal (next to "Enable Installment Plan"). One shared amount field — no live total preview exists in this modal today (it doesn't show a running total for bulk generation), so none is added.

**Backend:** identical logic to the single-student handler above, inserted at the equivalent point in the `foreach ($class_students as $student)` loop (same place `Previous Arrears & Late Fee` and `Installment Charges` are conditionally appended), reading the same `enable_lunch_fee`/`lunch_fee_amount` POST fields, applied identically to every student in the loop (same amount, no per-student variation in this bulk path — that's what "shared" means here).

## Testing plan

No automated test framework exists in this codebase — verification is manual (curl + direct MySQL queries against real `msst_db` data), consistent with every other feature built in this project. Specifically:

- Generate a single-student slip with lunch fee enabled at a specific amount; confirm `fee_slip_components` has a `Lunch Fee` row with that exact amount, and `fee_slips.amount` includes it in the total.
- Generate the same for a multi-month slip; confirm the lunch amount is NOT multiplied by the month count (appears once, as entered).
- Generate with installments enabled; confirm the lunch amount is divided across installment slips like every other component.
- Bulk-generate for a class with 2+ students and lunch fee enabled; confirm every student's generated slip has an identical `Lunch Fee` line-item amount.
- Confirm the 3 old preset `Lunch`/`Lunch ` components are `is_active = 0` after visiting the page, and no longer appear in the optional-components picker, while any pre-existing fee slip that already used them still displays correctly (query `fee_slip_components` for a historical slip referencing id 7, 8, or 9 and confirm it's unchanged).
