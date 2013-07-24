<?php
/**
 * EGroupware Home
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: setup.inc.php 31896 2010-09-05 16:26:30Z ralfbecker $
 */

/* Basic information about this app */
$setup_info['home']['name']      = 'home';
$setup_info['home']['title']     = 'Home';
$setup_info['home']['version']   = '1.8';
$setup_info['home']['app_order'] = 1;
$setup_info['home']['enable']    = 1;

$setup_info['home']['author'] = 'eGroupWare Core Team';
$setup_info['home']['license']  = 'GPL';
$setup_info['home']['description'] = 'Displays eGroupWare\' homepage';
$setup_info['home']['maintainer'] = array(
	'name' => 'eGroupWare Developers',
	'email' => 'egroupware-developers@lists.sourceforge.net'
);

/* The hooks this app includes, needed for hooks registration */
$setup_info['home']['hooks']['hasUpdates'] = 'home.updates.hasUpdates';
$setup_info['home']['hooks']['showUpdates'] = 'home.updates.showUpdates';
	
/* Dependencies for this app to work */
$setup_info['home']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
