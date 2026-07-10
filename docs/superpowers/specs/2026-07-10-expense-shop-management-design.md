# Expense Management + Shop Management — Design Spec

## Goal

Port two existing, working features from a sister codebase (Al-Abbas LMS,
`B:\PO\Website\xampp 8.2\htdocs\alabbas-lms\Alabbas-lms`) into MSST LMS:
**Expense Management** (categorized expense ledger with a running monthly
balance) and **Shop Management** (vendor directory + per-shop expense
view + bulk payment processing). Both are admin-only, fully standalone
from the academic side of MSST (no student/teacher/class coupling).

## Source reference

Read-only reference implementation, do not modify:
- `Alabbas-lms/manage-expense.php` — main expense ledger page
- `Alabbas-lms/manage-categories.php` — category/product CRUD
- `Alabbas-lms/shop-management.php` — shop directory + per-shop ledger
- `Alabbas-lms/api_expense_handler.php` — shared AJAX endpoint for all of the above
- `Alabbas-lms/assets/js/expense.js` — client JS for manage-expense.php

Confirmed dead/unused in the source app (do NOT port): `product.php`,
`store.php`, `supplier.php`, `unit.php`, `purchase.php` (0 bytes),
`category.php`, `expense_form_fields.php`, `expense_modals.php`. None of
these are linked from the source's live `sidebar.php` or included by any
live page — verified via grep across the whole source repo.

## Decisions from brainstorming

- **No data migration.** MSST's tables start empty. Do not copy Al-Abbas's
  626 expense rows, 267 products, 19 shops, or 25 categories.
- **Bill number prefix: `EXP`**, sequential (e.g. `EXP1001`, `EXP1002`),
  replacing the source's Al-Abbas-branded `AECM` prefix.
- **Fix a latent bug from the source**: the source stores `expenses.month`
  as a lowercase 3-letter month abbreviation only (`date('M', ...)` ->
  `strtolower()`, e.g. `"jun"`), with no year. Its balance/summary
  queries filter `WHERE LOWER(month) = ?`, which means two Junes from
  different years would be silently combined into one running balance.
  MSST's port stores `month` as `'Y-m'` (e.g. `"2026-06"`) instead, and
  every query that filters/groups by month uses this format. This is a
  correctness fix, not a design choice to ask about — the source
  behavior is clearly unintended (no realistic school budget tracking
  wants "every June ever" merged into one ledger).
- **Add a real FK** on `expenses.shop_id -> shops.id ON DELETE SET NULL`
  (source has no FK there, only `product_id` has one). MSST's other
  tables use FKs consistently; match that.
- Use MSST's **auto-patcher convention** (`CREATE TABLE IF NOT EXISTS` +
  `SHOW COLUMNS` / `ADD COLUMN IF NOT EXISTS` pattern, as seen in
  `attendance-management.php`, `teacher-management.php`, etc.) rather
  than assuming the schema pre-exists, since the source does not do this
  for these two features but every MSST page that owns a table does.

## Database schema (new tables in `msst_db`)

```sql
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expense_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(100) DEFAULT NULL,
    current_price DECIMAL(10,2) DEFAULT 0.00,
    last_updated DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product (category_id, name, company),
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS product_price_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS shops (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_no VARCHAR(50) NOT NULL UNIQUE,
    date DATE NOT NULL,
    shop_id INT DEFAULT NULL,
    product_id INT DEFAULT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    amount DECIMAL(10,2) NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    month VARCHAR(7) NOT NULL COMMENT 'Y-m format, e.g. 2026-06',
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    cn VARCHAR(50) DEFAULT NULL COMMENT 'cheque number',
    payment_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_month (month),
    KEY idx_date (date),
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE SET NULL
);
```

Note: no denormalized `shop`/`category` varchar columns (the source has
`expenses.shop` and `expenses.category` as legacy denormalized copies —
`category` was confirmed entirely empty/unused in the source's live data,
and `shop` is redundant with the FK; MSST always joins for display
instead).

## Pages to build

### 1. `expense-management.php` (admin)

Ported from `manage-expense.php`. Sections:
- **Monthly summary card**: month-range picker (`<input type=month>` x2
  for start/end), total expenses in range.
- **Expense detail table**: date-range filter, Paid/Unpaid/All radio
  filter, columns (Bill No, Date, Shop, Category, Product, Qty, Unit
  Price, Amount, Balance, Status), row actions (Edit, Delete, Print
  single invoice).
- **Add Expense modal**: Shop dropdown (AJAX-searchable, "+ Add new
  shop" inline option matching source behavior of accepting a shop_id or
  a shop_id of `'new'` with a name), Category dropdown -> cascading
  Product dropdown (AJAX), unit price auto-fills from
  `expense_products.current_price` but is editable, Quantity x Unit
  Price <-> Total Amount three-way live sync (matches source's
  `handleQuantityChange`/`handleUnitPriceChange`/`handleTotalAmountChange`),
  bill number auto-generated + read-only, date defaults to today.
- **Edit Expense modal**: same fields, pre-filled, editable.
- **Delete**: confirm dialog, then `recalculateBalances()` for that
  expense's month.
- **Print Summary** and **Print Full Report**: client-side window.open
  print views, same approach as source (`@media print` CSS, hidden
  printable divs) — port as-is, just re-brand header to "Muhaddisa
  School of Science and Technology" (matching the branding already used
  in MSST's other print reports, e.g. `attendance-report.php`).

### 2. `expense-categories.php` (admin)

Ported from `manage-categories.php`. Two DataTables: Categories (name,
product count, Edit/Delete) and Products (name, company, category,
current price, Edit/Delete). Add Category modal, Add Product modal
(category preselected when launched from a category row's "+ Add
Product" button), Edit modals for both. Product price edits archive the
prior price into `product_price_history` before updating
`current_price` (matches source's `update_product_price` action).

### 3. `shop-management.php` (admin)

Ported from `shop-management.php`. Shops DataTable (name, phone,
address, linked expense count, total spent — server-verified sum, not
just the JOIN'd aggregate, matching source's mismatch-guard pattern),
Add/Edit Shop modals, Delete Shop (blocked with a clear message if the
shop has any linked `expenses` rows — matches source's
`Cannot delete shop with existing expense records` guard). "View
Expenses" modal per shop: AJAX-loaded expense list scoped to that shop,
Paid/Unpaid/All filter + date-range filter (DataTables custom search
plugin, matching source), row checkboxes + "select all", bulk "Mark as
Paid" button (shows running selected-count/total) opening a Payment
modal (cheque number required, payment date defaults to today), submits
to `process_payment`. Print button for the (filtered) expense list.

### 4. `api_expense_handler.php` (admin, root-level, mirrors
   `api_user_handler.php`'s dispatch style)

Single endpoint, `$_POST['action']` (or `$_GET['action']` for the
read-only lookups), guarded by `$_SESSION['admin_logged_in']` (403 JSON
on failure, matching every other MSST API handler). Actions, ported
1:1 from the source's `api_expense_handler.php` with the month-format
and bill-prefix fixes applied:

- `get_products` (GET, `cat_id`) — cascading dropdown
- `add_category`, `edit_category`, `delete_category`
- `add_product`, `edit_product`, `delete_product`
- `update_product_price` — archives to `product_price_history` first
- `get_product_price`, `get_all_product_prices`
- `add` (add expense) — resolves shop (existing id, or `'new'` + name ->
  inserts into `shops`), computes `amount = quantity * unit_price`,
  computes running `balance` via `SELECT COALESCE(SUM(amount),0) FROM
  expenses WHERE month = ?` + this expense's amount
- `get_next_bill` — `SELECT bill_no FROM expenses ORDER BY id DESC LIMIT
  1`, parse trailing digits after the 3-char `EXP` prefix, `+1`, default
  seed `1001` if no rows yet
- `update` (edit expense) — same shop-resolution + amount recompute,
  then `recalculateBalances($month)`
- `delete` — look up the expense's month, delete, `recalculateBalances($month)`
- `process_payment` (bulk mark-paid) — transaction: requires non-empty
  `expense_ids[]` and non-empty `cn`, `UPDATE ... SET is_paid=1, cn=?,
  payment_date=? WHERE id IN (...)`, then `recalculateBalances()` for
  every distinct affected month
- `recalculate_balance` — manual admin recompute given a starting
  balance for a month
- `get_shops` (GET, optional `search` on name/phone)
- `add_shop`, `edit_shop`
- `delete_shop` — guarded: refuses (JSON `success:false`) if
  `SELECT COUNT(*) FROM expenses WHERE shop_id = ?` > 0
- `get_shop_expenses` (GET, `shop_id`) — joined expense list for the
  shop-management "view expenses" modal

`recalculateBalances($month)` helper: `SELECT id, amount FROM expenses
WHERE month = ? ORDER BY date, id`, then walk in order accumulating and
writing `balance` per row (exact port of source logic, just `month`
comparison is now an exact `'Y-m'` string match instead of
`LOWER(month) = ?`).

### 5. `assets/js/expense-management.js`

Ported from `assets/js/expense.js`: product-price caching, cascading
category->product dropdowns, invoice printing, edit/delete flows, shop
loading/search, quantity/price/total three-way sync, bill-number
auto-fetch on modal open, AJAX save. Renamed to avoid confusion with
any future MSST-native "expense" naming, and to make the port's origin
traceable in the MSST codebase.

## Sidebar

In `sidebar.php`, add after the existing "Fee Payment Report" link:

```html
<li class="nav-item dropdown">
    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
        <i class="fas fa-chart-bar fa-fw me-2"></i> <span>Expense Management</span>
    </a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="expense-management.php"><i class="fas fa-receipt me-2"></i>View Expenses</a></li>
        <li><a class="dropdown-item" href="expense-categories.php"><i class="fas fa-tags me-2"></i>Categories & Products</a></li>
    </ul>
</li>
<li><a href="shop-management.php"><i class="fas fa-shop fa-fw me-2"></i> <span>Shop Management</span></a></li>
```

MSST's `sidebar.php` uses a flat `<ul><li><a>` structure (no `nav-item`/
`nav-link` classes like the source — match MSST's existing plain `<li><a>`
markup, not the source's Bootstrap nav classes). This is the first
dropdown nav item in MSST's sidebar, but no extra plumbing is needed:
every admin page already loads `bootstrap.bundle.min.js` (confirmed in
`header.php`'s includers, e.g. `dashboard.php`), which includes the
dropdown plugin, so `data-bs-toggle="dropdown"` works immediately. The
existing active-link-highlighting script at the bottom of `sidebar.php`
selects `#sidebar ul li a` generically and will correctly highlight
`expense-management.php`/`expense-categories.php` inside the dropdown
without any changes.

## Out of scope / explicitly not built

- No import/migration of Al-Abbas's existing data.
- No product/purchase/store/supplier/unit inventory system (the source's
  dead scaffold pages for these have no real logic to port).
- No `expense_summary` / `expense_summary_items` "custom summary line
  items" feature (confirmed unused/empty in source — 0 rows, not
  queried by the three core files). Can be added later as a follow-up
  if the client asks for it specifically.

## Testing approach

Same as the rest of this session: curl + session-bootstrap scripts + direct
`mysql` queries against `msst_db`, no browser automation. Verify each
CRUD action end-to-end (add category -> add product -> add shop -> add
expense -> verify balance -> edit -> delete -> verify balance
recalculates), and verify the bulk-payment flow updates `is_paid`/`cn`/
`payment_date` and recalculates balances for affected months.
