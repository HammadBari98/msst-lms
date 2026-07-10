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
