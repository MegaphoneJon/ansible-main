#!/usr/bin/php
<?php

$params['api_user'] = 'megaphone-ansible';
exec('/usr/bin/pass ls megaphone/mfpl/megaphone-ansible', $api_password);
$params['api_password'] = $api_password[0];
$params['hosting_order_id'] = 1003514;
$params['url'] = 'https://members.mayfirst.org/cp/api.php';

if (!isset($argv[3])) {
  echo "You need more parameters to execute this command.\n";
  echo "Usage: mfplapi.php <DNSTYPE> <FQDN> <ip address>\n";
  echo "E.g.: mfplapi.php AAAA orange.megaphonetech.com 1.2.3.4\n";
  echo "Supported DNS types are currently A and AAAA.\n";
  exit(1);
}
$params['dnstype'] = strtolower($argv[1]);
$params['fqdn'] = $argv[2];
$params['ip'] = $argv[3];

try {
  $result = query_dns($params);
}
catch (Exception $e) {
  echo 'Error: ', $e->getMessage(), "\n";
  exit(127);
}
list($dns, $item_id) = $result;
if (!$dns) {
  // DNS not found.
  write_dns($params, 'insert');
  exit(0);
}
elseif ($dns != $params['ip']) {
  // DNS found but doesn't match our provisioning.
  write_dns($params, 'update', $item_id);
  exit(0);
}
else {
  echo "DNS already exists and is correct.";
  exit(0);
}

/**
 * Inserts or updates an MFPL DNS record (see $action).
 * Update requires an $item_id.
 * Other values are in $params['dns_fqdn'] and $params['dns_ip'].
 */
function write_dns($params, $action, $item_id = NULL) {
  echo "Write DNS: $action\n";
  $data = [
    'action' => $action,
    'object' => 'item',
    'user_name' => $params['api_user'],
    'user_pass' => $params['api_password'],
    'set:service_id' => 9,
    'set:hosting_order_id' => $params['hosting_order_id'],
    'set:dns_type' => $params['dnstype'],
    'set:dns_fqdn' => $params['fqdn'],
    'set:dns_ip' => $params['ip'],
    'set:dns_sshfp_algorithm' => '1',
    'set:dns_sshfp_type' => '1',
    'set:dns_sshfp_fpr' => '1',
  ];
  if ($item_id) {
    $data['where:item_id'] = $item_id;
  }
  $result = red_api($params['url'], $data);
}

/**
 * Checks $params['fqdn'] to see if it has an IPv4 address in May First.
 * Returns an IP if one exists, NULL if not.
 */
function query_dns($params) {

  // Non-A records may exist; we can't have both CNAME and A so we need to check.
  $data = [
    'action' => 'select',
    'object' => 'item',
    'user_name' => $params['api_user'],
    'user_pass' => $params['api_password'],
    'where:service_id' => 9,
    'where:dns_fqdn' => $params['fqdn'],
  ];

  $result = red_api($params['url'], $data);
  $return = NULL;
  $values = $result->values ?? [];
  foreach ($values as $dnsEntry) {
    if ($dnsEntry->dns_type == 'cname') {
      throw new Exception('You can\'t create an A/AAAA record because a CNAME record already exists');
    }
    if ($dnsEntry->dns_type == $params['dnstype']) {
      $return[0] = $result->values[0]->dns_ip;
      $return[1] = $result->values[0]->item_id;
    }
  }
  return $return;
}

/**
 * Wrapper function for the MFPL API.
 * @see https://support.mayfirst.org/wiki/control-panel/alternative-interfaces#UsingthewebaccessibleAPI
 */
function red_api($url, $data) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $result = curl_exec($ch);
  return json_decode($result);
}
