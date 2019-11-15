<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/templates/alteon.php
 *
 */

$p['type'] = 'qemu'; 
$p['name'] = 'Alteon';
$p['cpulimit'] = 1;
$p['icon'] = 'Load Balancer.png';
$p['cpu'] = 2; 
$p['ram'] = 6144;
$p['ethernet'] = 4;
$p['console'] = 'telnet'; 
$p['qemu_arch'] = 'x86_64';
$p['qemu_options'] = '-machine type=pc-1.0,accel=kvm -serial mon:stdio -nographic -nodefconfig -nodefaults -display none -vga std -rtc base=utc ';
?>
