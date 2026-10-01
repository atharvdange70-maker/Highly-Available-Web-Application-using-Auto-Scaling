<?php
/**
 * EduBlitz HA - Internal Load Endpoint
 * Runs stress on THIS instance only. Called by load.php orchestrator from other instances.
 */
header('Content-Type: application/json');

$scriptPath = '/var/www/html/load.sh';
if (file_exists($scriptPath)) {
    exec('bash ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &');
    echo json_encode(['status' => 'ok', 'instance' => trim(@file_get_contents('http://169.254.169.254/latest/meta-data/instance-id') ?: '')]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}