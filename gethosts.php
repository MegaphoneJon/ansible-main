#!/usr/bin/php
<?php
$test = [
         "megaphone_saas" => [
           "hosts" => [
             "server1.megaphonetech.com",
             "server2.megaphonetech.com",
             "server3.megaphonetech.com",
            ],
         ],
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
echo json_encode($test);
