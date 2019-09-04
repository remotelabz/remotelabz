<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/templates/cucm.php
 *
 */

$p['type'] = 'qemu';
$p['name'] = 'CUCM';
$p['cpulimit'] = 1;
$p['icon'] = 'CUCM.png';
$p['cpu'] = 2;
$p['ram'] = 4096;
$p['ethernet'] = 1;
$p['console'] = 'vnc';
$p['qemu_arch'] = 'x86_64';
$p['qemu_nic'] = 'virtio-net-pci';
$p['qemu_options'] = '-machine type=pc-1.0,accel=kvm -vga std -usbdevice tablet -boot order=cd';
