<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=v4_modomines1", "root", "");
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT count(*) FROM `$table`")->fetchColumn();
        if ($count > 0) {
            echo "Table $table: $count records\n";
        } else {
            echo "Table $table: 0 records\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
