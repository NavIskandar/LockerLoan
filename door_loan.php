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

/* loan list iframe content */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}


// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// privileges checking
$can_read = utility::havePrivilege('locker_loan', 'r');
$can_write = utility::havePrivilege('locker_loan', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

if (!isset($_SESSION['door_memberID'])) { die(); }

require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_UTILS/simbio_date.inc.php';

// page title
$page_title = 'Member Loan List';
ob_start();
$mem_session = $_SESSION['door_memberID'];
// Check loan status
$stat_q = $dbs->query("SELECT member_id FROM door_loan WHERE member_id='".$mem_session."' AND is_return=0");
if ($stat_q->num_rows > 0){
    // Message
    echo "<div><h2 style='background-color: #d9534f; color: white; padding-top: 20px; padding-bottom: 20px; text-align:center' >Anda masih memiliki peminjaman yang belum dikembalikan.</h2></di>";
} else {
?>
    <!--item loan form-->
    <div class="loanItemCodeInput">
        <form name="itemLoan" id="loanForm" action="door_circulation_action.php" method="post" style="display: inline;">
            <?php echo "Masukkan Kode Pintu/Barkod"; ?> :
            <input type="text" id="tempLoanID" name="tempLoanID" />
            <input type="submit" value="<?php echo __('Loan'); ?>" class="btn btn-warning button" />
        </form>
    </div>
    <script type="text/javascript">$('#tempLoanID').focus();</script>
    <!--item loan form end-->
<?php
}
// get the buffered content
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
