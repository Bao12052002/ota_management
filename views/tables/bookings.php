<?php
// =================================================================
// File 3.2: View xử lý bảng Bookings (DataTables)
// Path: /application/modules/ota_management/views/tables/bookings.php
// =================================================================

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    'tbl_ota_bookings.travel_date as travel_date',
    'tblitems.description as tour_name',
    'tbl_ota_channels.name as channel_name',
    'tbl_ota_bookings.channel_booking_ref as channel_booking_ref',
    'tbl_ota_bookings.customer_name as customer_name',
    'tbl_ota_bookings.pax as pax',
    'tbl_ota_bookings.total_amount as total_amount',
    'tbl_ota_bookings.status as status',
    'tbl_ota_bookings.booking_date as booking_date',
    'tbl_ota_bookings.currency as currency',
];
$sIndexColumn = 'id';
$sTable       = 'tbl_ota_bookings';
$join = [
    'LEFT JOIN tblitems ON tblitems.id = tbl_ota_bookings.tour_id',
    'LEFT JOIN tbl_ota_channels ON tbl_ota_channels.id = tbl_ota_bookings.channel_id',
];
$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join);
$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $row[] = _d($aRow['travel_date']);
    $row[] = $aRow['tour_name'];
    $row[] = $aRow['channel_name'];
    $row[] = $aRow['channel_booking_ref'];
    $row[] = $aRow['customer_name'];
    $row[] = $aRow['pax'];
    $row[] = app_format_money($aRow['total_amount'], $aRow['currency']);
    $row[] = '<span class="label label-default">' . $aRow['status'] . '</span>';
    $row[] = _dt($aRow['booking_date']);
    $output['aaData'][] = $row;
}