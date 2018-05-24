<?php

if (!defined('ENDPOINT')) {
	define('ENDPOINT', 'https://crm.megaphonetech.com/rest/');
}
if (!defined('USERNAME')) {
	define('USERNAME', 'restuser');
}

if (isset($argv[1]) && in_array($argv[1], ['ENDPOINT', 'USERNAME'])) {
	print constant($argv[1]);
}