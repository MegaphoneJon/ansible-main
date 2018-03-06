#!/usr/bin/php
<?php

const ENDPOINT = 'https://crm.megaphonetech.com/rest/';
const USERNAME = 'restuser';

function run($argv) {
  if (empty($argv[1])) {
    echo "No command found.\n\n";
    help();
    exit;
  }
  switch ($argv[1]) {
    case '--list':
    case '--host':
      # Get password from the "pass" utility.
      exec('pass ls megaphone/crm/restpassword', $password);
      $password = $password[0];
      $headers = login($password);
      // Build a group hierarchy.
      $groupResource = 'taxonomy_term';
      $groups = get($headers, $groupResource, NULL);
      $groupHierarchy = buildGroupHierarchy($groups);
      // Pull the view that shows the list of Ansible-enabled servers from Drupal.
      $resource = 'views/server_list?display_id=services_1';
      if ($argv[1] == '--host' && $argv[2]) {
        $resource .= "&title=$argv[2]";
      }
      $servers = get($headers, $resource, NULL);
      // Get the websites too.
      $resource = 'views/website_list?display_id=services_1';
      if ($argv[1] == '--host' && $argv[2]) {
        $resource .= "&title=$argv[2]";
      }
      $websites = get($headers, $resource, NULL);
      $inventory = buildServerList($servers, $websites);
      $inventory = json_encode(array_merge_recursive($inventory, $groupHierarchy));
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
 * Accepts a taxonomy term list from Drupal Services.
 * Outputs a hierarchical array of children, suitable for merging into the taxonomy.
 */
function buildGroupHierarchy($groups) {
  foreach ($groups as $k => $group) {
    foreach ($groups as $childKey => $childGroup) {
      if ($childGroup->parent == $group->tid) {
        $hierarchicalList[$group->name]['children'][] = $childGroup->name;
      }
    }
  }
  return $hierarchicalList;
}

/**
 * Accepts an array from Drupal Services Views, outputs an Ansible-compatible host list.
 *
 * @param array $servers
 *   The array that holds all the server data.
 * @param array $websites
 *   The array that holds all the website data.
 *
 * @return string $inventory This is a JSON-encoded inventory file in the format Ansible expects.
 */
function buildServerList($servers, $websites) {
  $inventory[] = [];
  foreach ($servers as $server) {
    $inventory[$server->group]['hosts'][] = $server->fqdn;
    // Pull in all field values as Ansible variables.
    foreach ($server as $key => $value) {
      if (!is_null($value)) {
        $key = str_replace(' ', '_', $key);
        $inventory['_meta']['hostvars'][$server->fqdn][$key] = $value;
      }
    }
    foreach ($websites as $website) {
      $inventory['_meta']['hostvars'][$website->server]['sites'][$website->bare_url] = $website;
    }
  }
  return $inventory;
  //return json_encode($inventory);
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
function login($password) {
  $creds = [
    'username' => USERNAME,
    'password' => $password,
  ];

  $result = post(NULL, 'user/login.json', $creds);
  $headers[] = "Cookie: $result->session_name=$result->sessid";
  return $headers;
}

function help() {
  echo "Usage:\n";
  echo " --help               This message\n";
  echo " --list               Show all hosts\n";
  echo " --host <hostname>    Show a single host specified by <hostname>";
}
