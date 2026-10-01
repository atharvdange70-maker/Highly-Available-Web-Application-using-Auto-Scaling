<?php
/**
 * EduBlitz HA - Instance Metadata Endpoint
 * Returns EC2 instance ID and Availability Zone
 */
header('Content-Type: application/json');

$instanceId = @file_get_contents('http://169.254.169.254/latest/meta-data/instance-id');
$az = @file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone');

echo json_encode([
    'instance-id' => $instanceId ?: 'unknown',
    'availability-zone' => $az ?: 'unknown'
]);