<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modification by Drajat Hasan 2017 (drajathasan20@gmail.com)
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


/* Biblio Item List */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SB.'admin/default/session.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');

// page title
$page_title = 'Item List';
// get id from url
$lockerID = 0;
if (isset($_GET['lockerID']) AND !empty($_GET['lockerID'])) {
  $lockerID = (integer)$_GET['lockerID'];
}

// start the output buffer
ob_start();
?>
<script type="text/javascript">
function confirmProcess(int_locker_id, int_door_id)
{
  var confirmBox = confirm('Are you sure to remove selected item?' + "\n" + 'Once deleted, it can\'t be restored!');
  if (confirmBox) {
    // set hidden element value
    document.hiddenActionForm.bid.value = int_locker_id;
    document.hiddenActionForm.remove.value = int_door_id;
    // submit form
    document.hiddenActionForm.submit();
  }
}
</script>
<?php
/* main content */
if (isset($_POST['remove'])) {
  $id = (integer)$_POST['remove'];
  $bid = (integer)$_POST['bid'];
  $sql_op = new simbio_dbop($dbs);
  // check if the item still on loan
  $loan_q = $dbs->query('SELECT DISTINCT dl.door_code, d.door_code FROM door_loan AS dl
    LEFT JOIN door AS d ON d.door_code=dl.door_code
    WHERE d.door_id='.$id.' AND dl.is_lent=1 AND dl.is_return=0');
  $loan_d = $loan_q->fetch_row();
  // send an alert if the member cant be deleted
  if ($loan_q->num_rows > 0) {
    echo '<script type="text/javascript">';
    echo 'alert(\''.__('Item data can not be deleted because still on hold by members').'\');';
    echo 'self.location.href = \'iframe_door_list.php?lockerID='.$bid.'\';';
    echo '</script>';
  } else {
    if ($sql_op->delete('door', 'door_id='.$id)) {
      echo '<script type="text/javascript">';
      echo 'alert(\''.__('Item succesfully removed!').'\');';
      echo 'self.location.href = \'iframe_door_list.php?lockerID='.$bid.'\';';
      echo '</script>';
    } else {
      echo '<script type="text/javascript">';
      echo 'alert(\''.__('Item FAILED to removed!').'\');';
      echo 'self.location.href = \'iframe_door_list.php?lockerID='.$bid.'\';';
      echo '</script>';
    }
  }
}

// if biblio ID is set
if ($lockerID) {
  $table = new simbio_table();
  $table->table_attr = 'align="center" class="detailTable" style="width: 100%;" cellpadding="2" cellspacing="0"';

  // database list
  $door_q = $dbs->query('SELECT l.locker_id, l.locker_name,l.rack_location, d.door_id, d.locker_id, d.door_code
                        FROM locker AS l
                        LEFT JOIN door AS d ON d.locker_id = l.locker_id
                        WHERE l.locker_id='.$lockerID);
  $row = 1;
  while ($door_d = $door_q->fetch_assoc()) {
    // alternate the row color
    $row_class = ($row%2 == 0)?'alterCell':'alterCell2';

    // links
    $edit_link = '<a class="notAJAX btn btn-default button openPopUp" href="'.MWB.'locker_loan/pop_item.php?inPopUp=true&action=detail&biblioID='.$lockerID.'&itemID='.$door_d['door_id'].'" width="650" height="400" title="'.__('Items/Copies').'" style="text-decoration: underline;">Edit</a>';
    $remove_link = '<a href="#" class="notAJAX btn button btn-danger btn-delete" onclick="javascript: confirmProcess('.$lockerID.', '.$door_d['door_id'].')">Delete</a>';
    $title = $door_d['door_code'];

    $table->appendTableRow(array($edit_link, $remove_link, $title, $door_d['rack_location']));
    $table->setCellAttr($row, null, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: auto;"');
    $table->setCellAttr($row, 0, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 5%;"');
    $table->setCellAttr($row, 1, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 10%;"');
    $table->setCellAttr($row, 2, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 40%;"');

    $row++;
  }
  echo $table->printTable();
  // hidden form
  echo '<form name="hiddenActionForm" method="post" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="bid" value="0" /><input type="hidden" name="remove" value="0" /></form>';
}
/* main content end */
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
