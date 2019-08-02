#!/usr/bin/env bash
set -euo pipefail

function cleanup {
    echo "Cleaning up"
    docker-compose down --rmi local -v
    rm -rf docroot
}
trap cleanup EXIT

HOST=$(basename "${PWD}")
export COMPOSE_FILE=docker-compose.yml
export COMPOSE_PROJECT_NAME=drupal_${HOST}_${VERSION}_${BRANCH_NAME}

echo "Cleaning up any failed builds"
docker-compose down --rmi local -v
rm -rf docroot

echo "Downloading Drupal core"
docker run --volume "$(pwd)":/app --user "$(id -u):$(id -g)" drush/drush dl drupal -y --drupal-project-rename=docroot

echo "Starting containers"
docker-compose up --detach
sleep 10

# TODO: Actually install Drupal here and also test dumping and autoloading a database.

echo "Fetching page to check status"
STATUS=$(docker-compose run --no-deps --rm php curl --location --silent --output /dev/null --write-out "%{http_code}" "http://web/")
echo "Status: ${STATUS}"
if [ "${STATUS}" != "200" ]; then
    echo "httpd container not responding with 200 HTTP response code"
    exit 1
fi
echo "Page status OK"

echo "Checking PHP version"
ACTUAL=$(docker-compose run --rm php php --version | head -n 1 | cut -d " " -f 2 | cut -d'.' -f1-2)
if [ "${ACTUAL}" != "${VERSION}" ]; then
    echo "PHP ${ACTUAL} does not match expected version number ${VERSION}"
    exit 2
fi
echo "PHP version OK"