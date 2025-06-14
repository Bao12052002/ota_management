<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Các cột sẽ được chọn từ database, với alias rõ ràng cho từng cột.
$aColumns = [
    'tbl_ota_product_mappings.id as id',
    'tblitems.description as tour_description',
    'tbl_ota_channels.name as channel_name',
    'tbl_ota_product_mappings.ota_product_code as ota_product_code', // Sửa: Thêm alias
    'tbl_ota_product_mappings.is_active as is_active',            // Sửa: Thêm alias
    'tbl_ota_product_mappings.tour_id as tour_id',                  // Sửa: Thêm alias
    'tbl_ota_product_mappings.channel_id as channel_id',            // Sửa: Thêm alias
];

$sIndexColumn = 'id';
$sTable       = 'tbl_ota_product_mappings';

$join = [
    'LEFT JOIN tblitems ON tblitems.id = tbl_ota_product_mappings.tour_id',
    'LEFT JOIN tbl_ota_channels ON tbl_ota_channels.id = tbl_ota_product_mappings.channel_id',
];

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    
    // Cột 1: Tên tour - Sử dụng alias 'tour_description'
    $row[] = $aRow['tour_description'];

    // Cột 2: Tên kênh OTA - Sử dụng alias 'channel_name'
    $row[] = $aRow['channel_name'];

    // Cột 3: Mã sản phẩm OTA - Sử dụng alias 'ota_product_code'
    $row[] = $aRow['ota_product_code'];
    
    // Cột 4: Trạng thái Active - Sử dụng alias 'is_active'
    $checked = ($aRow['is_active'] == 1) ? 'checked' : '';
    $toggleActive = '<div class="onoffswitch">
        <input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="c_' . $aRow['id'] . '" data-id="' . $aRow['id'] . '" ' . $checked . ' disabled>
        <label class="onoffswitch-label" for="c_' . $aRow['id'] . '"></label>
    </div>';
    $row[] = $toggleActive;

    // Cột 5: Nút tùy chọn (Sửa/Xóa) - Sử dụng các alias tương ứng
    $options = icon_btn('#', 'pencil-square-o', 'btn-default', [
        'onclick' => 'edit_mapping(this, ' . $aRow['id'] . '); return false;',
        'data-tour-id' => $aRow['tour_id'],
        'data-channel-id' => $aRow['channel_id'],
        'data-ota-product-code' => $aRow['ota_product_code'],
        'data-is-active' => $aRow['is_active'],
    ]);
    $options .= icon_btn('ota_management/delete_product_mapping/' . $aRow['id'], 'remove', 'btn-danger _delete');
    
    $row[] = $options;

    $output['aaData'][] = $row;
}

