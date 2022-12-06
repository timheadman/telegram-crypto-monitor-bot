#!/bin/bash
echo "Copying env variables to /etc/environment so that it is available for cron jobs"
printenv >> /etc/environment
echo "Starting cron"
php ctb.php
cron -f
