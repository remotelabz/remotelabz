ovs-vsctl del-port OVS170 tap69
ip link set tap69 down
ip link delete tap69
delete /home/fnolot/Ping2VM_68/debian-testing20160512.img-2
ovs-vsctl del-port OVS170 tap68
ip link set tap68 down
ip link delete tap68
delete /home/fnolot/Ping2VM_68/debian-testing20160512.img-1
ovs-vsctl dlink set  OVS170 down
ovs-vsctl del-br OVS170
