#!/usr/bin/env bash
set -euo pipefail
SUPPORTED_PHP=( 7.1 7.2 7.3 )
if [ -z "$HOST" ]; then
    echo "Specify a \$HOST to get configuration for."
    exit 1
fi
if [[ ! -f php/Dockerfile || ! -f mysql/Dockerfile || ! -f httpd/Dockerfile ]]; then
    echo "This script expects to be run in a directory with php, mysql and httpd subdirectories"
    echo "containing the Dockerfiles where the configuration will be updated."
    exit 1
fi

echo "Updating PHP configuration"
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
scp "$DIR"/getconfig.php "${HOST}":/tmp/
for PHP in "${SUPPORTED_PHP[@]}"; do
    echo "Getting config for $HOST -> $PHP"
    ssh "$HOST" "/usr/local/php${PHP}/bin/php" /tmp/getconfig.php acquia > php/"${PHP}"-config
done
ssh "$HOST" rm /tmp/getconfig.php

echo "Updating Percona version"
# Get a regular site alias to connect to
ALIAS=$(ssh "$HOST" drush sa --local-only | grep -E '\.(prod|test|dev)$' | head -n1)
# Connect to the alias and output the version string
# shellcheck disable=SC2029
RAWVERSION=$(ssh "$HOST" "drush $ALIAS sqlq 'SHOW VARIABLES LIKE \"version\"'")
# Extract the primary version number from the version string
MYSQLVERSION=$(echo "$RAWVERSION" | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+')
# Update the version number in the Dockerfile with the extracted number
sed -i'' -e "s/FROM percona:[0-9.]*$/FROM percona:$MYSQLVERSION/" mysql/Dockerfile

echo "Updating HTTPD version"
# Get HTTPD version number
HTTPDVERSION=$(ssh "$HOST" apachectl -v | grep version | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+')
# Update the version number in the Dockerfile with the extracted number
sed -i'' -e "s/FROM httpd:[0-9.]*$/FROM httpd:$HTTPDVERSION/" httpd/Dockerfile
