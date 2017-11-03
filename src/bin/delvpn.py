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

    ansible_delvpn_servvm = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_delvpn_servvm.sh\" -s"
    ansible_delvpn_frontend = "ansible frontend -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_delvpn_frontend.sh\" -s"
    ansible_delvpn_servvpn = "ansible servvpn -i " + user_dir_front + "/script_hosts -m script -a \"" + user_dir_front + "/script_delvpn_servvpn.sh\" -s"
    #print ansible_delvpn_servvm;
    #####################
    # Exec ansible
    #####################
    status_delvpn_frontend = subprocess.call(ansible_delvpn_frontend , shell=True)
    status_delvpn_servvm = subprocess.call(ansible_delvpn_servvm , shell=True)
    status_delvpn_servvpn = subprocess.call(ansible_delvpn_servvpn , shell=True)
    #print status_delvpn
