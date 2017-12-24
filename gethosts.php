#!/usr/bin/php
<?php
$test = [
/*         "megaphone_saas" => [
           "hosts" => [
             "server1.megaphonetech.com",
             "server2.megaphonetech.com",
             "server3.megaphonetech.com",
            ],
         ],
*/
         "lamp" => [
           "hosts" => [
             "orange.megaphonetech.com",
             "nwu.org",
           ],
           "children" => [
             "megaphone_saas",
           ],
         ],
         "docker" => [
           "hosts" => [
             "red.megaphonetech.com",
           ],
         ],
         "_meta" => [
           "hostvars" => [
             "orange.megaphonetech.com" => [
               "ansible_user" => "root",
             ],
             "nwu.org" => [
               "ansible_user" => "root",
             ]
           ]
         ]
        ];
function help() {
  echo "Usage:\n";
  echo " --help               This message\n";
  echo " --list               Show all hosts\n";
  echo " --host <hostname>    Show a single host specified by <hostname>";
}

function run($argv) {
  if (empty($argv[1])) {
    echo "No command found.\n\n";
    help();
    exit;
  }
  switch ($argv[1]) {
    case '--list':
      echo json_encode($test);
      break;

    case '--host':
      echo json_encode($test);
      break;

    default:
      echo "Unknown command.\n\n";
    case '--help':
      help();
  }
}
run($argv);
