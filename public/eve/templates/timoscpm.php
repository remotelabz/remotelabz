<?php
# vim: syntax=php tabstop=4 softtabstop=0 noexpandtab laststatus=1 ruler

/**
 * html/templates/timos.php
 *
 * timos template for UNetLab.
 * You should have received a copy of the GNU General Public License
 * along with UNetLab.If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Andrea Dainese <andrea.dainese@gmail.com>
 * @copyright 2014-2016 Andrea Dainese
 * @license BSD-3-Clause https://github.com/dainok/unetlab/blob/master/LICENSE
 * @link http://www.unetlab.com/
 * @version 20160719
 */

$p['type'] = 'qemu';
$p['name'] = '7750SR_CPM';
$p['cpulimit'] = 1;
$p['icon'] = 'SROS.png';
$p['cpu'] = 1;
$p['ram'] = 2048; 
$p['ethernet'] = 2; 
$p['console'] = 'telnet'; 
$p['qemu_arch'] = 'x86_64';
if (function_exists('isVirtual') && isVirtual()) {
	$p['qemu_options'] = '-machine type=pc,accel=tcg';
} else {
	$p['qemu_options'] = '-machine type=pc,accel=kvm';
}
$p['qemu_options'] .= ' -serial mon:stdio -nographic -nodefconfig -nodefaults -rtc base=utc';
$p['management_address'] = '1.1.1.1/24'; 
$p['timos_line'] = 'slot=A chassis=SR-12 card=cpm5'; 
$p['timos_license'] = "ftp://sim_license:sim_license@172.22.108.10/sros_vSIM_R14_license_cis.txt"; 
//$p['timos_config'] = "ftp://ftp_timos:ftp_timos@172.22.108.10:/172.22.113.1";
?>
