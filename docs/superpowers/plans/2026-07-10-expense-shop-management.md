# Expense Management + Shop Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port Expense Management and Shop Management from Al-Abbas LMS
(`B:\PO\Website\xampp 8.2\htdocs\alabbas-lms\Alabbas-lms`) into MSST LMS
(`B:\PO\Website\xampp 8.2\htdocs\msst\lms`), per the approved design spec at
`docs/superpowers/specs/2026-07-10-expense-shop-management-design.md`.

**Architecture:** Five new admin-only PHP pages/endpoints plus one JS asset,
all following MSST's existing conventions exactly (session auth check,
`sidebar.php`/`header.php`/`footer.php` includes, PDO with `$pdo`,
self-healing `CREATE TABLE IF NOT EXISTS` auto-patcher). No data migration
from Al-Abbas — MSST's tables start empty.

**Tech Stack:** PHP 8 + PDO/MySQL, Bootstrap 5.3.0, jQuery 3.7.0, DataTables
1.13.6 (all already loaded via CDN elsewhere in MSST — no new dependencies).

## Global Constraints

- Bill number prefix: `EXP` (not Al-Abbas's `AECM`), sequential from `1001`.
- `expenses.month` is stored as `'Y-m'` (e.g. `2026-06`), never a bare
  3-letter month name — this is a deliberate fix of a bug in the source
  where `date('M', ...)` conflated the same calendar month across
  different years. Every query that filters/groups on `month` must use
  this format.
- No denormalized `shop`/`category` varchar columns on `expenses` — always
  JOIN `shops`/`expense_products`/`expense_categories` for display.
- No data migration: every new table starts empty (`CREATE TABLE IF NOT
  EXISTS` only, no `INSERT` seed data).
- No `expense_summary`/`expense_summary_items` "custom summary line items"
  feature and no "Edit Summary Items" link — out of scope per spec.
- Do not port `product.php`, `store.php`, `supplier.php`, `unit.php`,
  `purchase.php`, `category.php`, `expense_form_fields.php`,
  `expense_modals.php` — confirmed dead/unused in the source.
- Every new PHP file starts with `session_start();
  require_once __DIR__ . '/config/db_config.php';` then
  `if (!isset($_SESSION['admin_logged_in'])) { header('Location:
  login.php'); exit; }` then `$admin_name = $_SESSION['admin_name'] ??
  'Admin';` — matches every existing MSST admin page (see
  `fee-payment-report.php` for the exact reference pattern).
- Branding in print views: "Muhaddisa School of Science and Technology"
  (matches `attendance-report.php`'s print header), not Al-Abbas's name.

---

### Task 1: Database schema auto-patcher

**Files:**
- Create: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\expense-management.php` (schema block only in this task — full page comes in Task 5, but the auto-patcher must exist before Task 2's API handler can be tested, so this task creates the file with JUST the auth check + auto-patcher + a temporary `echo 'patched';` body, which Task 5 will overwrite)

**Interfaces:**
- Produces: five tables — `expense_categories`, `expense_products`,
  `product_price_history`, `shops`, `expenses` — that every later task
  depends on. Exact column names below are final; later tasks must match
  them exactly.

- [ ] **Step 1: Create the file with auth check + auto-patcher**

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// =======================================================
// AUTO-PATCHER: Expense & Shop Management tables
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
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
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_price_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            effective_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shops (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
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
            month VARCHAR(7) NOT NULL,
            is_paid TINYINT(1) NOT NULL DEFAULT 0,
            cn VARCHAR(50) DEFAULT NULL,
            payment_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_month (month),
            KEY idx_date (date),
            FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE SET NULL
        )
    ");
} catch (PDOException $e) { /* Ignore if already up to date */ }

echo 'Schema patched OK';
```

- [ ] **Step 2: Verify the tables are created**

Bootstrap an admin session and hit the page:

```bash
cat > _t_admin.php << 'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c cj_admin.txt "http://localhost/msst/lms/_t_admin.php" > /dev/null
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-management.php"
```
Expected output: `Schema patched OK`

Then verify the tables exist:
```bash
mysql -u root msst_db -e "SHOW TABLES LIKE 'expense%';"
mysql -u root msst_db -e "SHOW TABLES LIKE 'shops';"
mysql -u root msst_db -e "SHOW TABLES LIKE 'product_price_history';"
```
Expected: `expense_categories`, `expense_products`, `expenses`, `shops`,
`product_price_history` all present, all with 0 rows
(`SELECT COUNT(*) FROM expenses;` etc. all return 0).

Clean up the bootstrap script and cookie jar (`rm -f _t_admin.php
cj_admin.txt`) after verifying — do not leave test artifacts in the repo.

- [ ] **Step 3: Commit**

```bash
git add expense-management.php
git commit -m "Add schema auto-patcher for Expense & Shop Management tables"
```

---

### Task 2: `api_expense_handler.php` — shared AJAX endpoint

**Files:**
- Create: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\api_expense_handler.php`

**Interfaces:**
- Consumes: the five tables from Task 1 (exact column names as created there).
- Produces: every action listed below, dispatched via `$_POST['action']`
  or `$_GET['action']`. Tasks 3, 4, and 5 (the UI pages) call these
  actions by name via `fetch()`/AJAX — the action names and their
  request/response shapes below are the contract those pages are written
  against, so they must match exactly.

This is a faithful port of Al-Abbas's `api_expense_handler.php` with two
changes applied throughout: `month` is computed as `date('Y-m',
strtotime($date))` instead of `strtolower(date('M', strtotime($date)))`,
and the bill-number prefix is `EXP` instead of `AECM` with the initial
seed serial `1001` instead of `2341001`. The denormalized `shop`/`phone`/
`category` columns from the source's `expenses` table do not exist in
MSST's schema (Task 1), so the `add`/`update` actions here do not select
or write a `shop` or `phone` column — shop identity is carried purely by
`shop_id`, and phone is looked up via JOIN at display time in Tasks 3-5.

- [ ] **Step 1: Create the file**

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit(json_encode(['message' => 'Unauthorized']));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // === GET PRODUCTS (cascading dropdown) ===
        case 'get_products':
            if (isset($_GET['cat_id'])) {
                $stmt = $pdo->prepare("SELECT id, name, company, current_price as price FROM expense_products WHERE category_id = ? ORDER BY name");
                $stmt->execute([$_GET['cat_id']]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        // === CATEGORY ===
        case 'add_category':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['message' => 'Name required']);
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(['message' => 'Category added']);
            break;

        case 'edit_category':
            try {
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    echo json_encode(['message' => 'Name required']);
                    break;
                }
                $stmt = $pdo->prepare("UPDATE expense_categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $_POST['id']]);
                echo json_encode(['message' => 'Category updated successfully!']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        case 'delete_category':
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['message' => 'Category deleted']);
            break;

        // === PRODUCT ===
        case 'add_product':
            try {
                $stmt = $pdo->prepare("INSERT INTO expense_products (category_id, name, company, current_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['category_id'],
                    $_POST['name'],
                    $_POST['company'] ?? '',
                    $_POST['price'] ?? 0.00
                ]);
                echo json_encode(['message' => 'Product added successfully!']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        case 'edit_product':
            try {
                $stmt = $pdo->prepare("UPDATE expense_products SET name = ?, company = ?, current_price = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['company'] ?? '',
                    $_POST['price'] ?? 0.00,
                    $_POST['id']
                ]);
                echo json_encode(['message' => 'Product updated successfully!']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        case 'delete_product':
            $stmt = $pdo->prepare("DELETE FROM expense_products WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['message' => 'Product deleted']);
            break;

        case 'update_product_price':
            $product_id = $_POST['product_id'];
            $new_price = $_POST['price'];

            $stmt = $pdo->prepare("SELECT current_price FROM expense_products WHERE id = ?");
            $stmt->execute([$product_id]);
            $current_price = $stmt->fetchColumn();

            if ($current_price > 0) {
                $stmt = $pdo->prepare("INSERT INTO product_price_history (product_id, price, effective_date) VALUES (?, ?, CURDATE())");
                $stmt->execute([$product_id, $current_price]);
            }

            $stmt = $pdo->prepare("UPDATE expense_products SET current_price = ?, last_updated = CURDATE() WHERE id = ?");
            $stmt->execute([$new_price, $product_id]);

            echo json_encode(['success' => true, 'message' => 'Price updated']);
            break;

        case 'get_product_price':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT id, name, company, current_price as price FROM expense_products WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode($product ?: ['price' => 0]);
            }
            break;

        case 'get_all_product_prices':
            $stmt = $pdo->query("SELECT id, current_price as price FROM expense_products");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $prices = [];
            foreach ($products as $product) {
                $prices[$product['id']] = $product['price'];
            }
            echo json_encode($prices);
            break;

        // === EXPENSE ADD ===
        case 'add':
            $date = $_POST['date'];
            $month = date('Y-m', strtotime($date));
            $quantity = $_POST['quantity'] ?? 1;
            $unit_price = $_POST['unit_price'] ?? $_POST['amount'];
            $total_amount = $quantity * $unit_price;

            $shop_id = $_POST['shop_id'] ?? null;
            $shop_name = $_POST['shop'] ?? '';
            $phone = $_POST['phone'] ?? '';

            if ($shop_id && !empty($shop_id) && $shop_id != 'new') {
                $stmt = $pdo->prepare("SELECT name, phone FROM shops WHERE id = ?");
                $stmt->execute([$shop_id]);
                $shop = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$shop) {
                    echo json_encode(['success' => false, 'message' => 'Selected shop no longer exists']);
                    break;
                }
            } elseif (!empty($shop_name) && ($shop_id == 'new' || empty($shop_id))) {
                $stmt = $pdo->prepare("INSERT INTO shops (name, phone) VALUES (?, ?)");
                $stmt->execute([$shop_name, $phone]);
                $shop_id = $pdo->lastInsertId();
            } else {
                echo json_encode(['success' => false, 'message' => 'Shop is required']);
                break;
            }

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE month = ?");
            $stmt->execute([$month]);
            $current_balance = $stmt->fetchColumn();
            $new_balance = $current_balance + $total_amount;

            $stmt = $pdo->prepare("
                INSERT INTO expenses
                (bill_no, date, shop_id, amount, unit_price, quantity, balance, product_id, month, cn, is_paid)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $_POST['bill_no'],
                $date,
                $shop_id,
                $total_amount,
                $unit_price,
                $quantity,
                $new_balance,
                $_POST['product_id'],
                $month,
                $_POST['cn'] ?? null
            ]);
            echo json_encode(['success' => true, 'message' => 'Expense added']);
            break;

        // === GET NEXT BILL NUMBER ===
        case 'get_next_bill':
            try {
                $last = $pdo->query("SELECT bill_no FROM expenses ORDER BY id DESC LIMIT 1")->fetchColumn();
                $next_serial = $last ? (int)substr($last, 3) + 1 : 1001;
                $next_bill_no = "EXP" . $next_serial;
                echo json_encode(['bill_no' => $next_bill_no]);
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        // === EXPENSE UPDATE ===
        case 'update':
            $id = $_POST['id'];
            $date = $_POST['date'];
            $month = date('Y-m', strtotime($date));
            $quantity = $_POST['quantity'] ?? 1;
            $unit_price = $_POST['unit_price'] ?? $_POST['amount'];
            $total_amount = $quantity * $unit_price;

            $shop_id = $_POST['shop_id'] ?? null;
            $shop_name = $_POST['shop'] ?? '';
            $phone = $_POST['phone'] ?? '';

            if ($shop_id && !empty($shop_id) && $shop_id != 'new') {
                $stmt = $pdo->prepare("SELECT id FROM shops WHERE id = ?");
                $stmt->execute([$shop_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Selected shop no longer exists']);
                    break;
                }
            } elseif (!empty($shop_name) && ($shop_id == 'new' || empty($shop_id))) {
                $stmt = $pdo->prepare("INSERT INTO shops (name, phone) VALUES (?, ?)");
                $stmt->execute([$shop_name, $phone]);
                $shop_id = $pdo->lastInsertId();
            } else {
                echo json_encode(['success' => false, 'message' => 'Shop is required']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE expenses
                SET date = ?, shop_id = ?,
                    amount = ?, unit_price = ?, quantity = ?,
                    product_id = ?, month = ?, cn = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $date,
                $shop_id,
                $total_amount,
                $unit_price,
                $quantity,
                $_POST['product_id'],
                $month,
                $_POST['cn'] ?? null,
                $id
            ]);

            recalculateBalances($month);
            echo json_encode(['success' => true, 'message' => 'Expense updated']);
            break;

        // === EXPENSE DELETE ===
        case 'delete':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT month FROM expenses WHERE id = ?");
            $stmt->execute([$id]);
            $month = $stmt->fetchColumn();
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$id]);
            if ($month) {
                recalculateBalances($month);
            }
            echo json_encode(['message' => 'Expense deleted']);
            break;

        // === PROCESS PAYMENT (bulk mark-paid) ===
        case 'process_payment':
            try {
                $expense_ids = $_POST['expense_ids'] ?? [];
                $cn = $_POST['cn'] ?? '';
                $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

                if (empty($expense_ids)) {
                    echo json_encode(['success' => false, 'message' => 'No expenses selected']);
                    break;
                }

                if (empty($cn)) {
                    echo json_encode(['success' => false, 'message' => 'Cheque number is required']);
                    break;
                }

                $pdo->beginTransaction();

                $placeholders = implode(',', array_fill(0, count($expense_ids), '?'));
                $stmt = $pdo->prepare("
                    UPDATE expenses
                    SET is_paid = 1,
                        cn = ?,
                        payment_date = ?
                    WHERE id IN ($placeholders)
                ");
                $params = array_merge([$cn, $payment_date], $expense_ids);
                $stmt->execute($params);
                $updated_count = $stmt->rowCount();

                $stmt = $pdo->prepare("SELECT DISTINCT month FROM expenses WHERE id IN ($placeholders)");
                $stmt->execute($expense_ids);
                $months = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($months as $month) {
                    recalculateBalances($month);
                }

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "$updated_count expenses marked as paid successfully"
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        case 'recalculate_balance':
            $month = $_POST['month'];
            $start = (float)$_POST['starting_balance'];
            $stmt = $pdo->prepare("SELECT id, amount FROM expenses WHERE month = ? ORDER BY date, id");
            $stmt->execute([$month]);
            $rows = $stmt->fetchAll();
            $balance = $start;
            foreach ($rows as $r) {
                $balance += $r['amount'];
                $pdo->prepare("UPDATE expenses SET balance = ? WHERE id = ?")->execute([$balance, $r['id']]);
            }
            echo json_encode(['message' => 'Balance updated from Rs. ' . number_format($start)]);
            break;

        // === SHOPS ===
        case 'get_shops':
            $search = $_GET['search'] ?? '';
            if ($search) {
                $stmt = $pdo->prepare("SELECT id, name, phone, address FROM shops WHERE name LIKE ? OR phone LIKE ?");
                $searchTerm = "%$search%";
                $stmt->execute([$searchTerm, $searchTerm]);
            } else {
                $stmt = $pdo->query("SELECT id, name, phone, address FROM shops ORDER BY name");
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'add_shop':
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Shop name required']);
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO shops (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $address]);
            echo json_encode(['success' => true, 'message' => 'Shop added', 'id' => $pdo->lastInsertId()]);
            break;

        case 'edit_shop':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($id <= 0 || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data: Shop ID and name are required']);
                break;
            }

            try {
                $stmt = $pdo->prepare("UPDATE shops SET name = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $address, $id]);
                echo json_encode(['success' => true, 'message' => 'Shop updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'delete_shop':
            $shop_id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE shop_id = ?");
            $stmt->execute([$shop_id]);
            $usage_count = $stmt->fetchColumn();

            if ($usage_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete shop with existing expense records']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM shops WHERE id = ?");
            $stmt->execute([$shop_id]);
            echo json_encode(['success' => true, 'message' => 'Shop deleted']);
            break;

        case 'get_shop_expenses':
            $shop_id = $_GET['shop_id'];
            $stmt = $pdo->prepare("
                SELECT e.*,
                       p.name as product_name,
                       p.company,
                       c.name as category_name
                FROM expenses e
                LEFT JOIN expense_products p ON e.product_id = p.id
                LEFT JOIN expense_categories c ON p.category_id = c.id
                WHERE e.shop_id = ?
                ORDER BY e.date DESC
            ");
            $stmt->execute([$shop_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        default:
            echo json_encode(['message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}

function recalculateBalances($month) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id, amount
        FROM expenses
        WHERE month = ?
        ORDER BY date, id
    ");
    $stmt->execute([$month]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $balance = 0;
    foreach ($expenses as $e) {
        $balance += $e['amount'];
        $update = $pdo->prepare("UPDATE expenses SET balance = ? WHERE id = ?");
        $update->execute([$balance, $e['id']]);
    }
}
```

- [ ] **Step 2: Verify each action end-to-end via curl**

Reuse the `_t_admin.php` bootstrap script and `cj_admin.txt` cookie jar
pattern from Task 1 (recreate them if you deleted them).

```bash
# Category CRUD
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_category" --data-urlencode "name=Test Category"
mysql -u root msst_db -e "SELECT * FROM expense_categories;"

# Product CRUD (use the category id from above)
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_product" --data-urlencode "category_id=1" \
  --data-urlencode "name=Test Product" --data-urlencode "company=Acme" \
  --data-urlencode "price=250"
mysql -u root msst_db -e "SELECT * FROM expense_products;"

# Shop CRUD
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_shop" --data-urlencode "name=Test Shop" \
  --data-urlencode "phone=03001234567"
mysql -u root msst_db -e "SELECT * FROM shops;"

# get_next_bill (expect EXP1001 on an empty expenses table)
curl -s -b cj_admin.txt "http://localhost/msst/lms/api_expense_handler.php?action=get_next_bill"

# Add expense (use real ids from above)
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add" --data-urlencode "bill_no=EXP1001" \
  --data-urlencode "date=2026-07-10" --data-urlencode "shop_id=1" \
  --data-urlencode "product_id=1" --data-urlencode "quantity=2" \
  --data-urlencode "unit_price=250"
mysql -u root msst_db -e "SELECT bill_no, date, shop_id, product_id, quantity, unit_price, amount, balance, month FROM expenses;"
```
Expected: `expenses` row has `month = '2026-07'` (not a bare month name),
`amount = 500.00` (2 x 250), `balance = 500.00`.

```bash
# get_next_bill again (expect EXP1002 now that one expense exists)
curl -s -b cj_admin.txt "http://localhost/msst/lms/api_expense_handler.php?action=get_next_bill"

# Add a second expense in the same month to verify balance accumulates
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add" --data-urlencode "bill_no=EXP1002" \
  --data-urlencode "date=2026-07-11" --data-urlencode "shop_id=1" \
  --data-urlencode "product_id=1" --data-urlencode "quantity=1" \
  --data-urlencode "unit_price=100"
mysql -u root msst_db -e "SELECT bill_no, amount, balance FROM expenses ORDER BY id;"
```
Expected: second row `amount = 100.00`, `balance = 600.00` (500 + 100).

```bash
# Edit the first expense, verify recalculateBalances cascades
EXP_ID=$(mysql -u root msst_db -N -e "SELECT id FROM expenses WHERE bill_no='EXP1001';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=update" --data-urlencode "id=$EXP_ID" \
  --data-urlencode "date=2026-07-10" --data-urlencode "shop_id=1" \
  --data-urlencode "product_id=1" --data-urlencode "quantity=3" \
  --data-urlencode "unit_price=250"
mysql -u root msst_db -e "SELECT bill_no, amount, balance FROM expenses ORDER BY id;"
```
Expected: EXP1001 `amount = 750.00`, `balance = 750.00`; EXP1002 `balance
= 850.00` (750 + 100) — confirms `recalculateBalances` re-walked both
rows in the month in date order.

```bash
# Bulk payment
EXP_ID_2=$(mysql -u root msst_db -N -e "SELECT id FROM expenses WHERE bill_no='EXP1002';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=process_payment" \
  --data-urlencode "expense_ids[]=$EXP_ID_2" \
  --data-urlencode "cn=CHK-001" --data-urlencode "payment_date=2026-07-10"
mysql -u root msst_db -e "SELECT bill_no, is_paid, cn, payment_date FROM expenses WHERE bill_no='EXP1002';"
```
Expected: `is_paid = 1`, `cn = 'CHK-001'`, `payment_date = '2026-07-10'`.

```bash
# delete_shop guard: try deleting shop 1, expect refusal since it has expenses
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=delete_shop" --data-urlencode "id=1"
```
Expected JSON: `{"success":false,"message":"Cannot delete shop with existing expense records"}`

Clean up all test data you created in this step before moving on:
```bash
mysql -u root msst_db -e "DELETE FROM expenses; DELETE FROM expense_products; DELETE FROM expense_categories; DELETE FROM shops;"
rm -f _t_admin.php cj_admin.txt
```

- [ ] **Step 3: Commit**

```bash
git add api_expense_handler.php
git commit -m "Add api_expense_handler.php for Expense & Shop Management"
```

---

### Task 3: `expense-categories.php`

**Files:**
- Create: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\expense-categories.php`

**Interfaces:**
- Consumes: `api_expense_handler.php` actions `add_category`,
  `edit_category`, `delete_category`, `add_product`, `edit_product`,
  `delete_product` (Task 2).
- Produces: nothing new consumed elsewhere — this is a leaf page reached
  only via the sidebar link added in Task 6.

Near-verbatim port of Al-Abbas's `manage-categories.php` — the source has
no dependency on the `month` format or denormalized columns, so no logic
adaptation is needed beyond re-branding.

- [ ] **Step 1: Create the file**

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $categories = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM expense_categories c
        LEFT JOIN expense_products p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY c.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $products = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM expense_products p
        JOIN expense_categories c ON p.category_id = c.id
        ORDER BY c.name, p.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Categories & Products | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-primary"><i class="fas fa-tags"></i> Expense Categories & Products</h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>

            <div id="alert-placeholder"></div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expense Categories</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="categoriesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Products</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= $cat['product_count'] ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick='editCategory(<?= json_encode($cat) ?>)' data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick='deleteCategory(<?= $cat["id"] ?>, "<?= addslashes($cat["name"]) ?>")' data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick='openAddProductModal(<?= $cat["id"] ?>, "<?= addslashes($cat["name"]) ?>")' data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fas fa-plus-circle"></i> Add Product
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Products</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Product Name</th>
                                    <th>Company</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['company'] ?? '—') ?></td>
                                    <td><?= number_format($p['current_price'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick='editProduct(<?= json_encode($p) ?>)' data-bs-toggle="modal" data-bs-target="#editProductModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick='deleteProduct(<?= $p["id"] ?>, "<?= addslashes($p["name"]) ?>")' data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal">
    <div class="modal-dialog">
        <form id="addCatForm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5>Add Category</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_category">
                    <input type="text" name="name" class="form-control" placeholder="e.g., Stationery" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal">
    <div class="modal-dialog">
        <form id="editCatForm">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5>Edit Category</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="id" id="editCatId">
                    <div class="mb-3">
                        <label>Category Name</label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal">
    <div class="modal-dialog">
        <form id="addProdForm">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5>Add Product to <span id="prodCatName"></span></h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="category_id" id="prodCatId">
                    <div class="mb-3">
                        <label>Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Company (Optional)</label>
                        <input type="text" name="company" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Price <small class="text-muted">(default price for expense entry)</small></label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal">
    <div class="modal-dialog">
        <form id="editProdForm">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5>Edit Product</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="id" id="editProdId">
                    <div class="mb-3">
                        <label>Product Name</label>
                        <input type="text" name="name" id="editProdName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Company (Optional)</label>
                        <input type="text" name="company" id="editProdCompany" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Price</label>
                        <input type="number" name="price" id="editProdPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5>Confirm Delete</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete <strong id="deleteName"></strong>?
                <input type="hidden" id="deleteId">
                <input type="hidden" id="deleteType">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(() => {
    $('#categoriesTable, #productsTable').DataTable({ pageLength: 25 });
});

function openAddProductModal(id, name) {
    document.getElementById('prodCatId').value = id;
    document.getElementById('prodCatName').textContent = name;
}

function editCategory(category) {
    document.getElementById('editCatId').value = category.id;
    document.getElementById('editCatName').value = category.name;
}

function editProduct(product) {
    document.getElementById('editProdId').value = product.id;
    document.getElementById('editProdName').value = product.name;
    document.getElementById('editProdCompany').value = product.company || '';
    document.getElementById('editProdPrice').value = product.current_price || 0;
}

function deleteCategory(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name + ' category';
    document.getElementById('deleteType').value = 'category';
}

function deleteProduct(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name + ' product';
    document.getElementById('deleteType').value = 'product';
}

['addCatForm', 'editCatForm', 'addProdForm', 'editProdForm'].forEach(id => {
    document.getElementById(id)?.addEventListener('submit', async e => {
        e.preventDefault();
        const data = new FormData(e.target);
        const res = await fetch('api_expense_handler.php', { method: 'POST', body: data });
        const json = await res.json();
        showAlert(json.message, res.ok ? 'success' : 'danger');
        if (res.ok) setTimeout(() => location.reload(), 1000);
    });
});

document.getElementById('confirmDelete').addEventListener('click', async () => {
    const id = document.getElementById('deleteId').value;
    const type = document.getElementById('deleteType').value;

    if (!id || !type) {
        showAlert('Nothing to delete', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_' + type);
    formData.append('id', id);

    try {
        const res = await fetch('api_expense_handler.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        showAlert(json.message, res.ok ? 'success' : 'danger');
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

function showAlert(msg, type) {
    const div = `<div class="alert alert-${type} alert-dismissible fade show"><div>${msg}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alert-placeholder').insertAdjacentHTML('beforeend', div);

    setTimeout(() => {
        const alerts = document.querySelectorAll('#alert-placeholder .alert');
        if (alerts.length > 0) {
            alerts[0].remove();
        }
    }, 5000);
}
</script>
</body>
</html>
```

- [ ] **Step 2: Verify the page loads and CRUD works**

```bash
cat > _t_admin.php << 'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c cj_admin.txt "http://localhost/msst/lms/_t_admin.php" > /dev/null
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-categories.php" -o /tmp/ec.html -w "HTTP %{http_code}\n"
grep -c "Database Error\|Fatal error" /tmp/ec.html
```
Expected: `HTTP 200`, grep count `0`.

Add a category through the page's own AJAX path and confirm it shows up:
```bash
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_category" --data-urlencode "name=Stationery"
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-categories.php" | grep -o "Stationery"
```
Expected: `Stationery` appears in the output.

Clean up: `mysql -u root msst_db -e "DELETE FROM expense_categories WHERE name='Stationery';"`
and `rm -f _t_admin.php cj_admin.txt`.

- [ ] **Step 3: Commit**

```bash
git add expense-categories.php
git commit -m "Add expense-categories.php for category/product CRUD"
```

---

### Task 4: `shop-management.php`

**Files:**
- Create: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\shop-management.php`

**Interfaces:**
- Consumes: `api_expense_handler.php` actions `add_shop`, `edit_shop`,
  `delete_shop`, `get_shop_expenses`, `process_payment` (Task 2).

Near-verbatim port of Al-Abbas's `shop-management.php`. This page has no
dependency on the `month` column at all (its per-shop expense view groups
purely by `shop_id` and formats month for display client-side from the
`date` field), so it ports with only branding/title changes.

- [ ] **Step 1: Create the file**

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $shops = $pdo->query("
        SELECT
            s.*,
            COUNT(e.id) as expense_count,
            COALESCE(SUM(e.amount), 0) as total_expense,
            MAX(e.date) as last_expense_date
        FROM shops s
        LEFT JOIN expenses e ON s.id = e.shop_id
        GROUP BY s.id
        ORDER BY s.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($shops as &$shop) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shop_id = ?");
        $stmt->execute([$shop['id']]);
        $verified_total = $stmt->fetchColumn();
        if (abs($shop['total_expense'] - $verified_total) > 0.01) {
            error_log("Total mismatch for shop {$shop['id']}: {$shop['total_expense']} vs {$verified_total}");
            $shop['total_expense'] = $verified_total;
        }
    }
    unset($shop);
} catch (Exception $e) {
    $shops = [];
    error_log("Error fetching shops: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .shop-table th { background-color: #f8f9fa; font-weight: 600; }
        .badge-count { font-size: 0.8em; padding: 3px 8px; }
        .action-buttons { white-space: nowrap; }
        .table-responsive { border-radius: 8px; border: 1px solid #dee2e6; }
        .total-amount { font-weight: bold; color: #198754; }
        .last-expense { font-size: 0.85em; color: #6c757d; }
        .modal-dialog-scrollable .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
        #shopExpensesTable thead th { background-color: #e9ecef; font-weight: 600; white-space: nowrap; }
        #shopExpensesTable tbody td { vertical-align: middle; }
        #filteredTotal { font-size: 1rem; padding: 8px 12px; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_length { float: left; }
        .dataTables_wrapper .dataTables_filter { float: right; }
        .dataTables_wrapper .dataTables_info { float: left; margin-top: 10px; }
        .dataTables_wrapper .dataTables_paginate { float: right; margin-top: 10px; }
        .dataTables_wrapper:after { content: ""; display: table; clear: both; }
        .dataTables_length select { width: auto; display: inline-block; }
        #verificationWarning { margin-bottom: 15px; }
        .expense-checkbox { cursor: pointer; width: 18px; height: 18px; }
        #selectAllCheckbox { cursor: pointer; width: 18px; height: 18px; }
        .payment-toolbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .badge-unpaid { background-color: #ffc107; color: #000; }
        .badge-paid { background-color: #28a745; color: #fff; }
        .selected-row { background-color: #e3f2fd !important; }
        .btn-group .btn-check:checked + .btn { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800"><i class="fas fa-store"></i> Shop Management</h1>
                <button class="btn btn-primary" onclick="showAddShopModal()">
                    <i class="fas fa-plus"></i> Add New Shop
                </button>
            </div>

            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All Shops</h6>
                    <span class="badge bg-secondary">Total: <?= count($shops) ?> shops</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover shop-table" id="shopsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">Shop Name</th>
                                    <th width="15%">Contact</th>
                                    <th width="20%">Address</th>
                                    <th width="10%" class="text-center">Expenses</th>
                                    <th width="15%" class="text-end">Total Amount</th>
                                    <th width="15%" class="text-center">Last Expense</th>
                                    <th width="15%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shops as $index => $shop): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><i class="fas fa-store text-primary me-1"></i> <?= htmlspecialchars($shop['name']) ?></strong></td>
                                    <td><?= $shop['phone'] ? htmlspecialchars($shop['phone']) : '<span class="text-muted"><i class="fas fa-phone-slash me-1"></i> N/A</span>' ?></td>
                                    <td>
                                        <?php if ($shop['address']): ?>
                                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($shop['address']) ?>">
                                            <?= strlen($shop['address']) > 30 ? substr(htmlspecialchars($shop['address']), 0, 30) . '...' : htmlspecialchars($shop['address']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-info badge-count"><i class="fas fa-receipt me-1"></i> <?= $shop['expense_count'] ?></span></td>
                                    <td class="text-end total-amount" data-total="<?= $shop['total_expense'] ?>">
                                        <?= $shop['total_expense'] > 0 ? 'Rs. ' . number_format($shop['total_expense'], 2) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="text-center last-expense">
                                        <?= $shop['last_expense_date'] ? date('d M Y', strtotime($shop['last_expense_date'])) : '<span class="text-muted">No expenses</span>' ?>
                                    </td>
                                    <td class="text-center action-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewShopExpenses(<?= $shop['id'] ?>, '<?= addslashes($shop['name']) ?>', <?= $shop['total_expense'] ?>)" data-bs-toggle="tooltip" title="View Expenses"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="showEditShopModal(<?= $shop['id'] ?>, '<?= addslashes($shop['name']) ?>', '<?= addslashes($shop['phone'] ?? '') ?>', '<?= addslashes($shop['address'] ?? '') ?>')" data-bs-toggle="tooltip" title="Edit Shop"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteShop(<?= $shop['id'] ?>, '<?= addslashes($shop['name']) ?>')" data-bs-toggle="tooltip" title="Delete Shop"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($shops)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-store fa-2x mb-3 d-block"></i>No shops found. Click "Add New Shop" to add your first shop!</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="4" class="text-end">Grand Totals:</th>
                                    <th class="text-center"><span class="badge bg-primary"><?= array_sum(array_column($shops, 'expense_count')) ?> expenses</span></th>
                                    <th class="text-end"><strong>Rs. <?= number_format(array_sum(array_column($shops, 'total_expense')), 2) ?></strong></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>

<!-- Add Shop Modal -->
<div class="modal fade" id="addShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Shop</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addShopForm">
                    <div class="mb-3">
                        <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g., 03001234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Shop address..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveShop()"><i class="fas fa-save"></i> Save Shop</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Shop Modal -->
<div class="modal fade" id="editShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editShopForm">
                    <input type="hidden" name="action" value="edit_shop">
                    <input type="hidden" name="id" id="edit_shop_id">
                    <div class="mb-3">
                        <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_shop_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="edit_shop_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_shop_address" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updateShop()"><i class="fas fa-save"></i> Update Shop</button>
            </div>
        </div>
    </div>
</div>

<!-- Shop Expenses Modal -->
<div class="modal fade" id="shopExpensesModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shopExpensesTitle"><i class="fas fa-receipt"></i> Shop Expenses</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="verificationWarning"></div>

                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-credit-card"></i> Payment Management</span>
                            <div>
                                <button class="btn btn-sm btn-light" onclick="toggleAllRows()" id="toggleAllBtn">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="filterPaid" id="filterAll" autocomplete="off" checked onclick="filterPaidStatus('all')">
                                    <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>

                                    <input type="radio" class="btn-check" name="filterPaid" id="filterUnpaid" autocomplete="off" onclick="filterPaidStatus('unpaid')">
                                    <label class="btn btn-outline-warning btn-sm" for="filterUnpaid">Unpaid</label>

                                    <input type="radio" class="btn-check" name="filterPaid" id="filterPaid" autocomplete="off" onclick="filterPaidStatus('paid')">
                                    <label class="btn btn-outline-success btn-sm" for="filterPaid">Paid</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="startDate" placeholder="Start Date">
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" id="endDate" placeholder="End Date">
                                    <button class="btn btn-primary btn-sm" onclick="applyDateFilter()" title="Apply Filter"><i class="fas fa-filter"></i></button>
                                    <button class="btn btn-secondary btn-sm" onclick="clearDateFilter()" title="Clear Filter"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-success fs-6 p-2" id="filteredTotal">Total: Rs. 0.00</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-warning text-dark fs-6 p-2" id="unpaidTotal">Unpaid: Rs. 0.00</span>
                            </div>
                        </div>

                        <div class="row mt-2" id="paymentActions" style="display: none;">
                            <div class="col-md-12">
                                <div class="alert alert-info p-2 mb-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-check-circle"></i>
                                            <strong id="selectedCount">0</strong> items selected
                                            (Total: Rs. <strong id="selectedTotal">0.00</strong>)
                                        </span>
                                        <div>
                                            <button class="btn btn-sm btn-success me-2" onclick="showPaymentModal()">
                                                <i class="fas fa-check-circle"></i> Mark as Paid
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                                                <i class="fas fa-times"></i> Clear Selection
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="shopExpensesTable" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAllCheckbox" onclick="toggleAllCheckboxes(this)">
                                </th>
                                <th width="3%">S.No</th>
                                <th width="8%">Date</th>
                                <th width="8%">Bill No</th>
                                <th width="12%">Product</th>
                                <th width="5%" class="text-center">Qty</th>
                                <th width="8%" class="text-end">Unit Price</th>
                                <th width="8%" class="text-end">Amount</th>
                                <th width="8%">C.N</th>
                                <th width="5%">Month</th>
                                <th width="8%">Category</th>
                                <th width="8%">Status</th>
                            </tr>
                        </thead>
                        <tbody id="shopExpensesBody">
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="5" class="text-end">Totals:</th>
                                <th id="totalQuantity" class="text-center">0.00</th>
                                <th></th>
                                <th id="totalAmount" class="text-end">Rs. 0.00</th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="printExpenses()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Process Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="shop_id" id="payment_shop_id">
                    <div id="selectedItemsList"></div>

                    <div class="mb-3">
                        <label class="form-label">Selected Items Summary</label>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Items:</span>
                                    <strong id="paymentItemsCount">0</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Amount:</span>
                                    <strong id="paymentTotalAmount">Rs. 0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cheque Number <span class="text-danger">*</span></label>
                        <input type="text" name="cn" class="form-control" required placeholder="Enter cheque number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processPayment()">
                    <i class="fas fa-check-circle"></i> Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
let shopExpensesData = [];
let currentShopId = null;
let currentShopName = '';
let expectedTotal = 0;
let expensesDataTable = null;
let selectedExpenses = new Set();

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#shopsTable')) {
        $('#shopsTable').DataTable().destroy();
    }
    $('#shopsTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 7] },
            { searchable: false, targets: [0, 4, 5, 6, 7] }
        ],
        language: {
            search: "Search shops:",
            lengthMenu: "Show _MENU_ shops per page",
            info: "Showing _START_ to _END_ of _TOTAL_ shops",
            infoEmpty: "Showing 0 to 0 of 0 shops",
            infoFiltered: "(filtered from _MAX_ total shops)",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        }
    });

    $('[data-bs-toggle="tooltip"]').each(function() {
        new bootstrap.Tooltip(this);
    });
});

function showAddShopModal() {
    document.getElementById('addShopForm').reset();
    new bootstrap.Modal(document.getElementById('addShopModal')).show();
}

function saveShop() {
    const form = document.getElementById('addShopForm');
    const formData = new FormData(form);
    formData.append('action', 'add_shop');

    const saveBtn = document.querySelector('#addShopModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('addShopModal')).hide();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Error: ' + data.message);
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showEditShopModal(id, name, phone, address) {
    document.getElementById('edit_shop_id').value = id;
    document.getElementById('edit_shop_name').value = name;
    document.getElementById('edit_shop_phone').value = phone || '';
    document.getElementById('edit_shop_address').value = address || '';
    new bootstrap.Modal(document.getElementById('editShopModal')).show();
}

function updateShop() {
    const form = document.getElementById('editShopForm');
    const formData = new FormData(form);

    const updateBtn = document.querySelector('#editShopModal .btn-warning');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    updateBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('editShopModal')).hide();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Error: ' + data.message);
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    });
}

function deleteShop(id, name) {
    if (!confirm(`Are you sure you want to delete shop "${name}"?\n\nNote: This will only work if no expenses are linked to this shop.`)) return;

    const formData = new FormData();
    formData.append('action', 'delete_shop');
    formData.append('id', id);

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Cannot delete: ' + data.message);
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

function viewShopExpenses(shopId, shopName, expectedTotalAmount) {
    currentShopId = shopId;
    currentShopName = shopName;
    expectedTotal = parseFloat(expectedTotalAmount) || 0;

    document.getElementById('shopExpensesTitle').innerHTML =
        `<i class="fas fa-receipt"></i> Expenses: ${shopName} (Expected: Rs. ${expectedTotal.toFixed(2)})`;

    shopExpensesData = [];
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    clearSelection();

    document.getElementById('shopExpensesBody').innerHTML =
        '<tr><td colspan="12" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> Loading expenses...</td></tr>';

    document.getElementById('totalQuantity').innerText = '0.00';
    document.getElementById('totalAmount').innerHTML = 'Rs. 0.00';
    document.getElementById('filteredTotal').innerHTML = 'Total: Rs. 0.00';
    document.getElementById('unpaidTotal').innerHTML = 'Unpaid: Rs. 0.00';
    document.getElementById('verificationWarning').innerHTML = '';

    if (expensesDataTable) {
        expensesDataTable.destroy();
        expensesDataTable = null;
    }
    if ($.fn.DataTable.isDataTable('#shopExpensesTable')) {
        $('#shopExpensesTable').DataTable().destroy();
    }
    $('#shopExpensesBody').empty();

    fetch(`api_expense_handler.php?action=get_shop_expenses&shop_id=${shopId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(expenses => {
            if (!Array.isArray(expenses)) {
                document.getElementById('shopExpensesBody').innerHTML =
                    '<tr><td colspan="12" class="text-center text-danger py-4">Invalid data format received</td></tr>';
                return;
            }

            const uniqueExpenses = [];
            const seenIds = new Set();
            expenses.forEach(expense => {
                if (expense.id && !seenIds.has(expense.id)) {
                    seenIds.add(expense.id);
                    uniqueExpenses.push(expense);
                } else if (!expense.id) {
                    const key = `${expense.date}_${expense.amount}_${expense.product_name}`;
                    if (!seenIds.has(key)) {
                        seenIds.add(key);
                        uniqueExpenses.push(expense);
                    }
                }
            });

            shopExpensesData = uniqueExpenses;

            const fetchedTotal = uniqueExpenses.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);

            const diff = Math.abs(fetchedTotal - expectedTotal);
            if (diff > 0.01) {
                const warningHtml = `
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Warning!</strong> Total mismatch!
                        Expected: Rs. ${expectedTotal.toFixed(2)},
                        Fetched: Rs. ${fetchedTotal.toFixed(2)}.
                        Difference: Rs. ${(expectedTotal - fetchedTotal).toFixed(2)}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                document.getElementById('verificationWarning').innerHTML = warningHtml;
            }

            if (uniqueExpenses.length === 0) {
                document.getElementById('shopExpensesBody').innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No expenses found
                        </td>
                    </tr>`;
            } else {
                initializeExpensesDataTable(uniqueExpenses);
            }

            new bootstrap.Modal(document.getElementById('shopExpensesModal')).show();
        })
        .catch(error => {
            document.getElementById('shopExpensesBody').innerHTML =
                '<tr><td colspan="12" class="text-center text-danger py-4">Error loading expenses</td></tr>';
        });
}

function initializeExpensesDataTable(expenses) {
    expenses.sort((a, b) => new Date(b.date) - new Date(a.date));

    const tableData = expenses.map((expense, index) => {
        const amount = parseFloat(expense.amount) || 0;
        const quantity = parseFloat(expense.quantity) || 1;
        const unitPrice = parseFloat(expense.unit_price) || (quantity > 0 ? amount / quantity : amount);
        const isPaid = expense.is_paid == 1 || expense.is_paid == true;

        let formattedDate = '—';
        try {
            if (expense.date) {
                const dateObj = new Date(expense.date);
                if (!isNaN(dateObj.getTime())) {
                    formattedDate = dateObj.toLocaleDateString('en-GB');
                }
            }
        } catch (e) {}

        let month = '—';
        try {
            if (expense.date) {
                const dateObj = new Date(expense.date);
                if (!isNaN(dateObj.getTime())) {
                    month = dateObj.toLocaleString('default', { month: 'long', year: 'numeric' });
                }
            }
        } catch (e) {}

        return {
            id: expense.id,
            sno: index + 1,
            date: formattedDate,
            rawDate: expense.date,
            bill_no: expense.bill_no || '—',
            product_name: expense.product_name || '—',
            quantity: quantity,
            unit_price: unitPrice,
            amount: amount,
            rawAmount: amount,
            cn: expense.cn || '—',
            month: month,
            category: expense.category_name || '—',
            is_paid: isPaid
        };
    });

    updateTotals(tableData);

    const unpaidTotal = tableData.reduce((sum, item) => sum + (item.is_paid ? 0 : item.amount), 0);
    document.getElementById('unpaidTotal').innerHTML = `Unpaid: Rs. ${unpaidTotal.toFixed(2)}`;

    if (expensesDataTable) {
        expensesDataTable.destroy();
    }

    expensesDataTable = $('#shopExpensesTable').DataTable({
        data: tableData,
        columns: [
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    return `<input type="checkbox" class="expense-checkbox" value="${row.id}" onchange="toggleCheckbox(this, ${row.id})" ${selectedExpenses.has(row.id.toString()) ? 'checked' : ''}>`;
                },
                orderable: false
            },
            { data: 'sno' },
            { data: 'date' },
            { data: 'bill_no' },
            { data: 'product_name' },
            {
                data: 'quantity',
                className: 'text-center',
                render: data => data.toFixed(2)
            },
            {
                data: 'unit_price',
                className: 'text-end',
                render: data => 'Rs. ' + data.toFixed(2)
            },
            {
                data: 'amount',
                className: 'text-end',
                render: data => '<strong>Rs. ' + data.toFixed(2) + '</strong>'
            },
            {
                data: 'cn',
                render: function(data) {
                    return data && data != '—' ? data : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'month',
                render: data => '<span class="badge bg-secondary">' + data + '</span>'
            },
            { data: 'category' },
            {
                data: 'is_paid',
                className: 'text-center',
                render: function(data) {
                    return data ?
                        '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>' :
                        '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Unpaid</span>';
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[2, 'desc']],
        destroy: true,
        language: {
            search: "Search expenses:",
            lengthMenu: "Show _MENU_ expenses per page",
            info: "Showing _START_ to _END_ of _TOTAL_ expenses",
            infoEmpty: "Showing 0 to 0 of 0 expenses",
            infoFiltered: "(filtered from _MAX_ total expenses)",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        },
        drawCallback: function(settings) {
            const filteredData = this.api().rows({ filter: 'applied' }).data().toArray();
            updateTotals(filteredData);

            const filteredUnpaidTotal = filteredData.reduce((sum, item) => sum + (item.is_paid ? 0 : item.amount), 0);
            document.getElementById('unpaidTotal').innerHTML = `Unpaid: Rs. ${filteredUnpaidTotal.toFixed(2)}`;

            document.querySelectorAll('.expense-checkbox').forEach(cb => {
                const id = cb.value;
                cb.checked = selectedExpenses.has(id.toString());
            });

            const checkboxes = document.querySelectorAll('.expense-checkbox');
            const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
            document.getElementById('selectAllCheckbox').checked = allChecked;

            updatePaymentActions();
            updateToggleButtonText();
        }
    });
}

function toggleAllRows() {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        const id = cb.value;
        if (cb.checked) {
            selectedExpenses.add(id);
        } else {
            selectedExpenses.delete(id);
        }
    });

    document.getElementById('selectAllCheckbox').checked = !allChecked;
    updatePaymentActions();
    updateToggleButtonText();
}

function toggleCheckbox(checkbox, id) {
    if (checkbox.checked) {
        selectedExpenses.add(id.toString());
    } else {
        selectedExpenses.delete(id.toString());
        document.getElementById('selectAllCheckbox').checked = false;
    }
    updatePaymentActions();
    updateToggleButtonText();
}

function toggleAllCheckboxes(headerCheckbox) {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = headerCheckbox.checked;
        const id = cb.value;
        if (headerCheckbox.checked) {
            selectedExpenses.add(id.toString());
        } else {
            selectedExpenses.delete(id.toString());
        }
    });
    updatePaymentActions();
    updateToggleButtonText();
}

function updateToggleButtonText() {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
    const toggleBtn = document.getElementById('toggleAllBtn');
    if (toggleBtn) {
        toggleBtn.innerHTML = allChecked ?
            '<i class="fas fa-times"></i> Deselect All' :
            '<i class="fas fa-check-double"></i> Select All';
    }
}

function updatePaymentActions() {
    const selectedCount = selectedExpenses.size;
    const paymentActions = document.getElementById('paymentActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');

    if (selectedCount > 0) {
        paymentActions.style.display = 'block';
        selectedCountSpan.textContent = selectedCount;

        let total = 0;
        if (expensesDataTable) {
            const allData = expensesDataTable.rows().data().toArray();
            selectedExpenses.forEach(id => {
                const expense = allData.find(item => item.id == id);
                if (expense) {
                    total += expense.amount || 0;
                }
            });
        }
        selectedTotalSpan.textContent = total.toFixed(2);
    } else {
        paymentActions.style.display = 'none';
    }
}

function clearSelection() {
    selectedExpenses.clear();
    document.querySelectorAll('.expense-checkbox').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updatePaymentActions();
    updateToggleButtonText();
}

function filterPaidStatus(status) {
    if (!expensesDataTable) return;

    $.fn.dataTable.ext.search.pop();

    if (status !== 'all') {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const rowData = expensesDataTable.row(dataIndex).data();
            const isPaid = rowData.is_paid == 1 || rowData.is_paid == true;
            return status === 'paid' ? isPaid : !isPaid;
        });
    }

    expensesDataTable.draw();
}

function applyDateFilter() {
    if (!expensesDataTable) return;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
        alert('Start date cannot be after end date');
        return;
    }

    $.fn.dataTable.ext.search.pop();

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (!startDate && !endDate) return true;

        const rowDate = data[2];
        let rowDateObj;

        try {
            const parts = rowDate.split('/');
            if (parts.length === 3) {
                rowDateObj = new Date(parts[2], parts[1] - 1, parts[0]);
            } else {
                rowDateObj = new Date(rowDate);
            }
            if (isNaN(rowDateObj.getTime())) return true;
        } catch (e) {
            return true;
        }

        const start = startDate ? new Date(startDate) : null;
        const end = endDate ? new Date(endDate) : null;

        if (start) start.setHours(0,0,0,0);
        if (end) end.setHours(23,59,59,999);

        if (start && end) return rowDateObj >= start && rowDateObj <= end;
        if (start) return rowDateObj >= start;
        if (end) return rowDateObj <= end;

        return true;
    });

    expensesDataTable.draw();
}

function clearDateFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    if (expensesDataTable) {
        $.fn.dataTable.ext.search.pop();
        expensesDataTable.draw();
    }
}

function showPaymentModal() {
    if (selectedExpenses.size === 0) {
        alert('Please select at least one expense to mark as paid');
        return;
    }

    document.getElementById('payment_shop_id').value = currentShopId;

    let totalAmount = 0;
    let itemsList = '<div class="mb-2"><small class="text-muted">Selected items:</small></div>';

    if (expensesDataTable) {
        const allData = expensesDataTable.rows().data().toArray();
        const selectedIds = Array.from(selectedExpenses);

        selectedIds.forEach(id => {
            const expense = allData.find(item => item.id == id);
            if (expense) {
                totalAmount += expense.amount || 0;
                itemsList += `<input type="hidden" name="expense_ids[]" value="${id}">`;

                if (expense.product_name) {
                    itemsList += `<div class="small text-muted">• ${expense.product_name} - Rs. ${(expense.amount || 0).toFixed(2)}</div>`;
                }
            }
        });
    }

    document.getElementById('selectedItemsList').innerHTML = itemsList;
    document.getElementById('paymentItemsCount').textContent = selectedExpenses.size;
    document.getElementById('paymentTotalAmount').textContent = 'Rs. ' + totalAmount.toFixed(2);

    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function processPayment() {
    const form = document.getElementById('paymentForm');
    const chequeNumber = form.querySelector('[name="cn"]').value;

    if (!chequeNumber) {
        alert('Please enter cheque number');
        return;
    }

    const formData = new FormData(form);

    const paymentBtn = document.querySelector('#paymentModal .btn-success');
    const originalText = paymentBtn.innerHTML;
    paymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    paymentBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            clearSelection();
            viewShopExpenses(currentShopId, currentShopName, expectedTotal);
        } else {
            alert('Error: ' + data.message);
            paymentBtn.innerHTML = originalText;
            paymentBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        paymentBtn.innerHTML = originalText;
        paymentBtn.disabled = false;
    });
}

function updateTotals(data) {
    let totalAmount = 0, totalQuantity = 0;
    data.forEach(item => {
        totalAmount += item.rawAmount || item.amount || 0;
        totalQuantity += item.quantity || 1;
    });
    document.getElementById('totalQuantity').innerText = totalQuantity.toFixed(2);
    document.getElementById('totalAmount').innerHTML = 'Rs. ' + totalAmount.toFixed(2);
    document.getElementById('filteredTotal').innerHTML = 'Total: Rs. ' + totalAmount.toFixed(2);
}

function printExpenses() {
    const title = document.getElementById('shopExpensesTitle').innerText;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    let dateRangeText = '';

    if (startDate && endDate) {
        dateRangeText = ` (${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()})`;
    } else if (startDate) {
        dateRangeText = ` (From ${new Date(startDate).toLocaleDateString()})`;
    } else if (endDate) {
        dateRangeText = ` (Until ${new Date(endDate).toLocaleDateString()})`;
    }

    const filteredData = expensesDataTable ?
        expensesDataTable.rows({ filter: 'applied' }).data().toArray() :
        shopExpensesData;

    const totalAmount = filteredData.reduce((sum, item) => sum + (item.rawAmount || item.amount || 0), 0);
    const totalQuantity = filteredData.reduce((sum, item) => sum + (item.quantity || 1), 0);

    let tableHTML = `
        <table style="width:100%; border-collapse: collapse; margin-top: 20px; font-size: 12px;">
            <thead style="background-color: #f8f9fa;">
                <tr>
                    <th style="border:1px solid #ddd; padding:8px;">S.No</th>
                    <th style="border:1px solid #ddd; padding:8px;">Date</th>
                    <th style="border:1px solid #ddd; padding:8px;">Bill No</th>
                    <th style="border:1px solid #ddd; padding:8px;">Product</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:center;">Qty</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:right;">Unit Price</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:right;">Amount</th>
                    <th style="border:1px solid #ddd; padding:8px;">C.N</th>
                    <th style="border:1px solid #ddd; padding:8px;">Month</th>
                    <th style="border:1px solid #ddd; padding:8px;">Category</th>
                    <th style="border:1px solid #ddd; padding:8px;">Status</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredData.forEach((item, index) => {
        const amount = item.rawAmount || item.amount || 0;
        const quantity = item.quantity || 1;
        const unitPrice = item.unit_price || (quantity > 0 ? amount / quantity : amount);
        const status = item.is_paid ? 'Paid' : 'Unpaid';
        const statusColor = item.is_paid ? '#28a745' : '#ffc107';

        tableHTML += `
            <tr>
                <td style="border:1px solid #ddd; padding:8px;">${index + 1}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.date || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.bill_no || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.product_name || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:center;">${quantity.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:right;">Rs. ${unitPrice.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:right;">Rs. ${amount.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.cn || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.month ? item.month.replace(/<[^>]*>/g, '') : '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.category || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px; background-color: ${statusColor}; color: ${item.is_paid ? 'white' : 'black'}; text-align:center;">${status}</td>
            </tr>
        `;
    });

    tableHTML += `
            </tbody>
        </table>
        <div style="margin-top:30px; padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:5px; font-weight:bold; text-align:right;">
            <div>Total Items: ${totalQuantity.toFixed(2)}</div>
            <div>Total Amount: Rs. ${totalAmount.toFixed(2)}</div>
        </div>
    `;

    let printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>${title}${dateRangeText}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h2 { color: #333; }
                @media print {
                    body { margin: 0.5in; }
                }
            </style>
        </head>
        <body>
            <h2>${title}</h2>
            <p>${dateRangeText || 'All expenses'} | Printed: ${new Date().toLocaleString()}</p>
            ${tableHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>
</body>
</html>
```

- [ ] **Step 2: Verify end-to-end via curl + real data**

```bash
cat > _t_admin.php << 'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c cj_admin.txt "http://localhost/msst/lms/_t_admin.php" > /dev/null

# Seed one category, product, shop, and expense so the page has data to show
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_category" --data-urlencode "name=Verify Cat"
CAT_ID=$(mysql -u root msst_db -N -e "SELECT id FROM expense_categories WHERE name='Verify Cat';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_product" --data-urlencode "category_id=$CAT_ID" \
  --data-urlencode "name=Verify Product" --data-urlencode "price=50"
PROD_ID=$(mysql -u root msst_db -N -e "SELECT id FROM expense_products WHERE name='Verify Product';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_shop" --data-urlencode "name=Verify Shop" --data-urlencode "phone=0300"
SHOP_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shops WHERE name='Verify Shop';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add" --data-urlencode "bill_no=EXP-VERIFY-1" \
  --data-urlencode "date=2026-07-10" --data-urlencode "shop_id=$SHOP_ID" \
  --data-urlencode "product_id=$PROD_ID" --data-urlencode "quantity=1" --data-urlencode "unit_price=50"

curl -s -b cj_admin.txt "http://localhost/msst/lms/shop-management.php" -o /tmp/sm.html -w "HTTP %{http_code}\n"
grep -c "Database Error\|Fatal error" /tmp/sm.html
grep -o "Verify Shop" /tmp/sm.html | head -1
grep -o "Rs\. 50.00" /tmp/sm.html | head -1
```
Expected: `HTTP 200`, grep count `0`, `Verify Shop` and `Rs. 50.00` both found
(confirms the shop listing query and total-verification logic work).

```bash
# Confirm the per-shop expense view endpoint returns the seeded row
curl -s -b cj_admin.txt "http://localhost/msst/lms/api_expense_handler.php?action=get_shop_expenses&shop_id=$SHOP_ID"
```
Expected: JSON array with one object, `bill_no: "EXP-VERIFY-1"`,
`product_name: "Verify Product"`, `category_name: "Verify Cat"`.

Clean up:
```bash
mysql -u root msst_db -e "DELETE FROM expenses WHERE bill_no='EXP-VERIFY-1'; DELETE FROM shops WHERE name='Verify Shop'; DELETE FROM expense_products WHERE name='Verify Product'; DELETE FROM expense_categories WHERE name='Verify Cat';"
rm -f _t_admin.php cj_admin.txt
```

- [ ] **Step 3: Commit**

```bash
git add shop-management.php
git commit -m "Add shop-management.php: vendor directory, per-shop ledger, bulk payments"
```

---

### Task 5: `expense-management.php` + `assets/js/expense-management.js`

**Files:**
- Modify: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\expense-management.php` (created in Task 1 with only the schema patcher — this task replaces the temporary body with the full page)
- Create: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\assets\js\expense-management.js`

**Interfaces:**
- Consumes: `api_expense_handler.php` actions `add`, `update`, `delete`,
  `get_next_bill`, `get_products`, `get_shops`, `get_product_price`,
  `get_all_product_prices` (Task 2).

This is the main expense ledger — the largest and most-adapted page. Two
deliberate departures from the Al-Abbas source, per the spec:
1. `month` is computed/filtered as `'Y-m'` everywhere (both in the PHP
   page's own queries and in the JS's cosmetic month display).
2. The "custom summary items" feature (`expense_summary_items` table,
   `summary-items.php` link) is dropped entirely — the summary card only
   shows real monthly totals from `expenses`, no custom line items, no
   "Edit Summary Items" button. There is also no `pn` column in MSST's
   `expenses` table (the source referenced `$e['pn']` in its print view
   but that column doesn't exist in Task 1's schema — that print column
   is dropped here too).

- [ ] **Step 1: Replace `expense-management.php`'s temporary body with the full page**

Keep the exact auth-check + auto-patcher block from Task 1 unchanged at
the top of the file. Replace everything from `echo 'Schema patched OK';`
onward with:

```php
// SEPARATE FILTERS FOR SUMMARY AND EXPENSE DETAILS

// 1. Summary filter (month range)
$summary_start = $_GET['summary_start'] ?? date('Y-01');
$summary_end = $_GET['summary_end'] ?? date('Y-m');

// 2. Expense details filter (date range)
$details_start = $_GET['details_start'] ?? date('Y-m-01', strtotime('-1 month'));
$details_end = $_GET['details_end'] ?? date('Y-m-t');

// 3. Payment status filter
$payment_filter = $_GET['payment_filter'] ?? 'all';

try {
    // 1. Expenses for selected DATE range with payment status filter
    $sql = "
        SELECT e.*, p.name AS product_name, p.company, p.current_price,
               c.name AS category_name, s.name as shop, s.phone
        FROM expenses e
        LEFT JOIN expense_products p ON e.product_id = p.id
        LEFT JOIN expense_categories c ON p.category_id = c.id
        LEFT JOIN shops s ON e.shop_id = s.id
        WHERE e.date BETWEEN ? AND ?
    ";

    $params = [$details_start, $details_end];

    if ($payment_filter == 'paid') {
        $sql .= " AND e.is_paid = 1";
    } elseif ($payment_filter == 'unpaid') {
        $sql .= " AND e.is_paid = 0";
    }

    $sql .= " ORDER BY e.date, e.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Monthly totals for SUMMARY (uses month range)
    $stmt_range = $pdo->prepare("
        SELECT
            DATE_FORMAT(date, '%M,%Y') as month_year,
            DATE_FORMAT(date, '%M-%Y') as ref,
            SUM(amount) as total
        FROM expenses
        WHERE DATE_FORMAT(date, '%Y-%m') BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY date
    ");
    $stmt_range->execute([$summary_start, $summary_end]);
    $monthly_totals = $stmt_range->fetchAll(PDO::FETCH_ASSOC);

    // 3. Grand total (for summary)
    $grand_total = array_sum(array_column($monthly_totals, 'total'));

    // 4. Categories for dropdowns
    $categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Next bill no
    $last = $pdo->query("SELECT bill_no FROM expenses ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_serial = $last ? (int)substr($last, 3) + 1 : 1001;
    $next_bill_no = "EXP" . $next_serial;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Expenses | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .no-print { }
        @media print {
            .no-print { display: none !important; }
            body { margin: 15px; font-family: Arial, sans-serif; }
            table { font-size: 11px; width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 4px; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .print-only { display: block !important; }
            #printable, #single-print { width: 100%; }
            h5 { margin: 8px 0; font-size: 14px; }
            .signature { margin-top: 30px; display: flex; justify-content: space-between; }
            .invoice-box { border: 1px solid #000; padding: 15px; margin: 20px 0; }
            .invoice-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        }
        .print-only { display: none; }
        .badge-paid { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8em; display: inline-block; }
        .badge-unpaid { background-color: #ffc107; color: black; padding: 5px 10px; border-radius: 20px; font-size: 0.8em; display: inline-block; }
        .payment-filter { margin-left: 10px; }
        .payment-filter .btn-check:checked + .btn { background-color: #0d6efd; color: white; }
        .cn-badge { background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; display: inline-block; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h1 class="h3 text-success"><i class="fas fa-money-bill-wave"></i> Manage Expenses</h1>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-success" onclick="printSummaryOnly()">
                        <i class="fas fa-file-invoice"></i> Print Summary Only
                    </button>
                    <button class="btn btn-outline-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Full Report
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
                </div>
            </div>

            <div id="alert-placeholder"></div>

            <!-- SUMMARY CARD -->
            <div class="card shadow mt-4 no-print">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Summary <?= date('M Y', strtotime($summary_start)) ?> to <?= date('M Y', strtotime($summary_end)) ?>
                    </h6>
                    <form method="GET" class="d-flex gap-2">
                        <input type="month" name="summary_start" value="<?= $summary_start ?>" class="form-control form-control-sm" required>
                        <input type="month" name="summary_end" value="<?= $summary_end ?>" class="form-control form-control-sm" required>
                        <input type="hidden" name="details_start" value="<?= $details_start ?>">
                        <input type="hidden" name="details_end" value="<?= $details_end ?>">
                        <button class="btn btn-sm btn-primary w-100">Update Summary</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Amount</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_totals as $m): ?>
                                <tr>
                                    <td><?= $m['month_year'] ?></td>
                                    <td class="text-end"><?= number_format($m['total']) ?></td>
                                    <td><?= $m['ref'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-danger fw-bold">
                                    <td>Total Expense</td>
                                    <td class="text-end"><?= number_format($grand_total) ?></td>
                                    <td>Summary</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- EXPENSES TABLE -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center no-print">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Expenses from <?= date('d M Y', strtotime($details_start)) ?> to <?= date('d M Y', strtotime($details_end)) ?>
                        <span class="badge bg-success ms-2"><?= count($expenses) ?></span>
                    </h6>
                    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="me-2 small">From:</label>
                        <input type="date" name="details_start" class="form-control form-control-sm"
                               value="<?= $details_start ?>" style="width:150px">
                        <label class="me-2 small">To:</label>
                        <input type="date" name="details_end" class="form-control form-control-sm"
                               value="<?= $details_end ?>" style="width:150px">

                        <div class="btn-group" role="group" style="margin-left: 10px;">
                            <input type="radio" class="btn-check" name="payment_filter" id="filterAll"
                                   value="all" <?= ($payment_filter ?? 'all') == 'all' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>

                            <input type="radio" class="btn-check" name="payment_filter" id="filterUnpaid"
                                   value="unpaid" <?= ($payment_filter ?? '') == 'unpaid' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-warning btn-sm" for="filterUnpaid">Unpaid</label>

                            <input type="radio" class="btn-check" name="payment_filter" id="filterPaid"
                                   value="paid" <?= ($payment_filter ?? '') == 'paid' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-success btn-sm" for="filterPaid">Paid</label>
                        </div>

                        <input type="hidden" name="summary_start" value="<?= $summary_start ?>">
                        <input type="hidden" name="summary_end" value="<?= $summary_end ?>">

                        <button class="btn btn-sm btn-primary">Filter</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="expensesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No</th>
                                    <th>Bill No</th>
                                    <th>Date</th>
                                    <th>Shop</th>
                                    <th>Phone</th>
                                    <th>Qty</th>
                                    <th>Rs.</th>
                                    <th>Balance</th>
                                    <th>Category</th>
                                    <th>Products</th>
                                    <th>C.N</th>
                                    <th>Status</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $running_balance = 0;
                                $sno = 1;
                                foreach ($expenses as $e):
                                    $running_balance += $e['amount'];
                                    $is_paid = $e['is_paid'] ?? 0;
                                    $cn_number = $e['cn'] ?? '';
                                ?>
                                <tr>
                                    <td><?= $sno++ ?></td>
                                    <td><code><?= $e['bill_no'] ?></code></td>
                                    <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                    <td><?= htmlspecialchars($e['shop'] ?? 'N/A') ?></td>
                                    <td><?= $e['phone'] ?: '<em>N/A</em>' ?></td>
                                    <td class="text-center"><?= $e['quantity'] ?? 1 ?></td>
                                    <td class="text-end text-danger fw-bold"><?= number_format($e['amount']) ?></td>
                                    <td class="text-end"><?= number_format($e['balance']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($e['category_name'] ?? '—') ?></span></td>
                                    <td><?= htmlspecialchars(($e['product_name'] ?? '') . ($e['company'] ? ' ('.$e['company'].')' : '')) ?></td>
                                    <td>
                                        <?php if ($cn_number): ?>
                                            <span class="cn-badge"><?= htmlspecialchars($cn_number) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-paid">Paid</span>
                                        <?php else: ?>
                                            <span class="badge-unpaid">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <button class="btn btn-sm btn-primary" onclick="printSingle2(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" title="Print Invoice">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editExpense2(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" data-bs-toggle="modal" data-bs-target="#editModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteExpense2(<?= $e['id'] ?>, '<?= addslashes($e['bill_no']) ?>')" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PRINTABLE CONTENT -->
            <div id="printable" class="print-only">
                <div id="summary-page">
                    <div class="text-center mb-4">
                        <h5>Muhaddisa School of Science and Technology — Expense Summary</h5>
                    </div>
                    <div class="text-center fw-bold mb-4">
                        Summary <?= date('M Y', strtotime($summary_start)) ?> to <?= date('M Y', strtotime($summary_end)) ?>
                    </div>

                    <table style="font-size:11px; width:100%; border-collapse:collapse; margin-bottom:30px;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="border:1px solid #000; padding:5px;">Month</th>
                                <th style="border:1px solid #000; padding:5px; text-align:right;">Amount</th>
                                <th style="border:1px solid #000; padding:5px;">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_totals as $m): ?>
                            <tr>
                                <td style="border:1px solid #000; padding:5px;"><?= $m['month_year'] ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($m['total']) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $m['ref'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold; background:#f8d7da;">
                                <td style="border:1px solid #000; padding:5px;">Total Expense</td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($grand_total) ?></td>
                                <td style="border:1px solid #000; padding:5px;">Summary</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="page-break-before: always;">
                    <div class="text-center mb-3">
                        <h5>Muhaddisa School of Science and Technology — Expense Detail Report</h5>
                    </div>
                    <table style="font-size:11px; width:100%; border-collapse:collapse;">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="border:1px solid #000; padding:5px;">S.No</th>
                                <th style="border:1px solid #000; padding:5px;">Bill No</th>
                                <th style="border:1px solid #000; padding:5px;">Date</th>
                                <th style="border:1px solid #000; padding:5px;">Shop</th>
                                <th style="border:1px solid #000; padding:5px;">Phone</th>
                                <th style="border:1px solid #000; padding:5px;">Qty</th>
                                <th style="border:1px solid #000; padding:5px;">Rs.</th>
                                <th style="border:1px solid #000; padding:5px;">Balance</th>
                                <th style="border:1px solid #000; padding:5px;">Category</th>
                                <th style="border:1px solid #000; padding:5px;">Items</th>
                                <th style="border:1px solid #000; padding:5px;">C.N</th>
                                <th style="border:1px solid #000; padding:5px;">Status</th>
                                <th style="border:1px solid #000; padding:5px;">Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($expenses as $i => $e):
                                $is_paid = $e['is_paid'] ?? 0;
                                $cn_number = $e['cn'] ?? '';
                            ?>
                            <tr>
                                <td style="border:1px solid #000; padding:5px;"><?= $i + 1 ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['bill_no'] ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= htmlspecialchars($e['shop'] ?? 'N/A') ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['phone'] ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:center;"><?= $e['quantity'] ?? 1 ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($e['amount']) ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($e['balance']) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['category_name'] ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['product_name'] . ($e['company'] ? ' ('.$e['company'].')' : '') ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $cn_number ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:center;"><?= $is_paid ? 'Paid' : 'Unpaid' ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['month'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="single-print" class="print-only" style="display:none;"></div>

        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-lg">
        <form id="addForm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5>Add Expense</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Bill No</label>
                            <input type="text" name="bill_no" class="form-control" value="<?= $next_bill_no ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>Date</label>
                            <input type="date" name="date" id="dateInput" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label>Shop</label>
                            <select name="shop_id" id="shopSelect" class="form-select" onchange="updateShopDetails()" required>
                                <option value="">Select Shop</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Phone</label>
                            <input type="text" id="phoneInput" name="phone" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">Auto-filled from shop</small>
                        </div>
                        <div class="col-md-4">
                            <label>Category</label>
                            <select name="category_id" class="form-select" onchange="syncProducts(this, 'product_id_add')" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Product</label>
                            <select name="product_id" id="product_id_add" class="form-select" onchange="updateProductPrice()" required>
                                <option value="">Select Category First</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" required
                                   oninput="handleQuantityChange()" onchange="handleQuantityChange()">
                        </div>
                        <div class="col-md-3">
                            <label>Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" id="unit_price" class="form-control" required
                                   oninput="handleUnitPriceChange()" onchange="handleUnitPriceChange()">
                            <small class="text-muted">Edit to auto-calculate total</small>
                        </div>
                        <div class="col-md-3">
                            <label>Total Amount</label>
                            <input type="number" step="0.01" name="amount" id="total_amount" class="form-control"
                                   oninput="handleTotalAmountChange()" onchange="handleTotalAmountChange()">
                            <small class="text-muted">Edit to auto-calculate unit price</small>
                        </div>
                        <div class="col-md-3">
                            <label>Cheque No. <small class="text-muted">(optional)</small></label>
                            <input type="text" name="cn" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAndContinueBtn">
                        <i class="fas fa-save"></i> Save & Continue
                    </button>
                    <button type="button" class="btn btn-success" id="saveAndExitBtn">
                        <i class="fas fa-save"></i> Save & Exit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <form id="editForm">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5>Edit Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Bill No</label>
                            <input type="text" name="bill_no" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>Date</label>
                            <input type="date" name="date" id="editDateInput" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>Shop</label>
                            <select name="shop_id" id="editShopSelect" class="form-select" onchange="updateEditShopDetails()">
                                <option value="">Select Shop</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Phone</label>
                            <input type="text" id="editPhoneInput" name="phone" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">Auto-filled from shop</small>
                        </div>
                        <div class="col-md-4">
                            <label>Category</label>
                            <select name="category_id" class="form-select" onchange="syncProducts(this, 'product_id_edit')" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Product</label>
                            <select name="product_id" id="product_id_edit" class="form-select" onchange="updateEditProductPrice()" required>
                                <option value="">Select Category First</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <input type="number" name="quantity" id="editQuantity" class="form-control" value="1" min="1" required
                                   oninput="handleEditQuantityChange()" onchange="handleEditQuantityChange()">
                        </div>
                        <div class="col-md-3">
                            <label>Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" id="editUnitPrice" class="form-control" required
                                   oninput="handleEditUnitPriceChange()" onchange="handleEditUnitPriceChange()">
                            <small class="text-muted">Edit to auto-calculate total</small>
                        </div>
                        <div class="col-md-3">
                            <label>Total Amount</label>
                            <input type="number" step="0.01" name="amount" id="editTotalAmount" class="form-control"
                                   oninput="handleEditTotalAmountChange()" onchange="handleEditTotalAmountChange()">
                            <small class="text-muted">Edit to auto-calculate unit price</small>
                        </div>
                        <div class="col-md-3">
                            <label>Month</label>
                            <input type="text" id="editMonthField" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">For display only — recomputed from date on save</small>
                        </div>
                        <div class="col-md-3">
                            <label>Cheque No. <small class="text-muted">(optional)</small></label>
                            <input type="text" name="cn" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5>Confirm Delete</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete <strong id="deleteName"></strong>?
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/expense-management.js"></script>
</body>
</html>
```

- [ ] **Step 2: Create `assets/js/expense-management.js`**

This is a from-scratch clean rewrite of the source's `expense.js` that
preserves 100% of its user-facing behavior but defines each function
exactly once (the source had a "define then override" pattern from
iterative live-patching — collapsed here into one straightforward
definition per function) and fixes the month display to `'YYYY-MM'`
format (cosmetic only — the server always recomputes `month` from `date`
on save regardless of what this field shows).

```javascript
var productPrices = {};

document.addEventListener('DOMContentLoaded', function() {
    fetch('api_expense_handler.php?action=get_all_product_prices')
        .then(r => r.json())
        .then(prices => { productPrices = prices; });

    loadShops();

    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.addEventListener('change', updateMonthFromDate);
    }
    const editDateInput = document.getElementById('editDateInput');
    if (editDateInput) {
        editDateInput.addEventListener('change', updateEditMonthFromDate);
    }

    $('input, select').on('input change', function() {
        $(this).removeClass('is-invalid');
    });

    $('#addModal').on('hidden.bs.modal', function() {
        resetAddForm();
    });
});

$('#expensesTable').DataTable({
    pageLength: 25,
    order: [[2, 'desc']],
    columnDefs: [
        { targets: [12], orderable: false }
    ]
});

// ========== SHOPS ==========

function loadShops() {
    fetch('api_expense_handler.php?action=get_shops')
        .then(r => r.json())
        .then(shops => {
            const select = document.getElementById('shopSelect');
            const editSelect = document.getElementById('editShopSelect');

            [select, editSelect].forEach(sel => {
                if (sel) {
                    sel.innerHTML = '<option value="">Select Shop</option>';
                    shops.forEach(shop => {
                        const opt = document.createElement('option');
                        opt.value = shop.id;
                        opt.textContent = shop.name;
                        opt.setAttribute('data-phone', shop.phone || '');
                        sel.appendChild(opt);
                    });
                }
            });
        })
        .catch(error => {
            showAlert('Failed to load shops: ' + error.message, 'danger');
        });
}

function updateShopDetails() {
    const shopSelect = document.getElementById('shopSelect');
    const phoneInput = document.getElementById('phoneInput');
    phoneInput.value = shopSelect.value
        ? (shopSelect.options[shopSelect.selectedIndex].getAttribute('data-phone') || '')
        : '';
}

function updateEditShopDetails() {
    const shopSelect = document.getElementById('editShopSelect');
    const phoneInput = document.getElementById('editPhoneInput');
    if (shopSelect && phoneInput) {
        phoneInput.value = shopSelect.value
            ? (shopSelect.options[shopSelect.selectedIndex].getAttribute('data-phone') || '')
            : '';
    }
}

// ========== CATEGORY -> PRODUCT ==========

function syncProducts(select, targetId) {
    const catId = select.value;
    const target = document.getElementById(targetId);
    target.innerHTML = '<option>Loading...</option>';
    fetch(`api_expense_handler.php?action=get_products&cat_id=${catId}`)
        .then(r => r.json())
        .then(products => {
            target.innerHTML = '<option value="">Select Product</option>';
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                const priceText = p.price ? ` - Rs. ${parseFloat(p.price).toFixed(2)}` : '';
                opt.textContent = p.name + (p.company ? ` (${p.company})` : '') + priceText;
                target.appendChild(opt);
            });
        });
}

function updateProductPrice() {
    const productSelect = document.getElementById('product_id_add');
    const unitPriceInput = document.getElementById('unit_price');
    const productId = productSelect.value;

    if (!productId) return;

    if (productPrices[productId]) {
        unitPriceInput.value = productPrices[productId];
        handleUnitPriceChange();
    } else {
        fetch(`api_expense_handler.php?action=get_product_price&id=${productId}`)
            .then(r => r.json())
            .then(product => {
                if (product.price) {
                    unitPriceInput.value = product.price;
                    handleUnitPriceChange();
                }
            });
    }
}

function updateEditProductPrice() {
    const productSelect = document.getElementById('product_id_edit');
    const unitPriceInput = document.getElementById('editUnitPrice');
    const productId = productSelect.value;

    if (!productId) return;

    if (productPrices[productId]) {
        unitPriceInput.value = productPrices[productId];
        handleEditUnitPriceChange();
    } else {
        fetch(`api_expense_handler.php?action=get_product_price&id=${productId}`)
            .then(r => r.json())
            .then(product => {
                if (product.price) {
                    unitPriceInput.value = product.price;
                    handleEditUnitPriceChange();
                }
            });
    }
}

// ========== QUANTITY / UNIT PRICE / TOTAL THREE-WAY SYNC ==========

let isUpdatingAdd = false;

function handleQuantityChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    document.getElementById('total_amount').value = (qty * price).toFixed(2);
    isUpdatingAdd = false;
}

function handleUnitPriceChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    document.getElementById('total_amount').value = (qty * price).toFixed(2);
    isUpdatingAdd = false;
}

function handleTotalAmountChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const total = parseFloat(document.getElementById('total_amount')?.value || 0);
    if (qty > 0) {
        document.getElementById('unit_price').value = (total / qty).toFixed(2);
    }
    isUpdatingAdd = false;
}

let isUpdatingEdit = false;

function handleEditQuantityChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const price = parseFloat(document.getElementById('editUnitPrice')?.value || 0);
    document.getElementById('editTotalAmount').value = (qty * price).toFixed(2);
    isUpdatingEdit = false;
}

function handleEditUnitPriceChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const price = parseFloat(document.getElementById('editUnitPrice')?.value || 0);
    document.getElementById('editTotalAmount').value = (qty * price).toFixed(2);
    isUpdatingEdit = false;
}

function handleEditTotalAmountChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const total = parseFloat(document.getElementById('editTotalAmount')?.value || 0);
    if (qty > 0) {
        document.getElementById('editUnitPrice').value = (total / qty).toFixed(2);
    }
    isUpdatingEdit = false;
}

// ========== MONTH DISPLAY (cosmetic only) ==========

function updateMonthFromDate() {
    // No visible month field on the Add modal - server derives month from date on save.
}

function updateEditMonthFromDate() {
    const dateInput = document.getElementById('editDateInput');
    const monthField = document.getElementById('editMonthField');
    if (dateInput && monthField && dateInput.value) {
        const date = new Date(dateInput.value);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        monthField.value = `${yyyy}-${mm}`;
    }
}

// ========== BILL NUMBER ==========

function getNextBillNumber() {
    fetch('api_expense_handler.php?action=get_next_bill')
        .then(r => r.json())
        .then(data => {
            if (data.bill_no) {
                document.querySelector('input[name="bill_no"]').value = data.bill_no;
            }
        });
}

// ========== FORM VALIDATION ==========

function validateForm() {
    const required = ['shop_id', 'date', 'category_id', 'product_id', 'unit_price'];
    let isValid = true;
    required.forEach(field => {
        const element = $(`#addForm [name="${field}"]`);
        if (!element.val()) {
            element.addClass('is-invalid');
            isValid = false;
        } else {
            element.removeClass('is-invalid');
        }
    });
    return isValid;
}

// ========== ADD / SAVE ==========

function saveExpense(callback) {
    if (!validateForm()) {
        showAlert('Please fill all required fields', 'warning');
        return;
    }
    const formData = $('#addForm').serializeArray();
    $.ajax({
        url: 'api_expense_handler.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                callback(response);
            } else {
                showAlert(response.message || 'Error saving expense', 'danger');
            }
        },
        error: function() {
            showAlert('Server error occurred', 'danger');
        }
    });
}

$('#saveAndContinueBtn').click(function() {
    saveExpense(function() {
        showAlert('Expense saved successfully!', 'success');
        resetAddForm();
        setTimeout(() => location.reload(), 800);
    });
});

$('#saveAndExitBtn').click(function() {
    saveExpense(function() {
        showAlert('Expense saved successfully!', 'success');
        $('#addModal').modal('hide');
        setTimeout(() => location.reload(), 800);
    });
});

function resetAddForm() {
    const form = document.getElementById('addForm');
    form.reset();
    document.getElementById('product_id_add').innerHTML = '<option value="">Select Category First</option>';
    document.getElementById('quantity').value = 1;
    document.getElementById('unit_price').value = '';
    document.getElementById('total_amount').value = '';
    document.getElementById('dateInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('phoneInput').value = '';
    document.getElementById('shopSelect').value = '';
    getNextBillNumber();
}

// ========== EDIT ==========

function editExpense2(expense) {
    const form = document.getElementById('editForm');
    form.reset();

    form.querySelector('[name="id"]').value = expense.id;
    form.querySelector('[name="bill_no"]').value = expense.bill_no;
    form.querySelector('[name="date"]').value = expense.date.split(' ')[0];
    form.querySelector('[name="cn"]').value = expense.cn || '';

    document.getElementById('editQuantity').value = expense.quantity || 1;
    document.getElementById('editUnitPrice').value = expense.unit_price || expense.amount;
    handleEditQuantityChange();

    setTimeout(() => {
        const shopSelect = document.getElementById('editShopSelect');
        if (shopSelect && expense.shop_id) {
            shopSelect.value = expense.shop_id;
            updateEditShopDetails();
        }
    }, 100);

    if (expense.category_id) {
        const catSelect = document.querySelector('#editForm [name="category_id"]');
        catSelect.value = expense.category_id;
        syncProducts(catSelect, 'product_id_edit');

        setTimeout(() => {
            const productSelect = document.querySelector('#editForm [name="product_id"]');
            if (productSelect && expense.product_id) {
                productSelect.value = expense.product_id;
            }
        }, 500);
    }

    updateEditMonthFromDate();

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const formData = new FormData(this);
        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        showAlert(result.message || 'Expense updated', result.success ? 'success' : 'danger');
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            setTimeout(() => location.reload(), 800);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

// ========== DELETE ==========

function deleteExpense2(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('confirmDelete').addEventListener('click', async function() {
    const id = document.getElementById('deleteId').value;
    if (!id) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();

        showAlert(result.message || 'Expense deleted', 'success');
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        setTimeout(() => location.reload(), 800);
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

// ========== ALERTS ==========

function showAlert(msg, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `<div>${msg}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.getElementById('alert-placeholder').appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}

// ========== PRINT ==========

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
}

function printSingle2(expense) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    const html = `
    <html>
    <head>
        <title>Invoice ${expense.bill_no}</title>
        <style>
            body { font-family: Arial; padding: 20px; }
            .invoice-box { border: 2px solid #000; padding: 20px; max-width: 800px; margin: 0 auto; }
            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #000; padding: 8px; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .signature { margin-top: 50px; display: flex; justify-content: space-between; }
            .total-row { font-weight: bold; background: #f0f0f0; }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <h2>Muhaddisa School of Science and Technology</h2>
                <h3>Expense Invoice</h3>
            </div>
            <table>
                <tr><td><strong>Bill No:</strong></td><td>${expense.bill_no}</td></tr>
                <tr><td><strong>Date:</strong></td><td>${new Date(expense.date).toLocaleDateString('en-GB')}</td></tr>
                <tr><td><strong>Shop:</strong></td><td>${expense.shop || ''}</td></tr>
                <tr><td><strong>Phone:</strong></td><td>${expense.phone || 'N/A'}</td></tr>
                <tr><td><strong>Cheque No:</strong></td><td>${expense.cn || '—'}</td></tr>
                <tr><td><strong>Category:</strong></td><td>${expense.category_name || ''}</td></tr>
                <tr><td colspan="2"><strong>Product Details:</strong></td></tr>
                <tr><td colspan="2">${expense.product_name || ''} ${expense.company ? '('+expense.company+')' : ''}</td></tr>
            </table>
            <table style="margin-top: 20px;">
                <tr><th>Quantity</th><th>Unit Price</th><th>Total Amount</th></tr>
                <tr class="total-row">
                    <td class="text-center">${expense.quantity || 1}</td>
                    <td class="text-right">Rs. ${formatNumber(expense.unit_price || expense.amount)}</td>
                    <td class="text-right"><strong>Rs. ${formatNumber(expense.amount)}</strong></td>
                </tr>
            </table>
            <div class="signature">
                <div>Accountant: ________________</div>
                <div>Authorized: ________________</div>
            </div>
            <div style="text-align:center; margin-top:20px;"><small>Printed: ${new Date().toLocaleString()}</small></div>
        </div>
    </body>
    </html>`;

    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

function printSummaryOnly() {
    const win = window.open('', '', 'width=900,height=700');
    const content = document.getElementById('summary-page').innerHTML;
    win.document.write(`
        <html><head><title>Summary</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th, td { border: 1px solid #000; padding: 6px; }
            h5 { font-size: 16px; text-align: center; }
        </style>
        </head><body>
        ${content}
        </body></html>
    `);
    win.document.close();
    win.print();
}

function printReport() {
    const win = window.open('', '', 'width=900,height=700');
    win.document.write(`
        <html><head><title>Full Report</title>
        <style>
            body { font-family: Arial; margin: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th, td { border: 1px solid #000; padding: 4px; }
        </style>
        </head><body>
        ${document.getElementById('printable').innerHTML}
        </body></html>
    `);
    win.document.close();
    win.print();
}
```

- [ ] **Step 3: Verify end-to-end via curl**

```bash
cat > _t_admin.php << 'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c cj_admin.txt "http://localhost/msst/lms/_t_admin.php" > /dev/null
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-management.php" -o /tmp/em.html -w "HTTP %{http_code}\n"
grep -c "Database Error\|Fatal error\|Warning:" /tmp/em.html
grep -o "EXP1001\|EXP[0-9]*" /tmp/em.html | head -1
```
Expected: `HTTP 200`, grep count `0`, a next-bill-number placeholder like
`EXP1001` present in the Add modal's readonly field.

Seed one expense via the API and confirm it renders in the table with a
correctly formatted `'Y-m'` month, then verify via direct SQL:
```bash
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_category" --data-urlencode "name=Verify Cat 2"
CAT_ID=$(mysql -u root msst_db -N -e "SELECT id FROM expense_categories WHERE name='Verify Cat 2';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_product" --data-urlencode "category_id=$CAT_ID" \
  --data-urlencode "name=Verify Product 2" --data-urlencode "price=75"
PROD_ID=$(mysql -u root msst_db -N -e "SELECT id FROM expense_products WHERE name='Verify Product 2';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add_shop" --data-urlencode "name=Verify Shop 2"
SHOP_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shops WHERE name='Verify Shop 2';")
curl -s -b cj_admin.txt -X POST "http://localhost/msst/lms/api_expense_handler.php" \
  --data-urlencode "action=add" --data-urlencode "bill_no=EXP-VERIFY-2" \
  --data-urlencode "date=2026-07-10" --data-urlencode "shop_id=$SHOP_ID" \
  --data-urlencode "product_id=$PROD_ID" --data-urlencode "quantity=2" --data-urlencode "unit_price=75"

mysql -u root msst_db -e "SELECT bill_no, month, amount, balance FROM expenses WHERE bill_no='EXP-VERIFY-2';"

curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-management.php?details_start=2026-07-01&details_end=2026-07-31" | grep -o "EXP-VERIFY-2"
```
Expected: SQL row shows `month = '2026-07'`, `amount = 150.00`. The curl
grep at the end confirms the row appears in the details table for that
date range.

Clean up:
```bash
mysql -u root msst_db -e "DELETE FROM expenses WHERE bill_no='EXP-VERIFY-2'; DELETE FROM shops WHERE name='Verify Shop 2'; DELETE FROM expense_products WHERE name='Verify Product 2'; DELETE FROM expense_categories WHERE name='Verify Cat 2';"
rm -f _t_admin.php cj_admin.txt
```

- [ ] **Step 4: Commit**

```bash
git add expense-management.php assets/js/expense-management.js
git commit -m "Add full expense-management.php ledger page and its client JS"
```

---

### Task 6: Sidebar link

**Files:**
- Modify: `B:\PO\Website\xampp 8.2\htdocs\msst\lms\sidebar.php`

**Interfaces:**
- Consumes: `expense-management.php`, `expense-categories.php`,
  `shop-management.php` (Tasks 3, 4, 5) as link targets.

- [ ] **Step 1: Add the dropdown + link after the Fee Payment Report entry**

Find this line in `sidebar.php` (added in a previous session):
```html
        <li><a href="fee-payment-report.php"><i class="fas fa-list-check fa-fw me-2"></i> <span>Fee Payment Report</span></a></li>
```

Insert immediately after it:
```html
        <li class="dropdown">
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

- [ ] **Step 2: Verify the dropdown renders and both links resolve**

```bash
cat > _t_admin.php << 'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c cj_admin.txt "http://localhost/msst/lms/_t_admin.php" > /dev/null
curl -s -b cj_admin.txt "http://localhost/msst/lms/dashboard.php" | grep -o "Expense Management\|Shop Management\|Categories &amp; Products\|View Expenses"
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-management.php" -o /dev/null -w "expense-management.php: HTTP %{http_code}\n"
curl -s -b cj_admin.txt "http://localhost/msst/lms/expense-categories.php" -o /dev/null -w "expense-categories.php: HTTP %{http_code}\n"
curl -s -b cj_admin.txt "http://localhost/msst/lms/shop-management.php" -o /dev/null -w "shop-management.php: HTTP %{http_code}\n"
rm -f _t_admin.php cj_admin.txt
```
Expected: all four grep matches found in the sidebar HTML, all three
pages return `HTTP 200`.

- [ ] **Step 3: Commit**

```bash
git add sidebar.php
git commit -m "Add Expense Management and Shop Management links to admin sidebar"
```

---

## Final verification (after all tasks complete)

Confirm the whole feature is empty-but-functional (no leftover test data)
and every table exists:

```bash
mysql -u root msst_db -e "
SELECT
  (SELECT COUNT(*) FROM expense_categories) AS categories,
  (SELECT COUNT(*) FROM expense_products) AS products,
  (SELECT COUNT(*) FROM shops) AS shops,
  (SELECT COUNT(*) FROM expenses) AS expenses,
  (SELECT COUNT(*) FROM product_price_history) AS price_history;
"
```
Expected: all five counts are `0` (no test data left behind — MSST starts
empty per the spec's "no data migration" decision).

Confirm all six new/modified files pass `php -l`:
```bash
php -l expense-management.php
php -l expense-categories.php
php -l shop-management.php
php -l api_expense_handler.php
php -l sidebar.php
```
