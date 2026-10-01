<?php
/**
 * EduBlitz HA - List All Running Instances in Auto Scaling Group
 * Uses AWS CLI (requires IAM role with autoscaling + ec2 permissions)
 * Falls back to current instance only if not in ASG or IAM unavailable
 */
header('Content-Type: application/json');

$currentInstanceId = trim(@file_get_contents('http://169.254.169.254/latest/meta-data/instance-id') ?: '');
$currentAz = trim(@file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone') ?: '');

if (empty($currentInstanceId)) {
    echo json_encode(['instances' => [], 'error' => 'Could not get current instance ID']);
    exit;
}

$instances = [];

// Try to get all instances from Auto Scaling Group via AWS CLI
$region = preg_replace('/[a-z]$/', '', $currentAz);
$asgResult = @shell_exec("aws autoscaling describe-auto-scaling-instances --instance-ids " . escapeshellarg($currentInstanceId) . " --region " . escapeshellarg($region) . " --output json 2>/dev/null");
$asgData = $asgResult ? @json_decode($asgResult, true) : null;

if (!empty($asgData['AutoScalingInstances'][0]['AutoScalingGroupName'])) {
    $asgName = $asgData['AutoScalingInstances'][0]['AutoScalingGroupName'];

    // Get all instance IDs in the ASG
    $allAsgResult = @shell_exec("aws autoscaling describe-auto-scaling-instances --region " . escapeshellarg($region) . " --output json 2>/dev/null");
    $allAsgData = $allAsgResult ? @json_decode($allAsgResult, true) : null;

    $instanceIds = [];
    if (!empty($allAsgData['AutoScalingInstances'])) {
        foreach ($allAsgData['AutoScalingInstances'] as $asi) {
            if (($asi['AutoScalingGroupName'] ?? '') === $asgName && ($asi['LifecycleState'] ?? '') === 'InService') {
                $instanceIds[] = $asi['InstanceId'];
            }
        }
    }

    if (!empty($instanceIds)) {
        $idsStr = implode(' ', array_map('escapeshellarg', $instanceIds));
        $ec2Result = @shell_exec("aws ec2 describe-instances --instance-ids {$idsStr} --region " . escapeshellarg($region) . " --query 'Reservations[*].Instances[*].[InstanceId,PrivateIpAddress,Placement.AvailabilityZone,State.Name]' --output json 2>/dev/null");
        $ec2Data = $ec2Result ? @json_decode($ec2Result, true) : null;

        if (!empty($ec2Data)) {
            foreach ($ec2Data as $reservation) {
                foreach ($reservation as $inst) {
                    $instances[] = [
                        'instance-id' => $inst[0] ?? '',
                        'private-ip' => $inst[1] ?? '',
                        'availability-zone' => $inst[2] ?? '',
                        'state' => $inst[3] ?? 'unknown',
                        'current' => ($inst[0] ?? '') === $currentInstanceId
                    ];
                }
            }
        }
    }
}

// Fallback: return only current instance (when not in ASG or IAM unavailable)
if (empty($instances)) {
    $instances[] = [
        'instance-id' => $currentInstanceId,
        'private-ip' => trim(@file_get_contents('http://169.254.169.254/latest/meta-data/local-ipv4') ?: ''),
        'availability-zone' => $currentAz,
        'state' => 'running',
        'current' => true
    ];
}

echo json_encode([
    'instances' => $instances,
    'current-instance-id' => $currentInstanceId
]);