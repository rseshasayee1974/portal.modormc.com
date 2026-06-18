<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=v4_modomines1", "root", "");
    
    // Check tables list first
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $userTable = in_array('mm_users', $tables) ? 'mm_users' : (in_array('users', $tables) ? 'users' : null);
    
    if (!$userTable) {
        echo "No users table found in list of tables:\n";
        print_r($tables);
        exit;
    }
    
    echo "Found user table: $userTable\n";
    
    // Check count of users
    $count = $pdo->query("SELECT count(*) FROM $userTable")->fetchColumn();
    echo "Total users: $count\n";
    
    // Query demo@modomines.com
    $stmt = $pdo->prepare("SELECT * FROM $userTable WHERE email = ?");
    $stmt->execute(['demo@modomines.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "User found:\n";
        print_r($user);
    } else {
        echo "User 'demo@modomines.com' not found!\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
