# drupal-acquia

Docker replica of Acquia hosting environment - matches OS, Apache, MariaDB, PHP and extension versions and PHP configs

## Getting started

Copy [docker-compose.yml](docker-compose.yml) to your project export/edit the TAG/VERSION variables to your preference and run `docker-compose up`.

## Updating and/or adding PHP versions

This uses the getconfig.sh script and getconfig.php helper to determine target configuration for a specified host.

If you need to include a new PHP version, add it to the list of SUPPORTED_PHP in `getconfig/getconfig.sh` and also add it to the list of versions being tested in the Jenkinsfile.

To execute, run:
```
curl -Lso getconfig/getconfig.php https://raw.githubusercontent.com/drydockcloud/tools-php-getconfig/master/getconfig.php
export HOST=mysite@mysite.full.domain
./getconfig/getconfig.sh
```

* Once complete you can review the config differences and try building each Dockerfile.
* The Dockerfile build process includes a test (in the last 3 lines of the main RUN compliation step) to verify that the extensions and resulting PHP config match what is expected - if there are differences the build will output a diff showing the fail.
* In this case you will need to update the Dockerfile to incorporate new/altered extension versions into the process, together with any new package dependencies.
