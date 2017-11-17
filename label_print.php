<?php
/**
 * Copyright (C) 2013  Arie Nugraha (dicarve@yahoo.com)
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

/* Label Locker print */
// Modified by Drajat Hasan fb.com/drajat.hasanm 2017

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB.'admin/default/session.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('locker_loan', 'r');

if (!$can_read) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$max_print = 50;

/* RECORD OPERATION */
if (isset($_POST['doorCode']) AND !empty($_POST['doorCode']) AND isset($_POST['itemAction'])) {
  if (!$can_read) {
    die();
  }
  if (!is_array($_POST['doorCode'])) {
    // make an array
    $_POST['doorCode'] = array((integer)$_POST['doorCode']);
  }
  // loop array
  if (isset($_SESSION['label'])) {
    $print_count = count($_SESSION['label']);
  } else {
    $print_count = 0;
  }
  // barcode size
  $size = 2;
  // create AJAX request
  echo '<script type="text/javascript" src="'.JWB.'jquery.js"></script>';
  echo '<script type="text/javascript">';
  // loop array
  foreach ($_POST['doorCode'] as $doorCode) {
    if ($print_count == $max_print) {
      $limit_reach = true;
      break;
    }
    if (isset($_SESSION['label'][$doorCode])) {
      continue;
    }
    if (!empty($doorCode)) {
      $barcode_text = trim($doorCode);
      /* replace space */
      $barcode_text = str_replace(array(' ', '/', '\/'), '_', $barcode_text);
      /* replace invalid characters */
      $barcode_text = str_replace(array(':', ',', '*', '@'), '', $barcode_text);
      // send ajax request
      echo 'jQuery.ajax({ url: \''.SWB.'lib/phpbarcode/barcode.php?code='.$doorCode.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', type: \'GET\', error: function() { alert(\'Error creating barcode!\'); } });'."\n";
      // add to sessions
      $_SESSION['label'][$doorCode] = $doorCode;
      $print_count++;
    }
  }
  echo 'top.$(\'#queueCount\').html(\''.$print_count.'\')';
  echo '</script>';
  // update print queue count object
  sleep(2);
  if (isset($limit_reach)) {
    $msg = str_replace('{max_print}', $max_print, __('Selected items NOT ADDED to print queue. Only {max_print} can be printed at once'));
    utility::jsAlert($msg);
  } else {
    utility::jsAlert(__('Selected items added to print queue'));
  }
  exit();
}

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
  // update print queue count object
  echo '<script type="text/javascript">top.$(\'#queueCount\').html(\'0\');</script>';
  utility::jsAlert(__('Print queue cleared!'));
  unset($_SESSION['label']);
  exit();
}

// barcode pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
  // check if label session array is available
  if (!isset($_SESSION['label'])) {
    utility::jsAlert(__('There is no data to print!'));
    die();
  }
  if (count($_SESSION['label']) < 1) {
    utility::jsAlert(__('There is no data to print!'));
    die();
  }

  // concat all ID together
  $door_codes = '';
  foreach ($_SESSION['label'] as $id) {
    $door_codes .= '\''.$id.'\',';
  }
  // strip the last comma
  $door_codes = substr_replace($door_codes, '', -1);
  // send query to database
  $door_q = $dbs->query('SELECT d.door_code, l.locker_name 
    FROM locker AS l LEFT JOIN door AS d ON l.locker_id=d.locker_id
    WHERE d.door_code IN('.$door_codes.')');
  $door_data_array = array();
  while ($door_d = $door_q->fetch_row()) {
    if ($door_d[0]) {
      $door_data_array[] = $door_d;
    }
  }

  // include printed settings configuration file
  require SB.'admin'.DS.'admin_template'.DS.'printed_settings.inc.php';
  // check for custom template settings
  $custom_settings = SB.'admin'.DS.$sysconf['admin_template']['dir'].DS.$sysconf['template']['theme'].DS.'printed_settings.inc.php';
  if (file_exists($custom_settings)) {
    include $custom_settings;
  }

  // load print settings from database to override value from printed_settings file
  loadPrintSettings($dbs, 'labellocker');

  // chunk barcode array
  $chunked_barcode_arrays = array_chunk($door_data_array, $sysconf['print']['labellocker']['label_per_row']);
  // create html ouput
  $html_str = '<!DOCTYPE html>'."\n";
  $html_str .= '<html><head><title>Label Locker Print Result</title>'."\n";
  $html_str .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
  $html_str .= '<meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0" /><meta http-equiv="Expires" content="Sat, 26 Jul 1997 05:00:00 GMT" />';
  $html_str .= '<style type="text/css">'."\n";
  $html_str .= 'body { padding: 0; margin: 0; font-family: '.$sysconf['print']['labellocker']['fonts'].'; font-size: '.$sysconf['print']['labellocker']['font_size'].'pt; background: #fff; }'."\n";
  $html_str .= '.box {width: '.$sysconf['print']['labellocker']['label_width'].'cm;line-height: 0;height: '.$sysconf['print']['labellocker']['label_height'].'cm;float: left;background-color:'.$sysconf['print']['labellocker']['label_bg_color'].';color:'.$sysconf['print']['labellocker']['font_color'].'; border-radius: 5px; border-style: solid;border-width: 1px;margin: 2px 5px 2px 2px;}'."\n";;
  $html_str .= '.logo {width:'.$sysconf['print']['labellocker']['img_width'].'cm;height:'.$sysconf['print']['labellocker']['img_height'].'cm;margin-left: auto;margin-right: auto;display: block;margin-top: 10px;}'."\n";;
  $html_str .= 'strong, .barcode {display: block; margin-top: 20px; margin-right: auto; margin-left:auto;}'."\n";
  $html_str .= 'strong,h1 {text-align: center; margin-top: 20px;}'."\n";
  $html_str .= '.barcode {width: 3cm;height: 1.5cm;}'."\n";
  $html_str .= '.doorLabel {margin-top: 55px;}'."\n";
  $html_str .= '.circle { border-style: solid;border-width: 2px;height: 0.5cm;width: 0.5cm;border-radius: 15px;display: block;margin-right: auto;margin-left: auto; margin-top: 10px;}'."\n";
  $html_str .= '</style>'."\n";
  $html_str .= '</head>'."\n";
  $html_str .= '<body>'."\n";
  $html_str .= '<a href="#" onclick="window.print()">Print Again</a>'."\n";
  $html_str .= '<table style="margin: 0; padding: 0;" cellspacing="0" cellpadding="0">'."\n";
  // loop the chunked arrays to row
  foreach ($chunked_barcode_arrays as $barcode_rows) {
    $html_str .= '<tr>'."\n";
    foreach ($barcode_rows as $barcode) {
      $html_str .= '<td valign="top">';
      $html_str .= '<div class="box">';
      $html_str .= '<img class="logo" src="../images/'.$sysconf['print']['labellocker']['logo'].'">';
      $html_str .= '<div class="doorLabel">';
      $html_str .= '<strong>'.$barcode[1].'</strong><h1>'.$barcode[0].'</h1>';
      $html_str .= '</div>';
      $html_str .= '</div>';
      $html_str .= '</td>';
      $html_str .= '<td>';
      $html_str .= '<div class="box">';
      $html_str .= '<div class="circle"></div>';
      $html_str .= '<img class="logo" src="../images/locker.svg">';
      $html_str .= '<strong>'.$barcode[1].'</strong><h1>'.$barcode[0].'</h1>';
      $html_str .= '<img class="barcode" src="../images/barcodes/'.$barcode[0].'.png">';
      $html_str .= '</div>';
      $html_str .= '</td>';
    }
    $html_str .= '<tr>'."\n";
  }
  $html_str .= '</table>'."\n";
  $html_str .= '<script type="text/javascript">self.print();</script>'."\n";
  $html_str .= '</body></html>'."\n";
  // unset the session
  unset($_SESSION['label']);
  // write to file
  $print_file_name = 'door_label_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
  $file_write = @file_put_contents(UPLOAD.$print_file_name, $html_str);
  if ($file_write) {
    // update print queue count object
    echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
    // open result in window
    echo '<script type="text/javascript">top.$.colorbox({href: "'.SWB.FLS.'/'.$print_file_name.'", iframe: true, width: 800, height: 500, title: "'.__('Item Barcodes Printing').'"})</script>';
  } else { utility::jsAlert('ERROR! Item barcodes failed to generate, possibly because '.SB.FLS.' directory is not writable'); }
  exit();
}

?>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
  <div class="per_title">
	  <h2><?php echo __('Item Barcodes Printing'); ?></h2>
  </div>
  <div class="sub_section">
	  <div class="btn-group">
      <a target="blindSubmit" href="<?php echo MWB; ?>locker_loan/label_print.php?action=clear" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-trash"></i>&nbsp;<?php echo __('Clear Print Queue'); ?></a>
      <a target="blindSubmit" href="<?php echo MWB; ?>locker_loan/label_print.php?action=print" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-print"></i>&nbsp;<?php echo __('Print Label for Selected Data');?></a>
	    <a href="<?php echo MWB; ?>bibliography/pop_print_settings.php?type=labellocker" class="notAJAX btn btn-default openPopUp" title="<?php echo __('Change print label settings'); ?>"><i class="glyphicon glyphicon-wrench"></i></a>
	  </div>
    <form name="search" action="<?php echo MWB; ?>locker_loan/label_print.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="btn btn-default" />
    </form>
  </div>
  <div class="infoBox">
  <?php
  echo __('Maximum').' <font style="color: #f00">'.$max_print.'</font> '.__('records can be printed at once. Currently there is').' ';
  if (isset($_SESSION['label'])) {
    echo '<font id="queueCount" style="color: #f00">'.count($_SESSION['label']).'</font>';
  } else { echo '<font id="queueCount" style="color: #f00">0</font>'; }
  echo ' '.__('in queue waiting to be printed.');
  ?>
  </div>
</div>
</fieldset>
<?php
/* search form end */

// create datagrid
$datagrid = new simbio_datagrid();
/* ITEM LIST */
require SIMBIO.'simbio_UTILS/simbio_tokenizecql.inc.php';
require LIB.'biblio_list_model.inc.php';
// index choice

  // table spec
  $table_spec = 'locker AS l LEFT JOIN door AS d ON l.locker_id=d.locker_id';
  $datagrid->setSQLColumn('d.door_code',
    'd.door_code AS \''.__('Kode Pintu').'\'',
    'l.locker_name AS \''.__('Nama Loker').'\'');
  $datagrid->setSQLorder('d.last_updates DESC'); 
// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
  $keywords = $dbs->escape_string(trim($_GET['keywords']));
  $datagrid->setSQLcriteria('d.door_code LIKE "%'.$keywords.'%" OR l.locker_name LIKE "%'.$keywords.'%"'); 
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('doorCode', __('Add'));
$datagrid->chbox_action_button = __('Add To Print Queue');
$datagrid->chbox_confirm_msg = __('Add to print queue?');
$datagrid->column_width = array('10%', '85%');
// set checkbox action URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
  $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords'));
  echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>';
}
echo $datagrid_result;
/* main content end */
