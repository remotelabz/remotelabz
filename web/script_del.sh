ovs-vsctl del-port OVS_ASA40 tap37
ip link set tap37 down
ip link delete tap37
delete /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-4
ovs-vsctl del-port OVS_ASA40 tap36
ip link set tap36 down
ip link delete tap36
delete /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-3
ovs-vsctl del-port OVS_ASA40 tap35
ip link set tap35 down
ip link delete tap35
delete /home/fnolot/3VM_1ASA_34/debian-testing20160512.img-2
ovs-vsctl del-port OVS_ASA40 tap34
ip link set tap34 down
ip link delete tap34
delete /home/fnolot/3VM_1ASA_34/Win7-Network.qcow2-1
ovs-vsctl dlink set  OVS_ASA40 down
ovs-vsctl del-br OVS_ASA40
