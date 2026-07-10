<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit(json_encode(['message' => 'Unauthorized']));
}

// =======================================================
// AUTO-PATCHER: heal pre-existing expense_products/expenses tables
// that predate this feature's columns.
// =======================================================
try {
    $ep_cols = $pdo->query("SHOW COLUMNS FROM expense_products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('current_price', $ep_cols)) {
        $pdo->exec("ALTER TABLE expense_products ADD COLUMN current_price DECIMAL(10,2) DEFAULT 0.00");
    }
    if (!in_array('last_updated', $ep_cols)) {
        $pdo->exec("ALTER TABLE expense_products ADD COLUMN last_updated DATE DEFAULT NULL");
    }
} catch (PDOException $e) { /* Ignore if already up to date */ }

try {
    $exp_cols = $pdo->query("SHOW COLUMNS FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('shop_id', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN shop_id INT DEFAULT NULL AFTER date");
    }
    if (!in_array('quantity', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN quantity INT DEFAULT 1");
    }
    if (!in_array('unit_price', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0.00");
    }
    if (!in_array('is_paid', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('cn', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN cn VARCHAR(50) DEFAULT NULL");
    }
    if (!in_array('payment_date', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN payment_date DATE DEFAULT NULL");
    }
    if (!in_array('balance', $exp_cols)) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
} catch (PDOException $e) { /* Ignore if already up to date */ }

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
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_products WHERE category_id = ?");
            $stmt->execute([$_POST['id']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['message' => 'Cannot delete category with existing products. Delete or reassign its products first.']);
                break;
            }
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
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE product_id = ?");
            $stmt->execute([$_POST['id']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['message' => 'Cannot delete product with existing expense records']);
                break;
            }
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
