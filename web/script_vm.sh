#!/bin/bash 

##########
# Gestion des VM Qemu##########

# VM 1
qemu-img create -b /usr/local/Virtualize/kvm-image/images/Win7-Network.qcow2 -f qcow2 /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-1 
ip tuntap add mode tap tap34 
ip link set tap34 up 
ovs-vsctl add-port OVS_ASA40 tap34
qemu-system-x86_64 -machine accel=kvm:tcg -name ASA-VM1 -daemonize -m 256 -hda /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-1 -net nic,macaddr=00:02:03:04:0:22 -net tap,ifname=tap34,script=no -vnc 194.57.105.124:1,websocket=6665 -k fr -localtime -usbdevice tablet 

# VM 2
qemu-img create -b /usr/local/Virtualize/kvm-image/images/debian-testing20160512.img -f qcow2 /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-2 
ip tuntap add mode tap tap35 
ip link set tap35 up 
ovs-vsctl add-port OVS_ASA40 tap35
qemu-system-x86_64 -machine accel=kvm:tcg -name ASA-VM2 -daemonize -m 256 -hda /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-2 -net nic,macaddr=00:02:03:04:0:23 -net tap,ifname=tap35,script=no -vnc 194.57.105.124:2,websocket=6667 -k fr -localtime -usbdevice tablet 

# VM 3
qemu-img create -b /usr/local/Virtualize/kvm-image/images/Win7-Network.qcow2 -f qcow2 /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-3 
ip tuntap add mode tap tap36 
ip link set tap36 up 
ovs-vsctl add-port OVS_ASA40 tap36
qemu-system-x86_64 -machine accel=kvm:tcg -name ASA-VM3 -daemonize -m 256 -hda /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-3 -net nic,macaddr=00:02:03:04:0:24 -net tap,ifname=tap36,script=no -vnc 194.57.105.124:3,websocket=6669 -k fr -localtime -usbdevice tablet 

# VM 4
qemu-img create -b /usr/local/Virtualize/kvm-image/images/debian-testing20160512.img -f qcow2 /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-4 
ip tuntap add mode tap tap37 
ip link set tap37 up 
ovs-vsctl add-port OVS_ASA40 tap37
qemu-system-x86_64 -machine accel=kvm:tcg -name ASA -daemonize -m 256 -hda /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-4 -net nic,macaddr=00:02:03:04:0:25 -net tap,ifname=tap37,script=no -vnc 194.57.105.124:4,websocket=6673 -k fr -localtime -usbdevice tablet 
