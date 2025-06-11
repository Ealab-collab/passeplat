<?php

use Symfony\Component\Yaml\Yaml;
 
require_once 'vendor/autoload.php';

/*
 *  Check security and write on disk the yaml file for config.
 */

// Check security token. @todo : do it in a more standard way ?
// @todo : token in general config file.
if (!isset($_POST['token']) || $_POST['token'] <> 'AAAAB3NzaC1yc2EAAAADAQABAAABAQCCamlqKq2oTR3A/I22EGPhpOujAPptTZY9TN1vZqely66LrbfV9UZJEdng9QzxNv4v7s6V7HQgvwB7WINQqzmmQPaMz/5bYqHOfU/QwsdH6ilubmpn2G6MCjYt+IF7ZbonevKAAy4nOauIxlM7kv7zIsYGBsZF9v2bdBIpMwZG8w9m6DDEMW0DrUaeWFDbMDZ89M31gqu3nb+TqrV7QK6DZDY1G8TL88xIipApPHpJoI7PQ2id0q0ggoZIpMxDMLC7+X') {
    return http_response_code(403);
}

// Parameter missing.
if (!isset($_POST['type']) || !isset($_POST['configs']) || !isset($_POST['uuid']) || ($_POST['type'] == 'webservice' && !isset($_POST['wsid']))) {
    return http_response_code(400);
}

// Parameters.

// Case of user and not webservice.
$wsid = '';
$type = $_POST['type'];
if ($type == 'webservice') {
    $wsid = $_POST['wsid'] . '---';
}
$uuid = $_POST['uuid'];
$extension = 'json';
$configs = $_POST['configs'];
$write = $configs;

// Change in case of yaml file.
if (isset($_POST['extension']) && $_POST['extension'] == 'yaml') {
    $configsArray = json_decode($configs, TRUE);
    $write = Yaml::dump($configsArray, 2);
    $extension = 'yaml';
}

// Write on disk the yaml configuration.
file_put_contents("config/app/$type/$wsid$uuid.$extension", $write);
return http_response_code(200);