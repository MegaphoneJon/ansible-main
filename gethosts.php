#!/usr/bin/php
<?php

const ENDPOINT = 'https://crm.megaphonetech.com/rest/';
const USERNAME = 'restuser';
const PASSWORD = 'dbSfzFqvHXDHBhCy1wI4OO1dKhH2ResX';

function run($argv) {
  if (empty($argv[1])) {
    echo "No command found.\n\n";
    help();
    exit;
  }
  switch ($argv[1]) {
    case '--list':
      $headers = login();
      // Pull the view that shows the list of Ansible-enabled servers from Drupal.
      $result = get($headers, 'views/server_list?display_id=services_1', NULL);
      $inventory = buildServerList($result);
      echo $inventory;
      break;

    case '--host':
      $headers = login();
      // Pull the view that shows the list of Ansible-enabled servers from Drupal.
      $result = get($headers, "views/server_list?display_id=services_1&title=$argv[2]", NULL);
      $inventory = buildServerList($result);
      echo $inventory;
      break;

    default:
      echo "Unknown command.\n\n";
    case '--help':
      help();
  }
}
run($argv);


/**
 * Accepts an array from Drupal Services Views, outputs an Ansible-compatible host list.
 *
 * @param array $view
 *   The array that holds all the db ids.
 *
 * @return string $inventory This is a JSON-encoded inventory file in the format Ansible expects.
 */
function buildServerList($view) {
  $inventory['hosts'] = [];
  foreach ($view as $server) {
    $inventory['hosts'][] = $server->fqdn;
    if ($server->username) {
      $inventory['_meta']['hostvars'][$server->fqdn]['ansible_user'] = $server->username;
    }
  }
  //print_r($inventory);
  return json_encode($inventory);
}

/**
 * Get the CSRF header.
 * @return array the curl options for this transaction.
 */
function post_csrf_header() {
  $csrfToken = post([], 'user/token.json', NULL);
  return 'X-CSRF-Token: ' . $csrfToken->token;
}

function post($curlHeaders, $operation, $postFields = NULL) {
  $curlHeaders[] = 'Content-Type: application/json';
  $curlOptions = [
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_POST => 1,
    CURLOPT_URL => ENDPOINT . $operation,
    CURLOPT_POSTFIELDS => $postFields ? json_encode($postFields) : NULL,
  ];
  $curl = curl_init();
  curl_setopt_array($curl, $curlOptions);
  $result = curl_exec($curl);
  curl_close($curl);
  return json_decode($result);
}

function get($curlHeaders, $operation, $body = NULL) {
  $curlOptions = [
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_URL => ENDPOINT . $operation,
  ];
  $curl = curl_init();
  curl_setopt_array($curl, $curlOptions);
  $result = curl_exec($curl);
  curl_close($curl);
  return json_decode($result);
}

/**
 * Handle login (including getting a CSRF header)
 * @return array $headers The necessary headers to perform further POST/GET actions.
 */
function login() {
  // This all is the CSRF token + login process
  $headers[] = post_csrf_header();
  $creds = [
    'username' => USERNAME,
    'password' => PASSWORD,
  ];

  $result = post($headers, 'user/login.json', $creds);
  $headers[] = "Cookie: $result->session_name=$result->sessid";
  return $headers;
}

function help() {
  echo "Usage:\n";
  echo " --help               This message\n";
  echo " --list               Show all hosts\n";
  echo " --host <hostname>    Show a single host specified by <hostname>";
}
