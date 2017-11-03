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
    
    #####################
    # Création de l'utilisateur si nécessaire
    #####################

    ansible_addvpn_servvm = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_addvpn_servvm.sh\" -s"
    ansible_addvpn_frontend = "ansible frontend -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_addvpn_frontend.sh\" -s"
    ansible_addvpn_servvpn = "ansible servvpn -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_addvpn_servvpn.sh\" -s"
    #print ansible_addvpn_servvm;
    #####################
    # Exec ansible
    #####################
    status_addvpn_servvm = subprocess.call(ansible_addvpn_servvm , shell=True)
    status_addvpn_frontend = subprocess.call(ansible_addvpn_frontend , shell=True)
    status_addvpn_servvpn = subprocess.call(ansible_addvpn_servvpn , shell=True)
    #print status_addvpn
