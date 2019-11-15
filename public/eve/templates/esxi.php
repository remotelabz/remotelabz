<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/templates/esxi.php
 *
 * esxi template for EVE-NG.
 *
 * LICENSE:
 *
 * Copyright (c) 2016, Andrea Dainese
 * Copyright (c) 2017, Alain Degreffe
 * All rights reserved.
 *
 *Redistribution and use in source and binary forms, with or without
 *modification, are permitted provided that the following conditions are met:
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * Neither the name of the UNetLab Ltd nor  the name of EVE-NG Ltd nor the
 *      names of its contributors may be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 **
 * @author Alain Degreffe <eczema@ecze.com>
 * @copyright 2017 Alain Degreffe
 * @license https://gitlab.com/eve-ng-dev/eve-ng-public/raw/DEV/LICENSE
 * @link http://www.eve-ng.net
 * @version 20171010
 */

$p['type'] = 'qemu';
$p['name'] = 'esxi';
$p['cpulimit'] = 1;
$p['icon'] = 'ESXi.png';
$p['cpu'] = 2;
$p['ram'] = 4096; 
$p['ethernet'] = 4; 
$p['console'] = 'vnc';
$p['qemu_arch'] = 'x86_64';
$p['qemu_options'] = '-machine pc,accel=kvm -serial none -nographic -nodefconfig -nodefaults -display none -vga std -rtc base=utc -cpu host';
?>
