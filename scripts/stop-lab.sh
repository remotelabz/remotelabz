#!/bin/bash
#
# This script was created by Julien Hubert
# (c) URCA, 2019
#
# TODO: For now, this script aims to execute all the app parts (vm, vpn)
# on the same server. Need to provide a way to dispatch components (API ?)

set -e

if ! [ -x "$(command -v xmllint)" ]; then
    echo 'Error: xmllint is not installed. Please install it and try again' >&2
    exit 1
fi

if ! [ -x "$(command -v qemu-system-"$(uname -i)")" ]; then
    echo 'Error: qemu is not installed. Please install it and try again' >&2
    exit 1
fi

if ! [ -x "$(command -v ovs-vsctl)" ]; then
    echo 'Error: openvswitch is not installed. Please install it and try again' >&2
    exit 1
fi

if ! [ -f "$1" ]; then
    echo 'Error: lab file not found.' >&2
    exit 1
fi

LAB_FILE=$1

xml() {
    xmllint --xpath "string($1)" "${LAB_FILE}"
}

LAB_USER=$(xml /lab/user/login)
LAB_NAME=$(xml "/lab/name")

#####################
# OVS
#####################
ovs() {
    OVS_NAME=$(xml "/lab/nodes/device[@type='switch']/name")

    ovs-vsctl del-br "${OVS_NAME}"
}

#####################
# VPN
#####################

vpn() {
    VPN_ACCESS=$(xml "/lab/tp_access")

    if [ "${VPN_ACCESS}" = "vpn" ]; then
        OVS_IP=$(xml "/lab/nodes/device[@type='switch']/vpn/ipv4")

        echo "${OVS_IP}"

        # TODO: Finish this later
        #
        # network_lab = lab.xpath("/lab/init/network_lab")[0].text
        # network_user = lab.xpath("/lab/init/network_user")[0].text
        # script_addvpn_servvm += "ip route add %s via %s\n"%(network_user,frontend_ip)
        # script_delvpn_servvm += "ip route del %s via %s\n"%(network_user,frontend_ip)
        # addr_servvm = lab.xpath("/lab/init/serveur/IPv4")[0].text
        
        # script_addvpn_frontend += "ip route add %s via %s\n"%(network_lab,addr_servvm)
        # script_addvpn_frontend += "ip route add %s via %s\n"%(network_user,addr_vpn)
        # script_delvpn_frontend += "ip route del %s via %s\n"%(network_lab,addr_servvm)
        # script_delvpn_frontend += "ip route del %s via %s\n"%(network_user,addr_vpn)

        # script_addvpn_servvpn += "ip route add %s via %s\n"%(network_lab,addr_host_vpn)

        # script_delvpn_servvpn += "ip route del %s via %s\n"%(network_lab,addr_host_vpn)
        
        # Ajouter une connexion internet aux machines - Mise en place d'un patch entre l'OVS du system et l'OVS du lab

        # script_addnet = "ovs-vsctl -- add-port %s patch-ovs%s-0 -- set interface patch-ovs%s-0 type=patch options:peer=patch-ovs0-%s -- add-port br0 patch-ovs0-%s  -- set interface patch-ovs0-%s type=patch options:peer=patch-ovs%s-0\n"%(ovs_name,ovs_name,ovs_name,ovs_name,ovs_name,ovs_name,ovs_name)
        # script_addnet += "iptables -t nat -A POSTROUTING -s %s -o br0 -j MASQUERADE"%network_lab
        # script_delnet += "ovs-vsctl del-port patch-ovs%s-0\n"%ovs_name
        # script_delnet += "ovs-vsctl del-port patch-ovs0-%s\n"%ovs_name
        # script_delnet += "iptables -t nat -D POSTROUTING -s %s -o br0 -j MASQUERADE"%network_lab
    fi
}

#####################
# QEMU
#####################

qemu() {
    NB_VM=$(xml "count(/lab/nodes/device[@hypervisor='qemu'])")
    
    VM_INDEX=1
    # POSIX Standard
    while [ ${VM_INDEX} -le $((NB_VM)) ]; do
        VM_PATH="/lab/nodes/device[@hypervisor='qemu'][${VM_INDEX}]"

        VNC_PORT=$(xml "${VM_PATH}/interface_control/@port")

        kill -9 "$(netstat -tnap | grep $((VNC_PORT+1000)) | awk -F "[ /]*" '{print $7}')"

        NB_NET_INT=$(xml "count(${VM_PATH}/interface/@type[1])")

        VM_IF_INDEX=1
        echo "Deleting interfaces"
        while [ ${VM_IF_INDEX} -le $((NB_NET_INT)) ]; do
            NET_IF_NAME=$(xml "${VM_PATH}/interface[${VM_IF_INDEX}]/@type")

            ovs-vsctl del-port "${OVS_NAME}" "${NET_IF_NAME}"
            ip link set "${NET_IF_NAME}" down
            ip link delete "${NET_IF_NAME}"
            
            VM_IF_INDEX=$((VM_IF_INDEX+1))
        done

        VM_INDEX=$((VM_INDEX+1))
    done

    rm -rf /opt/remotelabz/"${LAB_USER}"/"${LAB_NAME}"/${VM_INDEX}
}

#####################
# Main
#####################

main() {
    qemu
    vpn # TODO: Conditional (are we executing on a vpn server?)
    ovs
}

main
exit 0