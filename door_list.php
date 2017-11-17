<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modification by Drajat Hasan 2017 (drajathasan20@gmail.com)
 * Some Patch by Navis Kandar 2017 (navkandar@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */


/* Door Management section */

// key to authenticate
if (!defined('INDEX_AUTH')) {
  define('INDEX_AUTH', '1');
}
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
if (!defined('SB')) {
  require '../../../sysconfig.inc.php';
}

// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('locker_loan', 'r');
$can_write = utility::havePrivilege('locker_loan', 'w');

if (!$can_read) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
  $in_pop_up = true;
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    $doorCode = trim(strip_tags($_POST['door']));
    $locker_id = trim(strip_tags($_POST['lockerID']));
    if (empty($doorCode)) {
        utility::jsAlert(__('Door Code can\'t be empty!'));
        exit();
    } else {
        // Door
        $data['locker_id'] = $dbs->escape_string($locker_id);
        $data['door_code'] = $dbs->escape_string($doorCode);
        $data['last_updates'] = date('Y-m-d H:i:s');

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update the data
            $update = $sql_op->update('door', $data, "door_id=".$updateRecordID);
            if ($update) {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'locker loan', $_SESSION['realname'].' update door data ('.$data['door_code'].')');
                utility::jsAlert(__('Item Data Successfully Updated'));
                if ($in_pop_up) {
                    echo '<script type="text/javascript">top.setIframeContent(\'itemIframe\', \''.MWB.'locker_loan/iframe_door_list.php?lockerID='.$data['locker_id'].'\');</script>';
                    echo '<script type="text/javascript">top.jQuery.colorbox.close();</script>';
                } else {
                    echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
                }
                
            } else { utility::jsAlert(__('Door Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_on_loan = array();
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
        // check if the item still on loan
        $loan_q = $dbs->query('SELECT dl.door_code, dl.loan_id, dl.member_id, m.member_name, d.door_id, COUNT( dl.loan_id ) 
                               FROM door_loan AS dl
                               LEFT JOIN member AS m ON m.member_id = dl.member_id
                               LEFT JOIN door AS d ON d.door_code = dl.door_code
                               WHERE d.door_id
                               IN ('.$itemID.') AND dl.is_return=0 GROUP BY d.door_code');
        $loan_d = $loan_q->fetch_row();
        // if there is no loan
        if ($loan_d[5] < 1) {
            if (!$sql_op->delete('door', 'door_id='.$itemID.'')) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'locker', $_SESSION['realname'].' DELETE door data ('.$loan_d[0].')');
            }
        } else {
            $still_on_loan[] = $loan_d[0].' - '.$loan_d[1];
            $error_num++;
        }
    }

    if ($still_on_loan) {
        $items = '';
        foreach ($still_on_loan as $item) {
            $items .= $item."\n";
        }
        utility::jsAlert(__('Item data can not be deleted because still on hold by members')." : \n".$items);
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
        exit();
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('Item succesfully removed!'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    } else {
        utility::jsAlert(__('Item FAILED to removed!'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

if (!$in_pop_up) {
/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner itemIcon">
	<div class="per_title">
    	<h2><?php echo __('Daftar Pintu'); ?></h2>
	</div>
	<div class="sub_section">
	    <form name="search" action="<?php echo MWB; ?>locker_loan/door_list.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
		    <input type="text" name="keywords" id="keywords" size="30" />
		    <select name="searchby"><option value="item">Item</option></select>
		    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="btn btn-default" />
	    </form>
    </div>
</div>
</fieldset>
<?php
/* search form end */
}
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
      die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT d.locker_id,d.door_code,d.rack_number, d.last_updates,l.locker_id,l.locker_name,l.rack_location
        FROM door AS d
        LEFT JOIN locker AS l ON l.locker_id=d.locker_id
        WHERE door_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="btn btn-default"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) {
            $form->record_id = $itemID;
        } else {
            $form->addHidden('updateRecordID', $itemID);
            $form->back_button = false;
        }
        // form record title
        $form->record_title = $rec_d['locker_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-default"';
        // default biblio title and biblio ID
        $l_name = $rec_d['locker_name'];
        $d_code = $rec_d['door_code'];
        $l_locker_id = $rec_d['locker_id'];
    } 

    /* Form Element(s) */
    // title
    if (!$in_pop_up) {
      $str_input = $l_name;
      //$str_input .= '<div class="makeHidden"><a class="notAJAX button btn btn-primary openPopUp" href="'.MWB.'locker_loan/pop_biblio.php?inPopUp=true&action=detail&itemID='.$rec_d['door_id'].'" width="650" height="500" title="'.__('Edit Biblographic data').'">'.__('Edit Biblographic data').'</a></div>';
    } else { $str_input = $l_name; }
    $form->addAnything(__('Nama Rak'), $str_input);
    $form->addHidden('namaRak', $l_name);
    $form->addHidden('doorCode', $d_code);
    $form->addHidden('lockerID', $l_locker_id);
    // item code
    $str_input = simbio_form_element::textField('text', 'door', $rec_d['door_code'], 'onblur="ajaxCheckID(\''.SWB.'admin/AJAX_check_id.php\', \'door\', \'door_code\', \'msgBox\', \'door\')" style="width: 40%;"');
    $str_input .= ' &nbsp; <span id="msgBox">&nbsp;</span>';
    $form->addAnything(__('Door Code'), $str_input);
    // item location
        // get location data related to this record from database
        //$location_q = $dbs->query("SELECT locker_id,rack_location FROM locker");
        //$location_options = array();
        //while ($location_d = $location_q->fetch_row()) {
        //    $location_options[] = array($location_d[0], $location_d[1]);
        //}
    //$form->addSelectList('locationID', __('Location'), $location_options, $rec_d['rack_location']);
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit Item data').': <b>'.$rec_d['locker_name'].'</b> ' //mfc
            .'<br />'.__('Last Updated').'&nbsp;'.$rec_d['last_updates'];
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* ITEM LIST */
    // table spec
    $table_spec = 'door AS d LEFT JOIN locker AS l ON l.locker_id = d.locker_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    $datagrid->setSQLColumn('d.door_id',
        'd.door_code AS \''.__('Kode Pintu').'\'',
        'l.locker_name AS \''.__('Nama Loker').'\'',
        'l.rack_location AS \''.__('Lokasi Rak').'\'',
        'd.last_updates AS \''.__('Last Updated').'\'');
   
    $datagrid->setSQLorder('d.last_updates DESC');
    


    // is there any search
    if (isset($_GET['keywords']) && $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $datagrid->setSQLcriteria('d.door_code =  \''.$keywords.'\'');
    }
    
    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : '.$_GET['keywords'].'<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;
}
/* main content end */
