<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable OPCache for dynamic endpoint
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

// Simple parser for .env config
function get_db_config() {
    $envPath = dirname(__DIR__) . '/.env';
    $config = [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'v4_modomines1',
        'username' => 'root',
        'password' => ''
    ];
    
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                if ($key === 'DB_HOST') $config['host'] = $value;
                if ($key === 'DB_PORT') $config['port'] = $value;
                if ($key === 'DB_DATABASE') $config['database'] = $value;
                if ($key === 'DB_USERNAME') $config['username'] = $value;
                if ($key === 'DB_PASSWORD') $config['password'] = $value;
            }
        }
    }
    return $config;
}

$dbConfig = get_db_config();

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Request parameters
$sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

$whereClauses = [];
$params = [];

if ($sinceId > 0) {
    $whereClauses[] = "id > :since_id";
    $params[':since_id'] = $sinceId;
}

if (!empty($search)) {
    $whereClauses[] = "plate_number LIKE :search";
    $params[':search'] = "%{$search}%";
}

if (!empty($date)) {
    $whereClauses[] = "DATE(captured_at) = :date";
    $params[':date'] = $date;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Fetch total count for pagination info (only if not doing incremental updates)
$totalCount = 0;
if ($sinceId === 0) {
    $countSql = "SELECT COUNT(*) FROM `anpr_logs` $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
}

// Fetch logs
$sql = "SELECT * FROM `anpr_logs` $whereSql ORDER BY id DESC LIMIT :limit";
$stmt = $pdo->prepare($sql);

// Bind params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'total' => $totalCount,
    'count' => count($logs),
    'data' => $logs
]);