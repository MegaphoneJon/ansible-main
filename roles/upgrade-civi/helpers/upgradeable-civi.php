#!/usr/bin/php
<?php
eval(`cv php:boot`);

$e = new CRM_Admin_Page_Extensions();
$localExtensionList = $e->formatLocalExtensionRows();
$remoteExtensionList = $e->formatRemoteExtensionRows($localExtensionList);
$i = 0;
foreach ($remoteExtensionList as $e) {
  if ($e['is_upgradeable']) {
    $upgradeableExtensions[$i]['id'] = $e['id'];
    $upgradeableExtensions[$i]['name'] = $e['name'];
    $upgradeableExtensions[$i]['version'] = $e['version'];
    $i++;
  }
}
print_r(json_encode($upgradeableExtensions));
