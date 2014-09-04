<?php

/**
 * Main config
 * @package Flupp CMS
 * @author  Christian Klisch
 * @since   0.0.1
 */
define('ROOT', realpath(dirname(__FILE__)) . '/');
define('CONTENT', ROOT . 'content/');
define('EXTENSION', '.md');
define('SYSTEM', ROOT . 'system/');
define('MODULES', ROOT . 'modules/');
define('THEMES', ROOT . 'themes/');
define('THEME', ROOT . 'theme/');
define('CACHE', SYSTEM . 'cache/');
define('CONFIG', 'config.yaml');

require_once (ROOT . 'vendor/autoload.php');
require_once (SYSTEM . 'framework.php');

// ready!