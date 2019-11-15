<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

$p['type'] = 'qemu';
$p['name'] = 'JunipervRR';
$p['cpulimit'] = 1;
$p['icon'] = 'JuniperSRX.png';
$p['cpu'] = 1;
$p['ram'] = 8192;
$p['ethernet'] = 2;
$p['console'] = 'telnet';
$p['qemu_arch'] = 'x86_64';
$p['qemu_version'] = '2.9.0';
$p['qemu_options'] = '-machine type=pc-1.0,accel=kvm -cpu qemu64,+ssse3,+sse4.1,+sse4.2,+x2apic,+aes,pclmulqdq -serial mon:stdio -nographic -nodefconfig -nodefaults -rtc base=utc'; ?>
