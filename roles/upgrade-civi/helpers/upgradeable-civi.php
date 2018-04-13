<?php
$e = new CRM_Admin_Page_Extensions; 
$localExtensionList = $e->formatLocalExtensionRows(); 
$remoteExtensionList = $e->formatRemoteExtensionRows($localExtensionList); 
foreach($remoteExtensionList as $extension) { 
  if ($extension['is_upgradeable']) { 
    echo $extension['id'] . "\n";
  }
}
