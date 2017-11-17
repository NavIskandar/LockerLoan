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

/* Locker Loan module submenu items */
// IP based access limitation

do_checkIP('smc');
do_checkIP('smc-system');

$menu[] = array('Header', __('Loker'));
$menu[] = array(__('Daftar Lemari Loker'), MWB.'locker_loan/index.php', __('Daftar Locker'));
$menu[] = array(__('Tambah Lemari Loker'), MWB.'locker_loan/index.php?action=detail', __('Tambah Locker'));
$menu[] = array('Header', __('Pintu'));
$menu[] = array(__('Daftar Pintu Loker'), MWB.'locker_loan/door_list.php', __('Daftar Pintu'));
$menu[] = array(__('Daftar Pintu Keluar'), MWB.'locker_loan/checkout_door.php#', __('Daftar Pintu Keluar'));
$menu[] = array('Header', __('Peminjaman'));
$menu[] = array(__('Mulai Transaksi'), MWB.'locker_loan/door_circulation.php', __('Transaksi Peminjaman'));
$menu[] = array(__('Pengembalian Kilat'), MWB.'locker_loan/quick_return.php', __('Pengembalian Cepat'));
$menu[] = array(__('Sejarah Peminjaman'), MWB.'locker_loan/loan_history.php#', __('Sejaram Peminjaman'));
$menu[] = array('Header', __('Perkakas'));
$menu[] = array(__('Cetak label dan barkode kunci pintu'), MWB.'locker_loan/label_print.php', __('Label Pintu'));