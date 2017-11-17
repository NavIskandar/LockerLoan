<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modification by Drajat Hasan 2017 (drajathasan20@gmail.com)
 * Some Patch by Navis Kandar 2017 (navkandar@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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

/* Checkout door list */

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

// privileges checking
$can_read = utility::havePrivilege('locker_loan', 'r');
$can_write = utility::havePrivilege('locker_loan', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}
/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner itemOutIcon">
	<div class="per_title">
    	<h2><?php echo __('Daftar Kunci Keluar'); ?></h2>
    </div>
    <div class="sub_section">
    <form name="search" action="<?php echo MWB; ?>locker_loan/checkout_door.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="btn btn-default" />
    </form>
    </div>
</div>
</fieldset>
<?php
/* ITEM LIST */
// table spec
$table_spec = 'door_loan AS dl
    LEFT JOIN member AS m ON dl.member_id=m.member_id
    LEFT JOIN door AS d ON dl.door_code=d.door_code';

// create datagrid
$datagrid = new simbio_datagrid();
$datagrid->setSQLColumn("dl.door_code AS '".__('Kode Pintu')."'",
    "m.member_id AS '".__('Member ID')."'",
    "m.member_name AS '".__('Member Name')."'",
    "dl.loan_date AS '".__('Loan Date')."'");
$datagrid->setSQLorder("dl.loan_date DESC");

// change the record order
if (isset($_GET['fld']) AND isset($_GET['dir'])) {
    $datagrid->setSQLorder("'".urldecode($_GET['fld'])."' ".$dbs->escape_string($_GET['dir']));
}

$checkout_criteria = ' (dl.is_lent=1 AND dl.is_return=0) ';

// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $keyword = $dbs->escape_string(trim($_GET['keywords']));
    $words = explode(' ', $keyword);
    if (count($words) > 1) {
        $concat_sql = ' (';
        foreach ($words as $word) {
            $concat_sql .= " (m.member_name LIKE '%$word%' OR dl.door_code LIKE '%$word%') AND";
        }
        // remove the last AND
        $concat_sql = substr_replace($concat_sql, '', -3);
        $concat_sql .= ') ';
        $datagrid->setSQLCriteria($checkout_criteria.' AND '.$concat_sql);
    } else {
        $datagrid->setSQLCriteria($checkout_criteria." AND (m.member_name LIKE '%$keyword%' OR dl.door_code LIKE '%$keyword%')");
    }
} else {
    $datagrid->setSQLCriteria($checkout_criteria);
}

// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';

// set column width
$datagrid->column_width = array(0 => '12%', 1 => '12%', 2 => '50%');

// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, false);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    echo '<div class="infoBox">';
    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
    echo $msg.' : "'.$_GET['keywords'].'"</div>';
}

echo $datagrid_result;
/* main content end */
