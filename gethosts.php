#!/usr/bin/php
<?php

const ENDPOINT = 'https://crm.megaphonetech.com/rest/';
const USERNAME = 'restuser';
const PASSWORD = 'dbSfzFqvHXDHBhCy1wI4OO1dKhH2ResX';

/**
 *  Get the CSRF header.
 *  $curl_options array the curl options for this transaction.
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
  // DEBUG shit
  if ($operation == 'node') {
    print_r($curlOptions[CURLOPT_HTTPHEADER]);
    print_r(curl_getinfo($curl));
  }
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

// This all is the CSRF token + login process
$headers[] = post_csrf_header();
$creds = [
  'username' => USERNAME,
  'password' => PASSWORD,
];

$result = post($headers, 'user/login.json', $creds);
$headers[] = "Cookie: $result->session_name=$result->sessid";

$result =  get($headers, 'views/server_list?display_id=services_1', NULL);
//print_r($result);

$inventory = buildServerList($result);
echo json_encode($inventory);
/*
 * Accepts an array from Drupal Services Views, outputs an Ansible-compatible host list.
 *
 * @param array $view
 *   The array that holds all the db ids.
 *
 * @return CRM_Contribute_BAO_Contribution|\CRM_Core_Error
 */

function buildServerList($view) {
  $inventory['hosts'] = [];
  foreach ($view as $server) {
    $inventory['hosts'][] = $server->fqdn;
    if ($server->username) {
      $inventory['_meta']['hostvars'][$server->fqdn]['ansible_user'] = $server->username;
    }
  }
  return $inventory;
}
