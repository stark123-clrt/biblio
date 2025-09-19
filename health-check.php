<?php
// health-check.php - Endpoint de santé pour le monitoring du pipeline CI/CD

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '2.0.0',
    'environment' => $_ENV['APP_ENV'] ?? 'development',
    'checks' => []
];

// Vérification base de données
try {
    require_once 'classes/Core.php';
    $db = Database::getInstance();
    $stmt = $db->getConnection()->query("SELECT 1");
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    $health['status'] = 'error';
}

// Vérification espace disque
$diskFree = disk_free_space('./');
$diskTotal = disk_total_space('./');
$diskUsage = (($diskTotal - $diskFree) / $diskTotal) * 100;

if ($diskUsage > 90) {
    $health['checks']['disk'] = [
        'status' => 'warning',
        'message' => 'Disk usage high',
        'usage_percent' => round($diskUsage, 2)
    ];
    if ($health['status'] === 'ok') $health['status'] = 'warning';
} else {
    $health['checks']['disk'] = [
        'status' => 'ok',
        'message' => 'Disk usage normal',
        'usage_percent' => round($diskUsage, 2)
    ];
}

// Vérification fichiers critiques
$criticalFiles = [
    'classes/Core.php',
    'classes/Models.php',
    'classes/Repositories.php',
    '.env'
];

$missingFiles = [];
foreach ($criticalFiles as $file) {
    if (!file_exists($file)) {
        $missingFiles[] = $file;
    }
}

if (!empty($missingFiles)) {
    $health['checks']['files'] = [
        'status' => 'error',
        'message' => 'Critical files missing',
        'missing_files' => $missingFiles
    ];
    $health['status'] = 'error';
} else {
    $health['checks']['files'] = [
        'status' => 'ok',
        'message' => 'All critical files present'
    ];
}

// Vérification dossiers d'upload
$uploadDir = 'assets/uploads/';
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    $health['checks']['uploads'] = [
        'status' => 'warning',
        'message' => 'Upload directory not writable'
    ];
    if ($health['status'] === 'ok') $health['status'] = 'warning';
} else {
    $health['checks']['uploads'] = [
        'status' => 'ok',
        'message' => 'Upload directory writable'
    ];
}

// Mode maintenance
if (file_exists('maintenance.mode')) {
    $health['checks']['maintenance'] = [
        'status' => 'active',
        'message' => 'Application in maintenance mode'
    ];
    $health['status'] = 'maintenance';
} else {
    $health['checks']['maintenance'] = [
        'status' => 'inactive',
        'message' => 'Application operational'
    ];
}

// Vérification PHP version
$minPhpVersion = '8.0';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $health['checks']['php'] = [
        'status' => 'warning',
        'message' => 'PHP version below recommended',
        'current_version' => PHP_VERSION,
        'minimum_version' => $minPhpVersion
    ];
    if ($health['status'] === 'ok') $health['status'] = 'warning';
} else {
    $health['checks']['php'] = [
        'status' => 'ok',
        'message' => 'PHP version compatible',
        'current_version' => PHP_VERSION
    ];
}

// Retourner le statut HTTP approprié
switch ($health['status']) {
    case 'ok':
        http_response_code(200);
        break;
    case 'warning':
        http_response_code(200); // 200 mais avec warnings
        break;
    case 'maintenance':
        http_response_code(503); // Service Unavailable
        break;
    default:
        http_response_code(500); // Internal Server Error
}

echo json_encode($health, JSON_PRETTY_PRINT);
?>