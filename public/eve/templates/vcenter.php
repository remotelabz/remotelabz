<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/templates/esxi.php
 *
 * esxi template for EVE-NG
 *
 */

$p['type'] = 'qemu';
$p['name'] = 'vCenter';
$p['cpulimit'] = 1;
$p['icon'] = 'ESXi.png';
$p['cpu'] = 2;
$p['ram'] = 10240; 
$p['ethernet'] = 1; 
$p['console'] = 'vnc';
$p['qemu_arch'] = 'x86_64';
$p['qemu_options'] = '-machine pc,accel=kvm -serial none -nographic -nodefconfig -nodefaults -display none -vga std -rtc base=utc -cpu host';
?>
