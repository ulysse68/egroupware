<?php
/**
 * eGroupWare - Filemanager - user interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: index.php 25898 2008-08-14 13:53:17Z ralfbecker $
 */

header('Location: ../index.php?menuaction=filemanager.filemanager_ui.index'.
	(isset($_GET['sessionid']) ? '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'] : ''));
