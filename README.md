# README #

A repository for the Megaphone Ansible playbooks.

Logging
-------

To enable logging, run the following:

    sudo touch /var/log/ansible.log
    sudo chown $USER /var/log/ansible.log

Then enable logrotate for ansible.log by placing this in /etc/logrotate.d/ansible:

    /var/log/ansible.log {
        rotate 4
        weekly
        compress
        missingok
    }
