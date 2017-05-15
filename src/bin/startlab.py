#!/usr/bin/python
# -*- coding: utf8 -*-

from lxml import etree
import subprocess
import sys

#                                               _
# ___  __ _ _   ___   _____  __ _  __ _ _ __ __| | ___
#/ __|/ _` | | | \ \ / / _ \/ _` |/ _` | '__/ _` |/ _ \
#\__ \ (_| | |_| |\ V /  __/ (_| | (_| | | | (_| |  __/
#|___/\__,_|\__,_| \_/ \___|\__, |\__,_|_|  \__,_|\___|
#                           |___/

def save_script(script_string,script_file):
    text_file = open(user_dir_front+"/"+script_file, "w")
    text_file.write(script_string)
    text_file.close()

#                 _
# _ __ ___   __ _(_)_ __
#| '_ ` _ \ / _` | | '_ \
#| | | | | | (_| | | | | |
#|_| |_| |_|\__,_|_|_| |_|


if __name__ == "__main__":
    ficlab = sys.argv[1]
    addr_svc=sys.argv[2]
    user_dir_front=sys.argv[3]
    lab = etree.parse(ficlab)
    

    script_user = "#!/bin/bash \n\n"
    script_ovs = "#!/bin/bash \n\n"
    script_vm = "#!/bin/bash \n\n"

    script_del=""

    user = lab.xpath("/lab/user/@login")[0]
    lab_name = lab.xpath("/lab/lab_name")[0].text

    ansible_user="adminVM"
    ansible_pass="adminVM1516"
    #addr_svc="10.22.9.117"

    #####################
    # Création de l'utilisateur si nécessaire
    #####################

    script_user += "user_exists=$(awk -F':' '{print $1}' /etc/passwd | grep -w %s)\n"%user
    script_user += "if [ -z $user_exists ] \nthen\n"
    script_user += "\tpass=$(openssl passwd -crypt %s)\n"%user
    script_user += "\t$(useradd -s /bin/bash --create-home --password $pass %s)\nfi \n"%user
    script_user += "$(mkdir /home/%s/%s)"%(user,lab_name)

    #####################
    # Création de l'OVS
    #####################

    ovs_name = lab.xpath("/lab/nodes/device[@property='switch']/nom")[0].text
    script_ovs += "ovs-vsctl add-br %s \n"%ovs_name
    script_del = "ovs-vsctl del-br %s\n"%ovs_name + script_del

    script_ovs += "ip link set %s up \n"%ovs_name
    #script_del = "ovs-vsctl dlink set  %s down\n"%ovs_name + script_del

    #####################
    # Gestion des VM Qemu
    #####################

    script_vm += "##########\n# Gestion des VM Qemu##########\n"
    nb_vm = int(lab.xpath("count(/lab/nodes/device[@hypervisor='qemu'])"))
    #VERIFIER LE CALCUL CAR CA MARCHE MAIS PAS SUR QUE NOUS N'AYONS PAS UN PROBLEME SUR LES PORTS
    index_port_vnc = int(lab.xpath("/lab/init/serveur/index")[0].text)
    for i in range (1, nb_vm + 1):
        script_vm += "\n# VM %s\n"%i
        vm_path = "/lab/nodes/device[@hypervisor='qemu'][%s]"%i

        # création de l'image relative
        source = "/home/" + ansible_user + "/images/" + lab.xpath(vm_path + "/@image")[0]
        #dest = "/home/" + user + "/" + lab_name + "/" + lab.xpath(vm_path +"/@relativ_path")[0] + "/" + lab.xpath(vm_path +"/@image")[0] + "-" + str(i)
        dest = "/home/" + user + "/" + lab_name + "/" + lab.xpath(vm_path +"/@image")[0] + "-" + str(i)
        script_vm += "qemu-img create -b %s -f qcow2 %s \n"%(source,dest)
        script_del = "delete %s\n"%dest + script_del

        # connexion au réseau
        ifname = lab.xpath(vm_path + "/interface/@logical_name")[0]
        script_vm += "ip tuntap add mode tap %s \n"%ifname
        script_del = "ip link delete %s\n"%ifname + script_del
        script_vm += "ip link set %s up \n"%ifname
        script_del = "ip link set %s down\n"%ifname + script_del
        script_vm += "ovs-vsctl add-port %s %s\n"%(ovs_name,ifname)
        script_del = "ovs-vsctl del-port %s %s\n"%(ovs_name,ifname) + script_del

        #démarrage d'une machine
        name = lab.xpath(vm_path + "/nom")[0].text
        start_vm = "qemu-system-x86_64 -machine accel=kvm:tcg -name %s -daemonize "%name
        #start_vm = "qemu-system-x86_64 -name %s -daemonize "%name

        memory = lab.xpath(vm_path + "/system/@memory")[0]
        sys_param = "-m %s -hda %s "%(memory,dest)

        mac = lab.xpath(vm_path + "/interface/@mac_address")[0]
        net_param = "-net nic,macaddr=%s -net tap,ifname=%s,script=no "%(mac,ifname)

        vnc_addr = lab.xpath(vm_path + "/interface_control/@IPv4")[0]
        vnc_port = lab.xpath(vm_path + "/interface_control/@port")[0]
        access_param = "-vnc %s:%s,websocket=%s "%(vnc_addr,i+index_port_vnc,vnc_port)
        local_param = "-k fr -localtime -usbdevice tablet "

        script_vm += start_vm + sys_param + net_param + access_param + local_param + "\n"

    script_del = "for i in `ps -ef | grep qemu |grep %s | grep -v grep | awk '{print $2}'`; do kill -9 $i; done\n"%lab_name + script_del
    script_del = "#!/bin/bash \n\n" + script_del

    save_script(script_ovs,"script_ovs.sh")
    save_script(script_vm,"script_vm.sh")
    save_script(script_user,"script_user.sh")
    save_script(script_del,"script_del.sh")

    #addr_svc = lab.xpath("/lab/init/serveur/IPv4")[0].text

    ansible_host = "svc1 ansible_ssh_host=%s ansible_ssh_user=%s ansible_ssh_pass='%s' ansible_sudo_pass='%s'\n"%(addr_svc,ansible_user,ansible_pass,ansible_pass)
    ansible_host += "[server]\nsvc1\n"
    text_file = open(user_dir_front+"/"+"script_hosts", "w")
    text_file.write(ansible_host)
    text_file.close()

    ansible_user = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a " + user_dir_front + "/script_user.sh -s"
    ansible_ovs = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a " + user_dir_front + "/script_ovs.sh -s"
    ansible_vm = "ansible svc1 -i " + user_dir_front + "/script_hosts -m script -a " + user_dir_front + "/script_vm.sh -s"

    #####################
    # Exec ansible
    #####################
    status_user = subprocess.call(ansible_user , shell=True)
    #print status_user
    status_ovs =subprocess.call(ansible_ovs , shell=True)
    #print status_ovs
    status_vm =subprocess.call(ansible_vm , shell=True)
    #print status_vm
