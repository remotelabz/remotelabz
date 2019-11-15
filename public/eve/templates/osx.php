<?php 
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler 
  
/** 
 * html/templates/osx.php 
 * 
 */ 

$p['type'] = 'qemu'; 
$p['name'] = 'Mac'; 
$p['cpulimit'] = 1;
$p['icon'] = 'MAC_PC.png'; 
$p['cpu'] = 4; 
$p['ram'] = 3072; 
$p['ethernet'] = 1; 
$p['console'] = 'vnc'; 
$p['qemu_arch'] = 'x86_64'; 
$p['qemu_nic'] = 'e1000-82545em';
$p['qemu_options'] = '--machine pc-q35-2.4,accel=kvm -cpu Penryn,vendor=GenuineIntel -smp 4,cores=2  -usb -device usb-kbd -usbdevice tablet  -smbios type=2 -nodefaults -vga std';
?> 
