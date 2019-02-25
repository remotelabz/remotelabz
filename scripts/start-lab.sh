#!/bin/bash
#
# This script was created by Julien Hubert
# (c) URCA, 2019
#
# TODO: For now, this script aims to execute all the app parts (vm, vpn)
# on the same server. Need to provide a way to dispatch components (API ?)

set -e

# Emulate python script parameters with .env file
# TODO: Use these parameters
#
# ENV_FILE=${PWD}/../.env

# if [ -f ${ENV_FILE} ]; then
#     source ${ENV_FILE}
# else
#     echo "Error: Environment file .env not found in ${ENV_FILE}. Please check this file exists and try again." >&2
#     exit 1
# fi

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

if ! [ -x "$(command -v websockify)" ]; then
    if ! [ -d "/opt/remotelabz/websockify/.git" ]; then
        git clone https://github.com/novnc/websockify.git /opt/remotelabz/websockify
    fi

    OLD_DIR=$(pwd)
    cd /opt/remotelabz/websockify/
    python setup.py install
    cd "${OLD_DIR}"
    # echo 'Error: openvswitch is not installed. Please install it and try again' >&2
    # exit 1
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

    ovs-vsctl --may-exist add-br "${OVS_NAME}"
    ip link set "${OVS_NAME}" up
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
    VNC_PORT_INDEX=$(xml "/lab/init/serveur/index_interface")
    
    VM_INDEX=1
    # POSIX Standard
    while [ ${VM_INDEX} -le $((NB_VM)) ]; do
        VM_PATH="/lab/nodes/device[@hypervisor='qemu'][${VM_INDEX}]"

        IMG_DEST="/opt/remotelabz/${LAB_USER}/${LAB_NAME}/${VM_INDEX}/$(xml "${VM_PATH}/@image")"

        mkdir -p /opt/remotelabz/"${LAB_USER}"/"${LAB_NAME}"/${VM_INDEX}

        echo "Creating VM image"
        qemu-img create \
            -f qcow2 \
            -b /opt/remotelabz/images/"$(xml "${VM_PATH}/@image")" \
            "${IMG_DEST}"

        SYS_PARAMS="-m $(xml "${VM_PATH}/system/@memory") -hda ${IMG_DEST}"

        NB_NET_INT=$(xml "count(${VM_PATH}/interface/@type[1])")
        
        VM_IF_INDEX=1
        NET_PARAMS=""
        echo "Adding interfaces"
        while [ ${VM_IF_INDEX} -le $((NB_NET_INT)) ]; do
            NET_IF_NAME=$(xml "${VM_PATH}/interface[${VM_IF_INDEX}]/@type")

            ip tuntap add mode tap "${NET_IF_NAME}"
            ip link set "${NET_IF_NAME}" up
            ovs-vsctl add-port "${OVS_NAME}" "${NET_IF_NAME}"
            
            NET_MAC_ADDR=$(xml "${VM_PATH}/interface[${VM_IF_INDEX}]/@mac_address")
            NET_PARAMS="${NET_PARAMS}-net nic,macaddr=${NET_MAC_ADDR} -net tap,ifname=${NET_IF_NAME},script=no "

            VM_IF_INDEX=$((VM_IF_INDEX+1))
        done

        VNC_ADDR=$(xml "${VM_PATH}/interface_control/@ipv4")
        VNC_PORT=$(xml "${VM_PATH}/interface_control/@port")

        # WebSockify
        nohup /opt/remotelabz/websockify/run "${VNC_ADDR}":$((VNC_PORT+1000)) "${VNC_ADDR}":"${VNC_PORT}" &

        # TODO: add path to proxy
        # script_addpath2proxy += "curl -H \"Authorization: token $CONFIGPROXY_AUTH_TOKEN\" -X POST -d '{\"target\": \"ws://%s:%s\"}' http://localhost:82/api/routes/%s\n"%(vnc_addr,int(vnc_port)+1000,name.replace(" ","_"))

        if [ "$(xml "${VM_PATH}/interface_control/@protocol")" = "vnc" ]; then
            VNC_PORT=$((VNC_PORT-5900))

            ACCESS_PARAMS="-vnc ${VNC_ADDR}:${VNC_PORT}"
            LOCAL_PARAMS="-k fr"
        else
            ACCESS_PARAMS="-vnc ${VNC_ADDR}:$((VNC_PORT_INDEX+VM_INDEX)),websocket=${VNC_PORT}"
            LOCAL_PARAMS=""
        fi

        LOCAL_PARAMS="${LOCAL_PARAMS} -localtime -usbdevice tablet"
        
        # Launch VM
        qemu-system-"$(uname -i)" \
            -machine accel=kvm:tcg \
            -cpu Opteron_G2 \
            -daemonize \
            -name "$(xml "${VM_PATH}/name")" \
            "${SYS_PARAMS}" \
            "${NET_PARAMS}" \
            "${ACCESS_PARAMS}" \
            "${LOCAL_PARAMS}"

        VM_INDEX=$((VM_INDEX+1))
    done
}

#####################
# Main
#####################

main() {
    ovs
    vpn # TODO: Conditional (are we executing on a vpn server?)
    qemu
}

# TODO: Script reboot
# TODO: Script delete

main
exit 0