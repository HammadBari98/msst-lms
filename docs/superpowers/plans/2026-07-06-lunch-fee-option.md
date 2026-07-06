# Lunch Fee Option Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admin enter a free-form lunch amount per student when generating a fee slip (single-student flow), and one shared lunch amount when bulk-generating for a whole class — replacing the current workaround of picking from 3 fixed-price "Lunch" presets.

**Architecture:** One new lazily-created `fee_components` row (`Lunch Fee`, `is_optional=1`), following the exact find-or-insert pattern this file already uses for `Installment Charges`/`Previous Arrears & Late Fee`. Both existing fee-slip-generation POST handlers in `fee-management.php` (`generate_slip_for_student` and `generate_slips_for_class`) gain identical "if enabled, add this flat amount" logic at the same point they already conditionally add those other components. The 3 duplicate preset `Lunch` components get soft-deactivated via an idempotent one-time `UPDATE`, matching the existing auto-patcher convention already at the top of this file.

**Tech Stack:** PHP 8 / PDO / MySQL (MariaDB via XAMPP), no ORM, no automated test framework — verification is manual (curl against XAMPP Apache + direct MySQL queries), same as the rest of this codebase.

## Global Constraints

- **No month-multiplication:** the lunch amount is used exactly as entered — never multiplied by `$multi_months` (unlike Base Monthly Tuition and other recurring components). Admin enters the total lunch charge for however many months the slip covers, once.
- **Installment-splitting still applies automatically:** because the lunch amount is just another entry pushed into `$component_data`, the existing `$comp['amount'] / $installments` division (already applied to every component when installments > 1) divides it too — no special-casing needed anywhere for that.
- **Soft-deactivation, not deletion:** the 3 existing `fee_components` rows named `Lunch`/`Lunch ` (referenced by 10 real historical `fee_slip_components` rows) must be updated to `is_active = 0`, never deleted — `fee_slip_components` stores a snapshot of `component_name`/`amount`, not a live foreign key, so historical slips are unaffected either way, but deleting the component row would be an unnecessary destructive action on data this plan doesn't need to touch.
- **Bulk generation gets ONE shared amount** applied identically to every student in that class batch — this is a deliberate, explicit exception to today's rule that bulk generation skips all optional components (every other optional component still stays mandatory-only in bulk mode; only Lunch Fee is added there, per explicit product decision).
- **Reuse existing UI conventions exactly:** the "Enable Lunch Fee" switches use the same `form-check form-switch` markup as the neighboring "Enable Installments"/"Enable Installment Plan" switches in the same forms — no new CSS classes, no new JS patterns beyond what `refreshTotal()` already does for `.optional-fee-cb`.

---

### Task 1: Lunch Fee component + one-time preset deactivation

**Files:**
- Modify: `fee-management.php:28-31`

**Interfaces:**
- Produces: the `Lunch Fee` `fee_components` row (created lazily by Task 2, not this task — this task only deactivates the 3 old presets). Tasks 2 and 3 both independently do their own find-or-insert for `Lunch Fee`, following the same pattern as `Installment Charges` elsewhere in this file (no shared helper function needed, matching this file's existing style of inlining the same find-or-insert snippet at each call site).

- [ ] **Step 1: Add the one-time deactivation to the existing auto-patcher block**

In `fee-management.php`, find this existing block (lines 28-31):

```php
// Ensure column exists
try {
    $pdo->exec("ALTER TABLE student_details ADD COLUMN IF NOT EXISTS one_time_fees_cleared TINYINT(1) DEFAULT 0");
} catch (PDOException $e) { /* Column already exists */ }
```

Add immediately after it:

```php
// One-time migration: retire the old fixed-price Lunch presets in favor of a free-input Lunch Fee amount
try {
    $pdo->exec("UPDATE fee_components SET is_active = 0 WHERE TRIM(name) = 'Lunch' AND is_active = 1");
} catch (PDOException $e) { /* Already migrated */ }
```

- [ ] **Step 2: Verify the presets are deactivated and historical slips are untouched**

```bash
mysql -u root msst_db -e "SELECT id, name, is_active FROM fee_components WHERE TRIM(name) = 'Lunch';"
mysql -u root msst_db -e "SELECT component_id, component_name, amount FROM fee_slip_components WHERE component_id IN (7,8,9) LIMIT 3;"
```

Visit the page once as admin to trigger the patcher first:

```bash
cat > _t_admin.php <<'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 'ADM001';
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c /tmp/c_admin.txt http://localhost/msst/lms/_t_admin.php
curl -s -b /tmp/c_admin.txt -o /dev/null -w "HTTP %{http_code}\n" http://localhost/msst/lms/fee-management.php
```

Then re-run the two `mysql` queries above. Expected: all rows named exactly `Lunch` (trimmed) show `is_active = 0`; the `fee_slip_components` rows referencing ids 7/8/9 are unchanged (same `component_name`/`amount` as before — this table is never touched by this migration).

- [ ] **Step 3: Commit**

```bash
git add fee-management.php
git commit -m "Retire duplicate fixed-price Lunch fee presets"
```

---

### Task 2: Single-student generation — Lunch Fee backend, form, and live total

**Files:**
- Modify: `fee-management.php` (POST handler around line 232-301, form markup around line 824-836, JS around line 1157-1172)

**Interfaces:**
- Consumes: nothing from Task 1 directly (Task 1 only deactivates old presets; this task creates `Lunch Fee` itself on first use, exactly like `Installment Charges` is created lazily elsewhere in this same handler).
- Produces: the `enable_lunch_fee` (checkbox) / `lunch_fee_amount` (number input) POST field names — Task 3 uses the identical two field names in its own form.

- [ ] **Step 1: Read the lunch fields and append the component in the single-student POST handler**

In `fee-management.php`, find this line (around line 234):

```php
                $installment_charge = isset($_POST['installment_charge']) ? floatval($_POST['installment_charge']) : 0;
```

(this is inside the `if (isset($_POST['generate_slip_for_student']))` handler, right after the `$installments`/`$installment_days` lines). Add immediately after it:

```php
                $enable_lunch_fee = isset($_POST['enable_lunch_fee']);
                $lunch_fee_amount = isset($_POST['lunch_fee_amount']) ? floatval($_POST['lunch_fee_amount']) : 0;
```

Next, find this block (around line 297-300, the end of the "Previous Arrears & Late Fee" `if ($overdue_count > 0)` block):

```php
                    $component_data[] = ['id' => $lf_comp_id, 'name' => 'Previous Arrears & Late Fee (' . $true_overdue_count . ' month(s))', 'amount' => $total_arrears, 'type' => 'recurring'];
                    
                    $pdo->prepare("UPDATE fee_slips SET status = 'Overdue' WHERE student_id = ? AND status = 'Pending' AND due_date < CURRENT_DATE")->execute([$student_id]);
                }
```

Add immediately after that closing `}`:

```php
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

- [ ] **Step 2: Add the "Enable Lunch Fee" switch and amount field to the single-student form**

Find this block (around line 824-826):

```php
                                    <div class="col-md-6 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableMultiMonth" name="enable_multi_month"><label class="form-check-label fw-bold small text-success" for="enableMultiMonth">Multi-Month Generation</label></div></div>
                                    <div class="col-md-6 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableSingleInstallments"><label class="form-check-label fw-bold small text-primary" for="enableSingleInstallments">Enable Installments</label></div></div>
                                </div>
```

Replace with:

```php
                                    <div class="col-md-6 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableMultiMonth" name="enable_multi_month"><label class="form-check-label fw-bold small text-success" for="enableMultiMonth">Multi-Month Generation</label></div></div>
                                    <div class="col-md-6 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableSingleInstallments"><label class="form-check-label fw-bold small text-primary" for="enableSingleInstallments">Enable Installments</label></div></div>
                                    <div class="col-md-6 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableSingleLunchFee" name="enable_lunch_fee"><label class="form-check-label fw-bold small text-warning" for="enableSingleLunchFee">Enable Lunch Fee</label></div></div>
                                </div>
```

Find this block (around line 832-836):

```php
                                <div class="row g-2 mb-3 px-3 installment-fields-single" style="display: none;">
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">No. of Installments</label><input type="number" class="form-control border-primary" name="installments" id="singleInstallments" value="1" min="1" max="4"></div>
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">Days Between</label><input type="number" class="form-control border-primary" name="installment_days" value="15" min="1"></div>
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">Extra Charge/Slip</label><div class="input-group"><span class="input-group-text border-primary bg-primary text-white">PKR</span><input type="number" class="form-control border-primary" name="installment_charge" value="0" min="0" step="0.01"></div></div>
                                </div>
```

Add immediately after that closing `</div>`:

```php
                                <div class="row g-2 mb-3 px-3 lunch-fee-fields-single" style="display: none;">
                                    <div class="col-md-12"><label class="form-label small fw-bold text-warning">Lunch Amount</label><div class="input-group"><span class="input-group-text border-warning bg-warning bg-opacity-25">PKR</span><input type="number" class="form-control border-warning" name="lunch_fee_amount" id="singleLunchFeeAmount" value="0" min="0" step="0.01"></div></div>
                                </div>
```

- [ ] **Step 3: Wire the switch's show/hide and live-total JS**

**Important:** `refreshTotal` is a local closure declared inside `function calculateFeeForStudent(student) {...}` (around line 1109) — it is NOT callable from outside that function. The existing installment fields do NOT call `refreshTotal()` directly from their change handler either; instead, a single delegating handler at line 1507 re-invokes the whole `calculateFeeForStudent(selectedStudent)` function (which rebuilds the component list and calls `refreshTotal()` internally at its end) whenever any of the listed fields change. Follow that exact same pattern — do not introduce a direct `refreshTotal()` call from a top-level handler, it will throw `ReferenceError: refreshTotal is not defined`.

Find this exact line (around line 1501-1503):

```javascript
$('#enableSingleInstallments').on('change', function() {
    if ($(this).is(':checked')) { $('.installment-fields-single').slideDown(); $('#singleInstallments').val(1); const c = activeComponents.find(x => x.name.toLowerCase().includes('installment charge')); if(c) $('input[name="installment_charge"]').val(parseFloat(c.amount).toFixed(2)); } else { $('.installment-fields-single').slideUp(); $('#singleInstallments').val(1); }
});
```

Add immediately after it (before the `$('#enableClassInstallments')` handler that follows):

```javascript
$('#enableSingleLunchFee').on('change', function() {
    if ($(this).is(':checked')) { $('.lunch-fee-fields-single').slideDown(); } else { $('.lunch-fee-fields-single').slideUp(); $('#singleLunchFeeAmount').val(0); }
});
```

Then find this exact line (around line 1507, right after the `$('#enableClassInstallments')` handler):

```javascript
$('#singleInstallments, input[name="installment_charge"], #multiMonthsInput, #enableMultiMonth').on('change keyup', () => { if (selectedStudent) calculateFeeForStudent(selectedStudent); });
```

Replace with:

```javascript
$('#singleInstallments, input[name="installment_charge"], #multiMonthsInput, #enableMultiMonth, #enableSingleLunchFee, #singleLunchFeeAmount').on('change keyup', () => { if (selectedStudent) calculateFeeForStudent(selectedStudent); });
```

This makes toggling the lunch switch or typing an amount re-run the same full recalculation path the installment fields already use — no new global function needed.

Find the `refreshTotal` function (around line 1157-1168):

```javascript
    const refreshTotal = () => {
        let currentTotal = mandatoryTotal;
        const inst = parseInt($('#singleInstallments').val()) || 1;
        const charge = parseFloat($('input[name="installment_charge"]').val()) || 0;
        $('.optional-fee-cb:checked').each(function() { currentTotal += parseFloat($(this).data('amount')); });
        let finalAmount = (currentTotal / inst);
        let extra = '';
        if (inst > 1) { finalAmount += charge; extra = `<div class="mb-2 p-2 border-bottom bg-danger bg-opacity-10 text-danger rounded d-flex justify-content-between"><span>Installment Fee</span><strong>+ PKR ${charge.toFixed(2)}</strong></div>`; }
        $('#totalFeeAmount').text('PKR ' + finalAmount.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#installmentRow').remove();
        if (extra) $('#componentsList').append(`<div id="installmentRow">${extra}</div>`);
    };
```

Replace with:

```javascript
    const refreshTotal = () => {
        let currentTotal = mandatoryTotal;
        const inst = parseInt($('#singleInstallments').val()) || 1;
        const charge = parseFloat($('input[name="installment_charge"]').val()) || 0;
        $('.optional-fee-cb:checked').each(function() { currentTotal += parseFloat($(this).data('amount')); });
        if ($('#enableSingleLunchFee').is(':checked')) {
            currentTotal += parseFloat($('#singleLunchFeeAmount').val()) || 0;
        }
        let finalAmount = (currentTotal / inst);
        let extra = '';
        if (inst > 1) { finalAmount += charge; extra = `<div class="mb-2 p-2 border-bottom bg-danger bg-opacity-10 text-danger rounded d-flex justify-content-between"><span>Installment Fee</span><strong>+ PKR ${charge.toFixed(2)}</strong></div>`; }
        $('#totalFeeAmount').text('PKR ' + finalAmount.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#installmentRow').remove();
        if (extra) $('#componentsList').append(`<div id="installmentRow">${extra}</div>`);
    };
```

- [ ] **Step 4: Verify via curl against real data**

Use a real student from this project's existing test data (student `user_id=161`, class 2, category matching whatever `student_details.fee_category` holds for that student — check first):

```bash
mysql -u root msst_db -e "SELECT id, class_id, fee_category FROM student_details WHERE user_id=161;"
```

Generate a slip with lunch fee enabled for a month that student doesn't already have a slip for (pick a month at least a few months out to avoid the "slip already exists" guard, e.g. `2027-01`):

```bash
curl -s -c /tmp/c_admin.txt http://localhost/msst/lms/_t_admin.php  # recreate if the bootstrap script/cookie from Task 1 is gone
curl -s -b /tmp/c_admin.txt -X POST \
  -d "generate_slip_for_student=1&student_id=161&target_month=2027-01&enable_lunch_fee=1&lunch_fee_amount=1250" \
  http://localhost/msst/lms/fee-management.php -o /tmp/gen_out.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "SELECT fs.slip_no, fs.amount, fsc.component_name, fsc.amount AS component_amount FROM fee_slips fs JOIN fee_slip_components fsc ON fsc.slip_no = fs.slip_no WHERE fs.student_id=161 AND fs.month_year='2027-01-01' AND fsc.component_name = 'Lunch Fee';"
```

Expected: HTTP 302 (redirect after successful POST), one `fee_slip_components` row with `component_name = 'Lunch Fee'` and `component_amount = 1250.00`.

Now verify multi-month does NOT multiply the lunch amount (use a different month to avoid the existing-slip guard, e.g. `2027-02`):

```bash
curl -s -b /tmp/c_admin.txt -X POST \
  -d "generate_slip_for_student=1&student_id=161&target_month=2027-02&enable_multi_month=1&multi_months=3&enable_lunch_fee=1&lunch_fee_amount=1250" \
  http://localhost/msst/lms/fee-management.php -o /tmp/gen_out2.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "SELECT fsc.amount FROM fee_slips fs JOIN fee_slip_components fsc ON fsc.slip_no = fs.slip_no WHERE fs.student_id=161 AND fs.month_year='2027-02-01' AND fsc.component_name = 'Lunch Fee';"
```

Expected: `1250.00` (NOT `3750.00` — confirms no month-multiplication).

Clean up the test data:

```bash
mysql -u root msst_db -e "
DELETE fsc FROM fee_slip_components fsc JOIN fee_slips fs ON fs.slip_no = fsc.slip_no WHERE fs.student_id=161 AND fs.month_year IN ('2027-01-01','2027-02-01');
DELETE FROM fee_slips WHERE student_id=161 AND month_year IN ('2027-01-01','2027-02-01');
"
```

- [ ] **Step 5: Commit**

```bash
git add fee-management.php
git commit -m "Add free-input Lunch Fee option to single-student slip generation"
```

---

### Task 3: Class bulk generation — shared Lunch Fee amount

**Files:**
- Modify: `fee-management.php` (POST handler around line 381-430, modal markup around line 869-876)

**Interfaces:**
- Consumes: the same `enable_lunch_fee`/`lunch_fee_amount` POST field names Task 2 established.

- [ ] **Step 1: Read the lunch fields and append the component in the class bulk POST handler**

In `fee-management.php`, find this line (around line 383, inside the `foreach ($class_students as $student)` loop, in the `if (isset($_POST['generate_slips_for_class']))` handler):

```php
                $installment_charge = isset($_POST['installment_charge']) ? floatval($_POST['installment_charge']) : 0;
```

Add immediately after it:

```php
                $enable_lunch_fee = isset($_POST['enable_lunch_fee']);
                $lunch_fee_amount = isset($_POST['lunch_fee_amount']) ? floatval($_POST['lunch_fee_amount']) : 0;
```

Next, find this block (around line 429-430, the end of the "Previous Arrears & Late Fee" `if ($overdue_count > 0)` block in this same handler):

```php
                    $component_data[] = ['id' => $lf_comp_id, 'name' => 'Previous Arrears & Late Fee (' . $true_overdue_count . ' month(s))', 'amount' => $total_arrears, 'type' => 'recurring'];
                }
```

Add immediately after that closing `}`:

```php
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

- [ ] **Step 2: Add the "Enable Lunch Fee" switch and amount field to the class bulk modal**

Find this block (around line 869-871):

```php
                    <div class="row g-2 mb-3"><div class="col-md-12"><label class="form-label small fw-bold">Target Month</label><input type="month" class="form-control" name="target_month" value="<?= date('Y-m') ?>" required></div>
                        <div class="col-md-12 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableClassInstallments"><label class="form-check-label fw-bold small text-primary" for="enableClassInstallments">Enable Installment Plan</label></div></div>
                    </div>
```

Replace with:

```php
                    <div class="row g-2 mb-3"><div class="col-md-12"><label class="form-label small fw-bold">Target Month</label><input type="month" class="form-control" name="target_month" value="<?= date('Y-m') ?>" required></div>
                        <div class="col-md-12 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableClassInstallments"><label class="form-check-label fw-bold small text-primary" for="enableClassInstallments">Enable Installment Plan</label></div></div>
                        <div class="col-md-12 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableClassLunchFee" name="enable_lunch_fee"><label class="form-check-label fw-bold small text-warning" for="enableClassLunchFee">Enable Lunch Fee (same amount for every student in this class)</label></div></div>
                    </div>
```

Find this block (around line 872-876):

```php
                    <div class="row g-2 mb-3 installment-fields-class" style="display: none;">
                        <div class="col-md-4"><label class="form-label small fw-bold">Installments</label><input type="number" class="form-control" name="installments" id="classInstallments" value="1" min="1" max="4"></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Days Between</label><input type="number" class="form-control" name="installment_days" value="15" min="1"></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Extra Charge</label><input type="number" class="form-control" name="installment_charge" value="0" min="0" step="0.01"></div>
                    </div>
```

Add immediately after that closing `</div>`:

```php
                    <div class="row g-2 mb-3 lunch-fee-fields-class" style="display: none;">
                        <div class="col-md-12"><label class="form-label small fw-bold text-warning">Lunch Amount (per student)</label><input type="number" class="form-control" name="lunch_fee_amount" id="classLunchFeeAmount" value="0" min="0" step="0.01"></div>
                    </div>
```

- [ ] **Step 3: Wire the switch's show/hide JS**

This modal has no live running total to refresh (confirmed: no `#totalFeeAmount`-equivalent element exists in `#generateClassSlipsModal`), so this only needs a show/hide handler, not a recalculation trigger.

Find this exact line (around line 1504-1506):

```javascript
$('#enableClassInstallments').on('change', function() {
    if ($(this).is(':checked')) { $('.installment-fields-class').slideDown(); $('#classInstallments').val(1); const c = activeComponents.find(x => x.name.toLowerCase().includes('installment charge')); if(c) $('#generateClassSlipsModal input[name="installment_charge"]').val(parseFloat(c.amount).toFixed(2)); } else { $('.installment-fields-class').slideUp(); $('#classInstallments').val(1); }
});
```

Add immediately after it:

```javascript
$('#enableClassLunchFee').on('change', function() {
    if ($(this).is(':checked')) { $('.lunch-fee-fields-class').slideDown(); } else { $('.lunch-fee-fields-class').slideUp(); $('#classLunchFeeAmount').val(0); }
});
```

- [ ] **Step 4: Verify via curl against real data**

Use two real students in the same class (class 2) so a shared amount can be confirmed identical across both. Find at least two:

```bash
mysql -u root msst_db -e "SELECT sd.user_id, u.full_name, sd.class_id FROM student_details sd JOIN users u ON u.id = sd.user_id WHERE sd.class_id = 2 LIMIT 5;"
```

Bulk-generate for class 2 with lunch fee enabled, targeting a month with no existing slips for those students (e.g. `2027-03`):

```bash
curl -s -b /tmp/c_admin.txt -X POST \
  -d "generate_slips_for_class=1&class_id=2&target_month=2027-03&enable_lunch_fee=1&lunch_fee_amount=900" \
  http://localhost/msst/lms/fee-management.php -o /tmp/gen_class_out.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "
SELECT fs.student_id, fsc.component_name, fsc.amount
FROM fee_slips fs JOIN fee_slip_components fsc ON fsc.slip_no = fs.slip_no
WHERE fs.month_year = '2027-03-01' AND fsc.component_name = 'Lunch Fee';
"
```

Expected: HTTP 302, one `Lunch Fee` row per class-2 student who got a slip generated, every row showing `amount = 900.00` (identical across all of them).

Clean up:

```bash
mysql -u root msst_db -e "
DELETE fsc FROM fee_slip_components fsc JOIN fee_slips fs ON fs.slip_no = fsc.slip_no WHERE fs.month_year = '2027-03-01' AND fs.student_id IN (SELECT user_id FROM student_details WHERE class_id = 2);
DELETE FROM fee_slips WHERE month_year = '2027-03-01' AND student_id IN (SELECT user_id FROM student_details WHERE class_id = 2);
"
```

- [ ] **Step 5: Commit**

```bash
git add fee-management.php
git commit -m "Add shared Lunch Fee amount to class bulk slip generation"
```

---

### Task 4: Final end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: `php -l` the modified file**

```bash
php -l fee-management.php
```

Expected: "No syntax errors detected in fee-management.php"

- [ ] **Step 2: Confirm the old presets no longer appear in the single-student optional-components picker**

The page embeds the active components list into the JS variable `const activeComponents = <?= $active_components_json ?>;` (around line 1070). Confirm via curl that the emitted JSON no longer includes any component named exactly `Lunch` (trimmed):

```bash
curl -s -b /tmp/c_admin.txt http://localhost/msst/lms/fee-management.php | grep -o 'const activeComponents = \[[^;]*\];' | head -c 3000
```

Expected: no `"name":"Lunch "` or `"name":"Lunch"` (trimmed) entries; `Lunch Fee` may or may not appear depending on whether Tasks 2/3 left it `is_active=1` (it should, since it's the new intended option).

- [ ] **Step 3: Confirm installment-splitting divides the lunch amount like every other component**

Generate a single-student slip with both installments and lunch fee enabled, for a month with no existing slip (e.g. `2027-04`):

```bash
curl -s -b /tmp/c_admin.txt -X POST \
  -d "generate_slip_for_student=1&student_id=161&target_month=2027-04&installments=2&installment_days=15&installment_charge=0&enable_lunch_fee=1&lunch_fee_amount=1000" \
  http://localhost/msst/lms/fee-management.php -o /tmp/gen_inst_out.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "
SELECT fs.slip_no, fsc.component_name, fsc.amount
FROM fee_slips fs JOIN fee_slip_components fsc ON fsc.slip_no = fs.slip_no
WHERE fs.student_id=161 AND fs.month_year='2027-04-01' AND fsc.component_name = 'Lunch Fee'
ORDER BY fs.slip_no;
"
```

Expected: two rows (slip numbers ending `-1` and `-2`), each with `amount = 500.00` (the 1000 lunch amount split evenly across the 2 installments, same as every other component).

Clean up:

```bash
mysql -u root msst_db -e "
DELETE fsc FROM fee_slip_components fsc JOIN fee_slips fs ON fs.slip_no = fsc.slip_no WHERE fs.student_id=161 AND fs.month_year='2027-04-01';
DELETE FROM fee_slips WHERE student_id=161 AND month_year='2027-04-01';
"
```

- [ ] **Step 4: Clean up disposable test artifacts**

```bash
rm -f _t_admin.php /tmp/c_admin.txt /tmp/gen_out.html /tmp/gen_out2.html /tmp/gen_class_out.html /tmp/gen_inst_out.html
cd "B:\PO\Website\xampp 8.2\htdocs\msst\lms"
git status --short
```

Expected: clean (only the intentional commits from Tasks 1-3 show in `git log`, no stray files in `git status --short` beyond any pre-existing untracked files noted at the start of this work).
