<?php
try {
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=v4_modomines1;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Check if column exists in mm_customer_pos
    $stmt1 = $pdo->query("SHOW COLUMNS FROM mm_customer_pos LIKE 'is_tax_inclusive'");
    $col1 = $stmt1->fetch();
    
    if (!$col1) {
        $pdo->exec("ALTER TABLE mm_customer_pos ADD is_tax_inclusive TINYINT(1) NOT NULL DEFAULT 0 AFTER concrete_pump");
        echo "Column 'is_tax_inclusive' successfully added to 'mm_customer_pos' table.\n";
    } else {
        echo "Column 'is_tax_inclusive' already exists in 'mm_customer_pos' table.\n";
    }
    
    // Check if column exists in mm_sales_orders
    $stmt2 = $pdo->query("SHOW COLUMNS FROM mm_sales_orders LIKE 'is_tax_inclusive'");
    $col2 = $stmt2->fetch();
    
    if (!$col2) {
        $pdo->exec("ALTER TABLE mm_sales_orders ADD is_tax_inclusive TINYINT(1) NOT NULL DEFAULT 0 AFTER concrete_pump");
        echo "Column 'is_tax_inclusive' successfully added to 'mm_sales_orders' table.\n";
    } else {
        echo "Column 'is_tax_inclusive' already exists in 'mm_sales_orders' table.\n";
    }
    
    // Check if migration row exists
    $stmt = $pdo->prepare("SELECT 1 FROM migrations WHERE migration = ?");
    $stmt->execute(['2026_07_08_160000_add_is_tax_inclusive_to_customer_pos_and_sales_orders_table']);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute(['2026_07_08_160000_add_is_tax_inclusive_to_customer_pos_and_sales_orders_table', 9999]);
        echo "Migration record successfully inserted into 'migrations' table.\n";
    } else {
        echo "Migration record already exists.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
