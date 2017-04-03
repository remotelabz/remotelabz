#!/bin/bash 

##########
# Gestion des VM Qemu##########

# VM 1
qemu-img create -b /usr/local/Virtualize/kvm-image/images/debian-testing20160512.img -f qcow2 /home/fnolot/Ping2VM_68/debian-testing20160512.img-1 
ip tuntap add mode tap tap68 
ip link set tap68 up 
ovs-vsctl add-port OVS170 tap68
qemu-system-x86_64 -machine accel=kvm:tcg -name VM_1 -daemonize -m 256 -hda /home/fnolot/Ping2VM_68/debian-testing20160512.img-1 -net nic,macaddr=00:02:03:04:0:44 -net tap,ifname=tap68,script=no -vnc 194.57.105.124:1,websocket=6683 -k fr -localtime -usbdevice tablet 

# VM 2
qemu-img create -b /usr/local/Virtualize/kvm-image/images/debian-testing20160512.img -f qcow2 /home/fnolot/Ping2VM_68/debian-testing20160512.img-2 
ip tuntap add mode tap tap69 
ip link set tap69 up 
ovs-vsctl add-port OVS170 tap69
qemu-system-x86_64 -machine accel=kvm:tcg -name VM_2 -daemonize -m 256 -hda /home/fnolot/Ping2VM_68/debian-testing20160512.img-2 -net nic,macaddr=00:02:03:04:0:45 -net tap,ifname=tap69,script=no -vnc 194.57.105.124:2,websocket=6685 -k fr -localtime -usbdevice tablet 
