<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id: Delete.php 27445 2009-07-15 19:31:25Z ralfbecker $
 */
include_once 'Horde/SyncML/Command/Sync/SyncElement.php';

class Horde_SyncML_Command_Sync_Delete extends Horde_SyncML_Command_Sync_SyncElement {

    function output($currentCmdID, &$output)
    {
        $status = new Horde_SyncML_Command_Status($this->_status, 'Delete');
        $status->setCmdRef($this->_cmdID);

        if (!empty($this->_items)) {
            $status->setSyncItems($this->_items);
        }

        return $status->output($currentCmdID, $output);
    }

}
