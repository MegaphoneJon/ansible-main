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
      $groupHierarchy = buildGroupHierarchy($groups, NULL);
      // Pull the view that shows the list of Ansible-enabled servers from Drupal.
      $resource = 'views/server_list?display_id=services_1';
      if ($argv[1] == '--host' && $argv[2]) {
        $resource .= "&title=$argv[2]";
      }
      $result = get($headers, $resource, NULL);
      $inventory = buildServerList($result);
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

function buildGroupHierarchy($groups, $hierarchicalList, $parent = 0) {
  foreach ($groups as $k => $group) {
    if ($group->parent == $parent) {
      unset($groups[$k]);
      $hierarchicalList[$group->name]['children'] = buildGroupHierarchy($groups, NULL, $group->tid);
      if (empty($hierarchicalList[$group->name]['children'])) {
        unset($hierarchicalList[$group->name]['children']);
      }
    }
  }
  return $hierarchicalList;
}

/**
 * Accepts an array from Drupal Services Views, outputs an Ansible-compatible host list.
 *
 * @param array $view
 *   The array that holds all the db ids.
 *
 * @return string $inventory This is a JSON-encoded inventory file in the format Ansible expects.
 */
function buildServerList($view) {
  $inventory[] = [];
  foreach ($view as $server) {
    $inventory[$server->group]['hosts'][] = $server->fqdn;
    // Pull in all field values as Ansible variables.
    foreach ($server as $key => $value) {
      if ($value) {
        $key = str_replace(' ', '_', $key);
        $inventory['_meta']['hostvars'][$server->fqdn][$key] = $value;
      }
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
