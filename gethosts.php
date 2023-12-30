#!/usr/bin/php
<?php

require 'gethosts.cfg.php';

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
      exec('/usr/bin/pass ls megaphone/crm/restpassword', $password);
      $password = $password[0];
      $headers = login($password);
      if ($argv[1] == '--host' && $argv[2]) {
        $host = "$argv[2]";
      }
      $groupHierarchy = getGroups($headers);
      // Pull the view that shows the list of Ansible-enabled servers from Drupal.
      $servers = getServers($headers, $host ?? NULL);
      // If we're searching on a single server, only get that server's websites.
      $isServer = count($servers) === 1;
      $websites = getWebsites($headers, $host ?? NULL, $isServer);
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

function getServers(array $headers, ?string $host) : array {
  $endpoint = "server-rest/$host";
  return get($headers, $endpoint, NULL);
}

function getWebsites(array $headers, ?string $host, bool $isServer) : array {
  // Can't use contextual filter because bare URL is calculated.
  $endpoint = "website-rest?site=//$host";
  if ($isServer) {
    $endpoint = "website-rest?server=$host";  
  }

  return get($headers, $endpoint, NULL);
}

function getGroups(array $headers) : array {
  // Build a group hierarchy.
  $endpoint = 'group-rest';
  $groups = get($headers, $endpoint, NULL);
  return buildGroupHierarchy($groups);
}
/**
 * Accepts a taxonomy term list from Drupal Services.
 * Outputs a hierarchical array of children, suitable for merging into the taxonomy.
 */
// FIXME: The nosudo etc. isn't here.
function buildGroupHierarchy($groups) {
  $hierarchicalList = [];
  $groupTids = array_combine(array_column($groups, 'tid'), array_column($groups, 'name'));
  foreach ($groups as $group) {
    if ($groupTids[$group['parent']] ?? FALSE) {
      $hierarchicalList[$groupTids[$group['parent']]]['children'][] = $group['name'];
    }
  }
  // Websites are in their own hierarchy.
  $hierarchicalList['websites']['children'] = ['websites_dev', 'websites_test', 'websites_live'];  return $hierarchicalList;
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
  $inventory = [];
  foreach ($servers as $server) {
    // Pull in all field values as Ansible variables.
    foreach ($server as $key => $value) {
      if (!is_null($value)) {
        $key = str_replace(' ', '_', $key);
        $inventory['_meta']['hostvars'][$server['fqdn']][$key] = $value;
      }
      // Handle non-standard SSH ports.
      if ($server['security_ssh_port'] != 22) {
        $inventory['_meta']['hostvars'][$server['fqdn']]['ansible_port'] = $server['security_ssh_port'];
      }
    }
    // Put servers in groups.
    $groups = explode(', ', $server['group']);
    foreach ($groups as $group) {
      // Don't create a "blank" group.
      if (!$group) {
        continue;
      }
      // Don't get any localhosts besides your own.
      if ($group == 'localhosts' && $server['hostname'] !== gethostname()) {
        $ignoredServers[$server['hostname']] = TRUE;
        continue 2;
      }
      $inventory[$group]['hosts'][] = $server['fqdn'];
    }
  }
  // Websites go in their own group.
  if ($websites) {
    foreach ($websites as $website) {
      // Ignore localhost sites not on this localhost.
      if ($ignoredServers[$website['server']] ?? FALSE) {
        continue;
      }

      $inventory['_meta']['hostvars'][$website['bare_url']] = $website;
      // Put sites in groups per the "website_groups" field.
      $websiteGroups = explode(', ', $website['website_groups']) ?? NULL;
      foreach ($websiteGroups as $websiteGroup) {
        if ($websiteGroup) {
          $inventory[$websiteGroup][] = $website['bare_url'];
        }
      }
      // Put sites in groups based on environment.
      $envGroup = 'websites_' . strtolower($website['env']);
      $inventory[$envGroup][] = $website['bare_url'];
      // Also create maintenance groups.
      $maintenanceArray = [
        'maintenance_drupal' => 'Drupal',
        'maintenance_backdrop' => 'Backdrop',
        'maintenance_wp' => 'WordPress',
      ];
      foreach ($maintenanceArray as $group => $descriptor) {
        if (strpos($website['contract_type'], $descriptor . ' Maintenance') !== FALSE && $website['cms'] == $descriptor) {
          $inventory[$group][] = $website['bare_url'];
        }
      }
      // Drupal8 hack, sigh. Fix when D7 is no more.
      if (strpos($website['contract_type'], 'Drupal Maintenance') !== FALSE && $website['cms'] === 'Drupal8') {
        $inventory['maintenance_drupal8'][] = $website['bare_url'];
      }
      if (strpos($website['contract_type'], 'Civi Maintenance') !== FALSE && $website['civicrm'] === 'Yes') {
        $inventory['maintenance_civi'][] = $website['bare_url'];
      }
      // Also put website data in the metadata of their respective server for building Icinga templates.}
      $parentServer = $inventory['_meta']['hostvars'][$website['server']] ?? NULL;
      if ($parentServer) {
        $inventory['_meta']['hostvars'][$website['server']]['sites'][$website['bare_url']] = $website;
      }
    }
  }
  return $inventory;
}

function post($curlHeaders, $operation, $postFields = NULL) {
  $curlHeaders[] = 'Content-Type: application/json';
  $curlHeaders[] = 'Accept: application/json';
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

/**
 * Like POST but gets cookies too.
 */
function loginPost($curlHeaders, $operation, $postFields = NULL) {
  // A closure to use as a callback to get the cookie. See https://stackoverflow.com/a/25098798.
  $sessionId = '';
  $curlResponseHeaderCallback = function ($ch, $headerLine) use (&$sessionId) {
    if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $cookie) == 1)
      $sessionId = $cookie[1];
    return strlen($headerLine);
  };
  $curlHeaders[] = 'Content-Type: application/json';
  $curlHeaders[] = 'Accept: application/json';
  $curlOptions = [
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_POST => 1,
    CURLOPT_HEADERFUNCTION => $curlResponseHeaderCallback,
    CURLOPT_URL => ENDPOINT . $operation,
    CURLOPT_POSTFIELDS => $postFields ? json_encode($postFields) : NULL,
  ];
  $curl = curl_init();
  curl_setopt_array($curl, $curlOptions);
  $result = curl_exec($curl);
  curl_close($curl);
  $result = json_decode($result);
  $csrfToken = $result->csrf_token;
  return [$sessionId, $csrfToken];
}

function get(array $curlHeaders, string $endpoint) {
  $curlHeaders[] = 'Accept: application/json';
  $curlOptions = [
    CURLOPT_HTTPHEADER => $curlHeaders,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_URL => ENDPOINT . $endpoint // . '?_format=json',
  ];
  $curl = curl_init();
  curl_setopt_array($curl, $curlOptions);
  $result = curl_exec($curl);
  curl_close($curl);
  return json_decode($result, TRUE);
}

/**
 * Handle login (including getting a CSRF header)
 * @return array $headers The necessary headers to perform further POST/GET actions.
 */
function login($password) {
  $creds = [
    'name' => USERNAME,
    'pass' => $password,
  ];

  [$sessionId, $csrfToken] = loginPost(NULL, 'user/login', $creds);
  $headers[] = 'X-CSRF-Token: ' . $csrfToken;
  $headers[] = 'Cookie: ' . $sessionId;
  return $headers;
}

function help() {
  echo "Usage:\n";
  echo " --help               This message\n";
  echo " --list               Show all hosts\n";
  echo " --host <hostname>    Show a single host specified by <hostname>";
}
