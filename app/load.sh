#!/bin/bash
# EduBlitz HA - CPU Load Generator (~80% target)
# Generates CPU load on this instance to trigger Auto Scaling
# Uses stress-ng for ~80% if available, else stress for ~100%

# Target ~80% CPU for 5 minutes (300 seconds)
if command -v stress-ng &>/dev/null; then
    stress-ng --cpu 1 --cpu-load 80 --timeout 300 &
else
    # Fallback: stress --cpu 1 = ~100% on single vCPU (t2.micro)
    stress --cpu 1 --timeout 300 &
fi
echo "Load generation started (~80% CPU)"