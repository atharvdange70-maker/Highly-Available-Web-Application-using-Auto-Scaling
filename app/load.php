<?php
/**
 * EduBlitz HA - Load Orchestrator
 * Triggers CPU load (~80%) on ALL instances in the Auto Scaling Group.
 * Fetches instance list, then calls each instance's /load-internal endpoint.
 */
header('Content-Type: application/json');

$instancesJson = @file_get_contents('http://127.0.0.1/metadata'); // won't work - use CLI or HTTP to self
// Get instances via our instances API (internal call) - AWS CLI may take a few seconds
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$instancesData = @file_get_contents('http://127.0.0.1/instances', false, $ctx);

if (!$instancesData) {
    // Fallback: run load on this instance only
    $scriptPath = '/var/www/html/load.sh';
    if (file_exists($scriptPath)) {
        exec('bash ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &');
        echo json_encode(['status' => 'ok', 'triggered' => 1, 'message' => 'CPU load started on this instance (instances list unavailable)']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'load.sh not found']);
    }
    exit;
}

$data = @json_decode($instancesData, true);
$instances = $data['instances'] ?? [];

if (empty($instances)) {
    // Fallback to current instance only
    $scriptPath = '/var/www/html/load.sh';
    if (file_exists($scriptPath)) {
        exec('bash ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &');
        echo json_encode(['status' => 'ok', 'triggered' => 1, 'message' => 'CPU load started']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error']);
    }
    exit;
}

$triggered = 0;
$errors = [];

foreach ($instances as $inst) {
    $ip = $inst['private-ip'] ?? '';
    $state = $inst['state'] ?? '';
    if (empty($ip) || $state !== 'running') continue;

    $url = "http://{$ip}/load-internal";
    $result = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 3]]));
    if ($result !== false) {
        $triggered++;
    } else {
        $errors[] = $inst['instance-id'] ?? $ip;
    }
}

echo json_encode([
    'status' => 'ok',
    'triggered' => $triggered,
    'total' => count($instances),
    'message' => "CPU load (~80%) started on {$triggered} instance(s)",
    'errors' => $errors
]);