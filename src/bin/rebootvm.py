#!/usr/bin/python
# -*- coding: utf8 -*-

from lxml import etree
import subprocess
import sys

#                 _
# _ __ ___   __ _(_)_ __
#| '_ ` _ \ / _` | | '_ \
#| | | | | | (_| | | | | |
#|_| |_| |_|\__,_|_|_| |_|


if __name__ == "__main__":
    user_dir_front=sys.argv[1]
    name=sys.argv[2]

    name=sys.argv[2].replace(" ","\ ")
    #####################
    # Création de l'utilisateur si nécessaire
    #####################

    ansible_del = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_reboot.sh '%s'\" -s"%name
    #print ansible_del;
    #####################
    # Exec ansible
    #####################
    status_del = subprocess.call(ansible_del , shell=True)
    #print status_del
