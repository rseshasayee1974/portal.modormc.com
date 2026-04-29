<?php
echo "Connecting to MySQL...\\n";
try {
    // Database connection details from the requested settings
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $dbName = 'v4_modomines_com';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    echo "Database created or exists.\\n";
    
    // Select the database
    $pdo->exec("USE `$dbName`");
    
    // Read the SQL contents from the file
    $sqlFile = 'C:/Users/mahar/Downloads/v4_modomines (1).sql';
    if (!file_exists($sqlFile)) {
        die("SQL file does not exist at $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Temporarily disable foreign key checks to prevent dropping errors during resets
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    
    // Execute the SQL imports
    echo "Loading SQL into $dbName...\\n";
    $pdo->exec($sql);
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    
    echo "SQL Imported successfully.\\n";
} catch (PDOException $e) {
    die("PDO Error: " . $e->getMessage());
}
?>
