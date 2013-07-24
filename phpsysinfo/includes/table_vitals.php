<?php
//
// phpSysInfo - A PHP System Information Script
// http://phpsysinfo.sourceforge.net/
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
// $Id: table_vitals.php 4451 2001-06-17 17:56:44Z lkneschke $

$ar_buf = sys_loadavg(); 

for ($i=0;$i<3;$i++) {
    if ($ar_buf[$i] > 2) {
        $load_avg .= '<font color="#ff0000">' . $ar_buf[$i] . '</font>';
    } else {
        $load_avg .= $ar_buf[$i];
    }
    $load_avg .= '&nbsp;&nbsp;';
}

$_text = '<table border="0" width="90%" align="center">'
       . '<tr><td><font size="-1">'. $text['hostname'] .'</font></td><td><font size="-1">' . sys_chostname() . '</font></td></tr>'
       . '<tr><td><font size="-1">'. $text['ip'] .'</font></td><td><font size="-1">' . sys_ip_addr() . '</font></td></tr>'
    
       . '<tr><td><font size="-1">'. $text['kversion'] .'</font></td><td><font size="-1">' . sys_kernel() . '</font></td></tr>'
       . '<tr><td><font size="-1">'. $text['uptime'] .'</font></td><td><font size="-1">' . sys_uptime() . '</font></td></tr>'
       . '<tr><td><font size="-1">'. $text['users'] .'</font></td><td><font size="-1">' . sys_users() . '</font></td></tr>'
       . '<tr><td><font size="-1">'. $text['loadavg'] .'</font></td><td><font size="-1">' . $load_avg . '</font></td></tr>'
       . '</table>';

$tpl->set_var('vitals', makebox($text['vitals'], $_text, '100%'));

?>
