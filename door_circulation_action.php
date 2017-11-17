<?php
/**
 * Copyright (C) 2009  Arie Nugraha (dicarve@yahoo.com)
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

/* circulation transaction process */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}
// key to get full database access
@define('DB_ACCESS', 'fa');

if (!defined('DIRECT_INCLUDE')) {
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
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO.'simbio_UTILS/simbio_date.inc.php';

// transaction is finished
if (isset($_POST['finish'])) {
    // create circulation object
    $memberID = $_SESSION['door_memberID'];
    // finish loan transaction
    unset($_SESSION['door_memberID']);
        // write log
        utility::writeLogs($dbs, 'member', $memberID, 'circulation', $_SESSION['realname'].' finish door circulation transaction with member ('.$memberID.')');
        // send message
        echo '<script type="text/javascript">';
        if ($sysconf['transaction_finished_notification']) {
            echo 'alert(\''.__('Transaction finished').'\');';
        }
        echo 'parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'locker_loan/door_circulation.php\', {method: \'post\', addData: \'finishID='.$memberID.'\'});';
        echo '</script>';
    exit();
}

if (isset($_POST['tempLoanID'])) {
    // Modified by Drajat Hasan,
    $doorCode = $dbs->escape_string($_POST['tempLoanID']);
    // Empty or Not
    if (empty($doorCode)) {
       utility::jsAlert("Kode tidak boleh kosong!");
       echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
       exit();
    }
    // Check availibility of the door
    $chk_door = $dbs->query('SELECT door_code FROM door WHERE door_code="'.$doorCode.'"');
    // Check loan status
    $door_q = $dbs->query('SELECT dl.door_code,dl.member_id, dl.is_return,m.member_id,m.member_name FROM door_loan
                          AS dl LEFT JOIN member AS m ON m.member_id=dl.member_id 
                          WHERE dl.door_code="'.$doorCode.'" AND dl.is_return=0 ');
    if ($chk_door->num_rows == 0) {
       utility::jsAlert("Pintu dengan kode ".$doorCode." tidak ditemukan dalam pangkalan data.");
       echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
       exit();
    } else {
        if ($door_d = $door_q->fetch_assoc()){
            utility::jsAlert("Pintu dengan kode ".$door_d['door_code']." sedang dipinjam oleh ".$door_d['member_name']."");
            echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
            exit();
        } else {
            // Insert data
            $dateLoan = date('Y-m-d');
            $mem_id = $_SESSION['door_memberID'];
            $dbs->query("INSERT INTO door_loan(door_code,member_id,loan_date,is_lent) VALUES('$doorCode','$mem_id','$dateLoan','1')");
            // Sucess Message
            // unset session
            unset($_SESSION['door_memberID']);
            // write log
            utility::writeLogs($dbs, 'member', $memberID, 'circulation', $_SESSION['realname'].' finish door circulation transaction with member ('.$mem_id.')');
            // send message
            echo '<script type="text/javascript">';
            if ($sysconf['transaction_finished_notification']) {
                echo 'alert(\''.__('Transaction finished').'\');';
            }
            echo 'parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'locker_loan/door_circulation.php\', {method: \'post\', addData: \'finishID='.$mem_id.'\'});';
            echo '</script>';
        exit();
        }
    }
    // End Modified
}

// quick return proccess
if (isset($_POST['quickReturnID']) AND $_POST['quickReturnID']) {
    // get loan data
    $loan_info_q = $dbs->query("SELECT dl.*,m.member_id,m.member_name FROM door_loan AS dl
        LEFT JOIN door AS d ON d.door_code=dl.door_code
        LEFT JOIN member AS m ON dl.member_id=m.member_id
        WHERE dl.door_code='".$dbs->escape_string($_POST['quickReturnID'])."' AND is_lent=1 AND is_return=0");
    if ($loan_info_q->num_rows < 1) {
        echo '<div class="errorBox">'.__('Kunci Loker ini sudah dikembalikan atau tidak terdaftar pada pangkalan data peminjaman').'</div>';
    } else {
        $return_date = date('Y-m-d');
        // get data
        $loan_d = $loan_info_q->fetch_assoc();
        // write log
        utility::writeLogs($dbs, 'member', $loan_d['member_id'], 'circulation', $_SESSION['realname'].' return item ('.$_POST['quickReturnID'].') with title ('.$loan_d['door_code'].') with Quick Return method');
        // show loan information
        include SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
        // create table object
        $table = new simbio_table();
        $table->table_attr = 'class="border" style="width: 100%; margin-bottom: 5px;" cellpadding="5" cellspacing="0"';
        // append data to table row
        $table->appendTableRow(array('Item '.$_POST['quickReturnID'].__(' successfully returned on').$return_date)); //mfc
        $table->appendTableRow(array(__('Kode Pintu'), $loan_d['door_code']));
        $table->appendTableRow(array(__('Member Name'), $loan_d['member_name'], __('Member ID'), $loan_d['member_id']));
        $table->appendTableRow(array(__('Loan Date'), $loan_d['loan_date'], __('Return Date'), $return_date));
        // set the cell attributes
        $table->setCellAttr(1, 0, 'class="dataListHeader" style="color: #fff; font-weight: bold;" colspan="4"');
        $table->setCellAttr(2, 0, 'class="alterCell"');
        $table->setCellAttr(2, 1, 'class="alterCell2" colspan="3"');
        $table->setCellAttr(3, 0, 'class="alterCell" width="15%"');
        $table->setCellAttr(3, 1, 'class="alterCell2" width="35%"');
        $table->setCellAttr(3, 2, 'class="alterCell" width="15%"');
        $table->setCellAttr(3, 3, 'class="alterCell2" width="35%"');
        $table->setCellAttr(4, 0, 'class="alterCell" width="15%"');
        $table->setCellAttr(4, 1, 'class="alterCell2" width="35%"');
        $table->setCellAttr(4, 2, 'class="alterCell" width="15%"');
        $table->setCellAttr(4, 3, 'class="alterCell2" width="35%"');
        // print out the table
        echo $table->printTable();
        // Update the loan status
        $dbs->query("UPDATE door_loan SET is_return='1', return_date='".$return_date."' WHERE door_code='".$dbs->escape_string($_POST['quickReturnID'])."' AND is_return='0'");
    }
    exit();
}


// transaction is started
if (isset($_POST['door_memberID']) OR isset($_SESSION['door_memberID'])) {
    // create member object
    // if there is already member ID session
    if (isset($_SESSION['door_memberID'])) {
        $memberID = trim($_SESSION['door_memberID']);
    } else {
        // new transaction proccess
        // clear previous sessions
        $memberID = trim(preg_replace('@\s*(<.+)$@i', '', $_POST['door_memberID']));
        // write log
        utility::writeLogs($dbs, 'member', $memberID, 'circulation', $_SESSION['realname'].' start transaction with member ('.$memberID.')');
    }
    $member = new member($dbs, $memberID);
    if (!$member->valid()) {
    //if (!$member) {
        # echo '<div class="errorBox">Member ID '.$memberID.' not valid (unregistered in database)</div>';
        echo '<div class="errorBox">'.__('Member ID').' '.$memberID.' '.__(' not valid (unregistered in database)').'</div>'; //mfc
    } else {
        //echo "<script>alert('".$member->is_return."');</script>";
        // get member information
        $member_type_d = $member->getMemberTypeProp();
        // member type ID
        $_SESSION['memberTypeID'] = $member->member_type_id;
        // save member ID to the sessions
        $_SESSION['door_memberID'] = $member->member_id;
        // create renewed/reborrow session array
        $_SESSION['reborrowed'] = array();
        // check membership expire
        $_SESSION['is_expire'] = $member->isExpired();
        // check if membership is blacklisted
        $_SESSION['is_pending'] = $member->isPending();
        // print record
        $_SESSION['receipt_record'] = array();
        // set HTML buttons disable flag
        $disabled = '';
        $add_style = '';
        // check for expire date and pending state
        if ($_SESSION['is_expire'] OR $_SESSION['is_pending']) {
            $disabled = ' disabled ';
            $add_style = ' disabled';
        }
        // show the member information
        echo '<table width="100%" class="border" style="margin-bottom: 5px;" cellpadding="5" cellspacing="0">'."\n";
        echo '<tr>'."\n";
        echo '<td class="dataListHeader" colspan="5">';
        // hidden form for transaction finish
        echo '<form id="finishForm" method="post" target="blindSubmit" action="'.MWB.'locker_loan/door_circulation_action.php" style="display: inline;"><input type="button" class="btn btn-danger" accesskey="T" value="'.__('Finish Transaction').' (T)" onclick="confSubmit(\'finishForm\', \''.__('Are you sure want to finish current transaction?').'\')" /><input type="hidden" name="finish" value="true" /></form>';
        echo '</td>';
        echo '</tr>'."\n";
        echo '<tr>'."\n";
        echo '<td class="alterCell" width="15%"><strong>'.__('Member Name').'</strong></td><td class="alterCell2" width="30%">'.$member->member_name.'</td>';
        echo '<td class="alterCell" width="15%"><strong>'.__('Member ID').'</strong></td><td class="alterCell2" width="30%">'.$member->member_id.'</td>';
        // member photo
        if ($member->member_image) {
          if (file_exists(IMGBS.'persons/'.$member->member_image)) {
            echo '<td class="alterCell2" valign="top" rowspan="3">';
            echo '<img src="'.SWB.'lib/phpthumb/phpThumb.php?src=../../images/persons/'.urlencode($member->member_image).'&w=90" style="border: 1px solid #999999" />';
            echo '</td>';
          }
        }
        echo '</tr>'."\n";
        echo '<tr>'."\n";
        echo '<td class="alterCell" width="15%"><strong>'.__('Member Email').'</strong></td><td class="alterCell2" width="30%">'.$member->member_email.'</td>';
        echo '<td class="alterCell" width="15%"><strong>'.__('Member Type').'</strong></td><td class="alterCell2" width="30%">'.$member->member_type_name.'</td>';
        echo '</tr>'."\n";
        echo '<tr>'."\n";
        echo '<td class="alterCell" width="15%"><strong>'.__('Register Date').'</strong></td><td class="alterCell2" width="30%">'.$member->register_date.'</td>';
        // give notification about expired membership and pending
        $expire_msg = '';
        if ($_SESSION['is_expire']) {
            $expire_msg .= '<span class="error">('.__('Membership Already Expired').')</span>';
        }
        echo '<td class="alterCell" width="15%"><strong>'.__('Expiry Date').'</strong></td><td class="alterCell2" width="30%">'.$member->expire_date.' '.$expire_msg.'</td>';
        echo '</tr>'."\n";
        // member notes and pending information
        if (!empty($member->member_notes) OR $_SESSION['is_pending']) {
          echo '<tr>'."\n";
          echo '<td class="alterCell" width="15%"><strong>Notes</strong></td><td class="alterCell2" colspan="4">';
          if ($member->member_notes) {
              echo '<div class=\'member_notes\'>'.$member->member_notes.'</div>';
          }
          if ($_SESSION['is_pending']) {
              echo '<div class="error">('.__('Membership currently in pending state, loan transaction is locked.').')</div>';
          }
          echo '</td>';
          echo '</tr>'."\n";
        }
        echo '</table>'."\n";
        echo '<ul class="nav nav-tabs nav-justified circ-action-btn">';
           echo '<li class="active"><a class="tab notAJAX" href="'.MWB.'locker_loan/door_loan.php" target="listsFrame">'.__('Loans').' (L)</a></li>';
           echo '</ul>';
           echo '<iframe src="modules/locker_loan/door_loan.php" id="listsFrame" name="listsFrame" class="expandable border"></iframe>'."\n";
     }
    exit();
 }