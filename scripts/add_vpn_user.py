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
    shell_script=sys.argv[2]
    
    #####################
    # Création de l'utilisateur si nécessaire
    #####################
   
    ansible_vpn_user_servvpn = "ansible servvpn -i " + user_dir_front + "/script_hosts -m script -a " + user_dir_front + "/" + shell_script + " -s"
    #print ansible_vpn_user_servvpn;
    #####################
    # Exec ansible
    #####################
    status_vpn_user_servvpn = subprocess.call(ansible_vpn_user_servvpn , shell=True)
    #print status_delvpn
