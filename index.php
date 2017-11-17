<?php
/**
 * Copyright (C) 2007,2008,2009,2010  Arie Nugraha (dicarve@yahoo.com)
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

/* Locker Loan */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

if (!defined('SB')) {
  // main system configuration
  require '../../../sysconfig.inc.php';
  // start the session
  require SB.'admin/default/session.inc.php';
}
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');

require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO.'simbio_FILE/simbio_file_upload.inc.php';
require MDLBS.'system/biblio_indexer.inc.php';
require MDLBS.'locker_loan/long_biblio.inc.php';

// privileges checking
$can_read = utility::havePrivilege('locker_loan', 'r');
$can_write = utility::havePrivilege('locker_loan', 'w');

// Default Pattern
$door_pattern = 'LK0000';

$in_pop_up = false;
// Save
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
  $title = trim(strip_tags($_POST['title']));
  $rack_number = trim(strip_tags($_POST['rack_number']));
  $rack_location = trim(strip_tags($_POST['rack_location']));
  // check form validity
  if (empty($title)) {
    utility::jsAlert(__('Title can not be empty'));
    exit();
  } else {
    $indexer = new biblio_indexer($dbs);
    // create biblio_indexer class instance
    $data['locker_name'] = $dbs->escape_string($title);
    $data['rack_location'] = $dbs->escape_string($rack_location);
    $data['input_date'] = date('Y-m-d H:i:s');
    $data['last_update'] = date('Y-m-d H:i:s');
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    if (isset($_POST['updateRecordID'])) {
      /* UPDATE RECORD MODE */
      unset($data['input_date']);
      // filter update record ID
      $updateRecordID = (integer)$_POST['updateRecordID'];
      // update data
      $update = $sql_op->update('locker', $data, 'locker_id='.$updateRecordID);
      // send an alert
      if ($update) {
        if ($sysconf['bibliography_update_notification']) {
          utility::jsAlert(__('Locker Data Successfully Updated'));
        }        
        // write log
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'locker', $_SESSION['realname'].' update locker data ('.$data['locker_name'].') with locker_id ('.$_POST['itemID'].')');
        // close window OR redirect main page
        if ($in_pop_up) {
          $itemCollID = (integer)$_POST['itemCollID'];
          echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url, {method: \'post\', addData: \''.( $itemCollID?'itemID='.$itemCollID.'&detail=true':'' ).'\'});</script>';
          echo '<script type="text/javascript">top.closeHTMLpop();</script>';
        } else {
          echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
        }
      } else { 
        utility::jsAlert(__('Bibliography Data FAILED to Updated. Please Contact System Administrator')."\n".$sql_op->error); 
      }
    } else {
      /* INSERT RECORD MODE */
      // insert the data
      $insert = $sql_op->insert('locker', $data);
      if ($insert) {
        $last_locker_id = $sql_op->insert_id;
        utility::jsAlert(__('New Locker Data Successfully Saved'));
        $indexer->makeIndex($last_locker_id);
        // write log
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' insert locker data ('.$data['title'].') with biblio_id ('.$last_biblio_id.')');
      } else { utility::jsAlert(__('Bibliography Data FAILED to Save. Please Contact System Administrator')."\n".$sql_op->error); }
    }

    // item batch insert
    /* Simple Item Batch by Navis */
    if (trim($_POST['itemCodePattern']) != '' && $_POST['totalItems'] > 0 ) {
      // Set basic var
      $pattObj = new longBiblioAtt();
      $patt_prefix = trim($dbs->escape_string($_POST['itemCodePattern']));
      $total = (integer)$_POST['totalItems'];
      // Set pattern and lastnumber
      $pattern = $pattObj->getPattern($patt_prefix, $dbs);
      $getlastnumber = $pattObj->getLastNumber($dbs, $patt_prefix);
      // Set start and end
      $end = $getlastnumber+$total;
      $start = $getlastnumber;
      for ($x = $start; $x < $end;$x++) {
        $itemcode = $pattern['prefix'].sprintf("%0".$pattern['zero']."d", $x).$pattern['suffix'];
        $item_insert_sql = sprintf("INSERT IGNORE INTO door (locker_id, door_code, rack_number)
        VALUES (%d, '%s', '%s')", $updateRecordID?$updateRecordID:$last_locker_id, $itemcode, $data['rack_number']);
        @$dbs->query($item_insert_sql);
      }
    }
    /* End Modification */

    echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'locker_loan/index.php\', {method: \'post\', addData: \'itemID='.$last_locker_id.'&detail=true\'});</script>';
    exit();
  }
  exit();
} elseif (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
  if (!($can_read AND $can_write)) {
    die();
  }
  /* DATA DELETION PROCESS */
  // create sql op object
  $sql_op = new simbio_dbop($dbs);
  $failed_array = array();
  $error_num = 0;
  $still_have_item = array();
  if (!is_array($_POST['itemID'])) {
    // make an array
    $_POST['itemID'] = array((integer)$_POST['itemID']);
  }
  // loop array
  foreach ($_POST['itemID'] as $itemID) {
    $itemID = (integer)$itemID;
    // check if this biblio data still have an item
    $_sql_locker_item_q = sprintf('SELECT l.locker_name, COUNT(door_id) FROM locker AS l
      LEFT JOIN door AS d ON d.locker_id=l.locker_id
      WHERE l.locker_id=%d GROUP BY locker_name', $itemID);
    $locker_item_q = $dbs->query($_sql_locker_item_q);
    $locker_item_d = $locker_item_q->fetch_row();
    if ($locker_item_d[1] < 1) {
      if (!$sql_op->delete('locker', "locker_id=$itemID")) {
        $error_num++;
      } else {
        // write log
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'locker', $_SESSION['realname'].' DELETE locker data ('.$biblio_item_d[0].') with locker_id ('.$itemID.')');
        // delete related data
        $sql_op->delete('locker', "locker_id=$itemID");
        utility::jsAlert(__('Data berhasil dihapus'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\', {addData: \''.$_POST['lastQueryStr'].'\'});</script>';
      }
    } else {
      $still_have_item[] = substr($locker_item_d[0], 0, 45).'... still have '.$locker_item_d[1].' copies';
      $error_num++;
    }
  }

  if ($still_have_item) {
    $titles = '';
    foreach ($still_have_item as $title) {
      $titles .= $title." ";
    }
    utility::jsAlert(__('Below data can not be deleted:')." ".$titles);
    echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\', {addData: \''.$_POST['lastQueryStr'].'\'});</script>';
    exit();
  }
}
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
  $in_pop_up = true;
}
?>
<fieldset class="menuBox">
<div class="menuBoxInner memberIcon">
	<div class="per_title">
    	<h2><?php echo __('Lemari Locker'); ?></h2>
    </div>
    <div class="sub_section">
	<div class="btn-group">
    <a href="<?php echo MWB; ?>locker_loan/index.php" class="btn btn-default"><i class="glyphicon glyphicon-list-alt"></i>&nbsp;<?php echo __('Daftar Locker'); ?></a>
    <a href="<?php echo MWB; ?>locker_loan/index.php?action=detail" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i>&nbsp;<?php echo __('Tambah Locker'); ?></a>
	</div>
    <form name="search" action="<?php echo MWB; ?>locker_loan/index.php" id="search" method="get" style="display: inline;"><?php echo __('Pencarian Locker'); ?> :
	    <input type="text" name="keywords" size="30" />
	    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
	</form>
	</div>
</div>
</fieldset>
<?php
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
  if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
  }
  /* RECORD FORM */
  // try query
  $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
  $_sql_rec_q = sprintf('SELECT l.*,d.* FROM locker AS l
                         LEFT JOIN door AS d ON d.locker_id=l.locker_id 
                         WHERE l.locker_id=%d', $itemID);
  $rec_q = $dbs->query($_sql_rec_q);
  $rec_d = $rec_q->fetch_assoc();

  // create new instance
  $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
  $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="btn btn-default"';
  // form table attributes
  $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
  $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
  $form->table_content_attr = 'class="alterCell2"';

  $visibility = 'makeVisible';
  // edit mode flag set
  if ($rec_q->num_rows > 0) {
    $form->edit_mode = true;
    // record ID for delete process
    if (!$in_pop_up) {
      // form record id
      $form->record_id = $itemID;
    } else {
      $form->addHidden('updateRecordID', $itemID);
      $form->addHidden('itemCollID', $_POST['itemCollID']);
      $form->back_button = false;
    }
    // form record title
    $form->record_title = $rec_d['locker_name'];
    // submit button attribute
    $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-default"';
    // element visibility class toogle
    $visibility = 'makeHidden';
  } // end of edit mode

  /* Form Element(s) */
  // biblio title
  $form->addTextField('textarea', 'title', __('Nama Locker').'*', $rec_d['locker_name'], 'rows="1" style="width: 100%; overflow: auto;"', __('Penamaan loker digunakan untuk membedakan antara 1 rak loker dengan yang lain.'));
  $form->addTextField('text', 'rack_location', __('Lokasi Rak'), $rec_d['rack_location'], 'style="width: 20%;"', __('Lokasi dimana loker ditempatkan.'));
  /* Modified By Navis */
  // Bib Att
  $BibAtt = new longBiblioAtt();
  // Set Batch Code List
  $setList = $BibAtt->setList($dbs);
  $str_input  = '<div class="btn-group" style="float:left !important;">';
  $str_input .= '<a style="margin-right:0px" class="notAJAX btn btn-primary openPopUp notIframe" href="'.MWB.'locker_loan/pop_pattern.php" height="420px" title="'.__('Add new pattern').'">
                  <i class="glyphicon glyphicon-plus"></i> Add New Pattern</a>';
  $str_input .= '<a href="'.MWB.'locker_loan/item_code_pattern.php" class="notAjax btn btn-default openPopUp" title="'.__('Item code pattern manager.').'"><i class="glyphicon glyphicon-wrench"></i></a>';
  $str_input .= '</div>&nbsp;';
  $str_input .= simbio_form_element::selectList('itemCodePattern', $setList, '', 'style="width: auto"').' &nbsp;';
  $str_input .= '<label id="totalItemsLabel">' . __('Total item(s)').':</label> <input type="text" class="small_input" style="width: 100px;" name="totalItems" value="0" /> &nbsp;';
  $form->addAnything(__('Item(s) code batch generator'), $str_input);
  // biblio item add
  if (!$in_pop_up AND $form->edit_mode) {
    //$stre_input = '<div class="makeHidden"><a class="notAJAX button btn btn-info openPopUp" href="'.MWB.'locker_loan/pop_item.php?inPopUp=true&action=detail&itemID='.$rec_d['locker_id'].'" title="'.__('Items/Copies').'" height="500">'.__('Add New Items').'</a></div>';
    $stre_input = '<iframe name="itemIframe" id="itemIframe" class="borderAll" style="width: 100%; height: 70px;" src="'.MWB.'locker_loan/iframe_door_list.php?lockerID='.$rec_d['locker_id'].'&block=1"></iframe>'."\n";
    $form->addAnything(__('Door(s) Data'), $stre_input);
  }

  // edit mode messagge
  if ($form->edit_mode) {
  echo '<div class="infoBox">'
    .'<div style="float: left; width: 80%;">'.__('You are going to edit locker data').' : <b>'.$rec_d['locker_name'].'</b>  <br />'.__('Last Updated').'&nbsp;'. $rec_d['last_update'].'</div>'; //mfc
  echo '</div>'."\n";
  }
  // print out the form object
  echo $form->printOut();
  // javascript
  ?>
  <script type="text/javascript">
  $(document).ready(function() {
    $('#class').change(function() {
      $('#callNumber').val($(this).val().replace('NEW:',''));
    });
  });
  </script>
  <?php
/* SaveData */
} else {
/* LOCKER LIST */
// Table spec
$table_spec = 'locker AS l LEFT JOIN door AS d ON d.locker_id=l.locker_id';
$datagrid = new simbio_datagrid();
// Create Datagrid
if ($can_read AND $can_write) {
    $datagrid->setSQLColumn('l.locker_id',
    	                    'l.locker_name AS \''.__('Nama Locker').'\'',
                          'l.rack_location AS \''.__('Lokasi Rak').'\'',
                          'IF(COUNT(d.door_id)>0, COUNT(d.door_id), \'<strong style="color: #f00;">'.__('None').'</strong>\') AS \''.__('Jumlah Pintu').'\'',
                          'l.last_update AS \''.__('Last Updated').'\'');
} else {
	$datagrid->setSQLColumn('locker_id',
    	                    'locker_name AS \''.__('Member Name').'\'',
    	                    'last_update AS \''.__('Last Updated').'\'');
}
$datagrid->setSQLorder('last_update DESC');
// set group by
$datagrid->sql_group_by = 'l.locker_id';
// Keyword
if (isset($_GET['keywords']) AND $_GET['keywords']) {
  $keyword = $dbs->escape_string(trim($_GET['keywords']));
  $criteria = "l.locker_name LIKE '%$keyword%'";
  $datagrid->setSQLCriteria($criteria);
}
// set table and table header attributes
$datagrid->icon_edit = SWB.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
$datagrid->table_name = 'lockerList';
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
?>
