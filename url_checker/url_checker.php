<?php

// Read our configuration file (or die)
$config = url_checker_get_config();

// Make note of the site and environment we are running on.
$env = $_ENV['PANTHEON_ENVIRONMENT'];
$site = $_ENV['PANTHEON_SITE_NAME'];

// If we are not running in the live environment, or if there
// is no 'base_url' defined in config.json, then generate an
// appropriate URL for this current environment.
if ($_ENV['PANTHEON_ENVIRONMENT'] != 'live') {
  $config['base_url'] = 'http://' . $env . '-' . $site . '.pantheon.io';
}

// If the base url does not end in a '/', then add one to the end.
if ($config['base_url'][strlen($config['base_url'])] != '/') {
  $config['base_url'] .= '/';
}

$failed = 0;
$results = array();

foreach ($config['check_paths'] as $path) {
  $status = url_checker_test_url($config['base_url'] . $path);
  $results[] = array(
    'url' => $config['base_url'] . $path,
    'status' => $status
  );
  if ($status != 200) {
    $failed++;
  }
}

$output = url_checker_build_output($results, $failed);
print $output;

if ($failed > 0) {
  $subject = 'Failed status check (' . $failed . ')';
  $message = "Below is a list of each tested url and its status:\n\n";
  $message .= $output;
  mail($config['email'], $subject, $message);
}

/**
 * Returns decoded config defined in config.json
 */
function url_checker_get_config() {
  $config_file = __DIR__ . '/config.json';
  if (!file_exists($config_file)) {
    die('Config file not found.');
  }
  $config_file_contents = file_get_contents($config_file);
  if (empty($config_file_contents)) {
    die('Config file could not be read.');
  }
  $config = json_decode($config_file_contents);
  if (!$config) {
    die('Config file did not contain valid json.');
  }
  return $config;
}

/**
 * Constructs workflow output
 */
function url_checker_build_output($results, $failed) {
  $output = "\nURL Checks\n--------\n";
  foreach ($results as $item) {
    $output .= '  ' . $item['status'] . ' - ' . $item['url'] . "\n";
  }
  $output .= "--------\n" . count($failed) . " failed\n\n";
  return $output;
}

/**
 * Try to access the specified URL, and return the http status code.
 */
function url_checker_test_url($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);
  return $info['http_code'];
}