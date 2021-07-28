<?php

/**
 * Extract PHP config from the current server.
 *
 * This script gathers PHP build and configuration details and outputs them as
 * a file of shell variables. These can then be used in Dockerfile build
 * processes to reproduce and validate a PHP build.
 *
 * PHP Version 5+
 *
 * @category PHP
 *
 * @see https://github.com/CivicActions/drydock/blob/master/tools/getconfig.php
 *
 * @author    Owen Barton <owen.barton@civicactions.com>
 * @copyright 2018 CivicActions
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

/**
 * Gets the configure options from any php binary supporting the '-i' info flag.
 *
 * @param string $executable php executable to interrogate
 *
 * @return string Configure options (excluding initial ./configure)
 */
function configureOptions($executable)
{
    $info = shell_exec($executable.' -i');
    $matches = array();
    if (preg_match("|^Configure Command[^a-z]*configure' +(.*)|m", $info, $matches)) {
        return trim(str_replace("'", '', $matches[1]));
    }

    return '';
}

/**
 * Gets the ini configuration from any php binary supporting the '-i' info flag.
 *
 * @param string $executable php executable to interrogate
 *
 * @return array array of file names and contents, in parse order
 */
function iniConfig($executable)
{
    $files = array();
    $info = shell_exec($executable.' -i');
    $matches = array();
    if (preg_match('|^Loaded Configuration File[^/]+(/.*)|m', $info, $matches)) {
        $file = $matches[1];
        $files[$file] = file_get_contents($file);
    }
    if (preg_match_all("|^Additional .ini files parsed[^/]+((?:\/[^,=>]+\.ini,?\n)+)|m", $info, $matches)) {
        if (!empty($matches[1][0])) {
            foreach (explode(',', $matches[1][0]) as $ini) {
                $file = trim($ini);
                $files[$file] = file_get_contents($file);
            }
        }
    }

    // Reset the array pointer to make it easy to access the first key.
    reset($files);

    return $files;
}

/**
 * Tries to find the most appropriate php-fpm binary given the current cli path.
 *
 * @return string Path to php-fpm executable
 */
function phpFpm()
{
    // Look for php-fpm first in sbin adjacent to php CLI, then next to php, then just on PATH.
    $phpfpm = dirname(dirname(PHP_BINARY)).'/sbin/php-fpm';
    if (!file_exists($phpfpm)) {
        $phpfpm = dirname(PHP_BINARY).'/php-fpm';
    }
    if (!file_exists($phpfpm)) {
        $phpfpm = 'php-fpm';
    }

    return $phpfpm;
}

// Accept a platform argument (we just take the last one to keep things simple).
$platform = array_pop($_SERVER['argv']);

// Gather values in an array, formatted at the end.
$output = array();

// Used for validation.
if (file_exists('/etc/redhat-release')) {
    $output['DISTRO'] = trim(file_get_contents('/etc/redhat-release'));
} else {
    $output['DISTRO'] = trim(shell_exec('lsb_release -ir'));
}

$output['EXACT_VERSION'] = phpversion();
$output['CLI_CONFIGURE_OPTIONS'] = configureOptions(PHP_BINARY);
$output['FPM_CONFIGURE_OPTIONS'] = configureOptions(phpFpm());

// Look for pecl first in lib adjacent to php CLI, then in standard system location.
$peclphp = dirname(dirname(PHP_BINARY)).'/lib/php/peclcmd.php';
if (!file_exists($peclphp)) {
    $peclphp = '/usr/share/pear/peclcmd.php';
}
$pecl = explode(PHP_EOL, shell_exec(PHP_BINARY.' '.$peclphp.' list'));
// Extract package name/version, skipping header rows.
$packages = array();
foreach (array_slice($pecl, 3) as $n => $package) {
    $matches = array();
    if (preg_match('|([^ ]+) +([^ ]+)|', $package, $matches)) {
        $packages[] = $matches[1].'-'.$matches[2];
    }
}
$output['PECL'] = implode(' ', $packages);

// Used for validation.
$extensions = get_loaded_extensions();
sort($extensions);
$output['EXTENSIONS'] = implode(PHP_EOL, $extensions);
foreach ($extensions as $k => $extension) {
    $ext = new ReflectionExtension($extension);
    $extensions[$k] = $extension.'-'.$ext->getVersion();
}
// Used to identify versions where we don't have a pecl list.
$output['EXTENSIONS_VERSIONS'] = implode(PHP_EOL, $extensions);

$cli_ini = iniConfig(PHP_BINARY);
$output['CLI_INI_FILE'] = key($cli_ini);
$output['CLI_INI'] = implode(PHP_EOL, $cli_ini);
$fpm_ini = iniConfig(phpFpm());
$output['FPM_INI_FILE'] = key($fpm_ini);
$output['FPM_INI'] = implode(PHP_EOL, $fpm_ini);

// Final resolved config, used for validation.
$config = array();
foreach (ini_get_all() as $k => $v) {
    $config[] = "$k: ".$v['global_value'];
}
$output['CONFIG'] = implode(PHP_EOL, $config);

// Remove IDs etc from config & paths.
// TODO: Make this a 2 level array so you can select (with the first level array keys) a regex identifying which variables to target.
$filters = array(
    // UUID v4s
    '@[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-(8|9|a|b)[a-f0-9]{3}-[a-f0-9]{12}@' => 'docker',
);
// Pantheon specific.
if ($platform == 'pantheon') {
    $filters['@/srv/bindings/[a-z0-9]{32}@'] = '/var/www';
    $filters['@\/srv\/bindings\/[a-z0-9]{32}@'] = '\/var\/www';
    $filters['@getpantheon.com@'] = 'example.com';
    $filters['@--with-tidy=/opt/pantheon@'] = '--with-tidy';
    $filters['@auto_prepend_file@'] = ';auto_prepend_file=';
    // Include pear as we need it for sqlsrv ext.
    $filters['@--without-pear@'] = '--with-pear';
    // Remove PHP version number from build directory.
    $filters['@/opt/php[0-9.-]*@'] = '/opt/php';
    // Normalize the extension dir to work around pecl install issue.
    $filters['@extension_dir *=.*@'] = 'extension_dir=/opt/php/lib';
    $filters['@--libdir=/usr/lib64/php@'] = '--libdir=/opt/php/lib';
}
// Acquia specific.
if ($platform == 'acquia') {
    // Remove PHP version number from build directory.
    $filters['@/usr/local/php[0-9.-]*@'] = '/usr/local/php';
}

// Filter and serialize final output.
foreach ($output as $k => $v) {
    $value = preg_replace(array_keys($filters), array_values($filters), $v);
    echo 'export '.$k.'='.escapeshellarg($value).PHP_EOL;
}
