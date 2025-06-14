<?php
// =================================================================
// File 1: Cập nhật file Controller để có hàm bookings()
// Path: /application/modules/ota_management/controllers/Ota_management.php
// =================================================================

// THAY THẾ TOÀN BỘ FILE HIỆN TẠI BẰNG NỘI DUNG NÀY

defined('BASEPATH') or exit('No direct script access allowed');

class Ota_management extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!has_permission('ota_management', '', 'view')) {
            access_denied('OTA Management');
        }
        $this->load->model('ota_model');
    }

    /**
     * Hàm index() sẽ là hàm mặc định.
     * Ta sẽ cho nó chuyển hướng đến trang quản lý bookings để tiện lợi.
     */
    public function index()
    {
        redirect(admin_url('ota_management/bookings'));
    }

    /**
     * SỬA LỖI: Tạo hàm bookings() để khớp với đường link
     * Đây sẽ là trang chính để quản lý Bookings.
     */
    public function bookings()
    {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('ota_management', 'tables/bookings'));
        }
        $data['title'] = _l('ota_bookings');
        $this->load->view('bookings/manage', $data);
    }

    /**
     * Trang quản lý Ánh xạ sản phẩm (giữ nguyên)
     */
    public function products()
    {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('ota_management', 'tables/product_mappings'));
        }
        $data['title'] = _l('ota_product_mappings');
        $this->load->view('products/manage', $data);
    }

    public function test($channel_code = '', $booking_ref = '')
    {
        if (empty($channel_code) || empty($booking_ref)) {
            echo "<h1>Cron Test Helper</h1>";
            echo "Vui lòng cung cấp Kênh OTA và Mã Booking trên URL.";
            echo "<br><br>";
            echo "Ví dụ: <a href='" . admin_url('ota_management/test/GYG/GYG68216da831a43235647321') . "'>" . admin_url('ota_management/test/GYG/GYG68216da831a43235647321') . "</a>";
            die();
        }

        $channel_code = strtoupper($channel_code);
        $channel_id = $this->ota_model->get_channel_id_by_code($channel_code);
        if (!$channel_id) {
            die("Không tìm thấy kênh OTA với mã: " . $channel_code);
        }

        $booking_data = null;
        switch ($channel_code) {
            case 'VIATOR':
                $booking_data = $this->db->where('booking_reference', $booking_ref)->get('tbl_viator_bookings')->row_array();
                break;
            case 'KLOOK':
                $booking_data = $this->db->where('uuid', $booking_ref)->get('tbl_klook_bookings')->row_array();
                break;
            case 'GYG':
                $booking_data = $this->db->where('booking_reference', $booking_ref)->get('tbl_gyg_bookings')->row_array();
                break;
            case 'TRIP':
                 $booking_data = $this->db->where('ota_order_id', $booking_ref)->get('tbl_trip_orders')->row_array();
                break;
        }

        if ($booking_data) {
            echo "<pre>";
            echo "--- Debugging Booking Ref: " . $booking_ref . " from channel " . $channel_code . " ---<br>";
            
            // Gọi hàm xử lý và xem kết quả
            $normalized_data = $this->ota_model->normalize_booking_data($booking_data, $channel_id, $channel_code);
            
            echo "--- Raw Booking Data from old table ---<br>";
            print_r($booking_data);

            echo "<br>--- Normalized Data (dữ liệu đã được chuẩn hóa) ---<br>";
            print_r($normalized_data);
    
            if ($normalized_data && !empty($normalized_data['tour_id'])) {
                echo "<br>--- SUCCESS: Mapping found! Internal Tour ID is: " . $normalized_data['tour_id'] . " ---";
            } else {
                 echo "<br>--- ERROR: Could not find product mapping! ---";
                 if(isset($normalized_data['tour_id'])) {
                     echo "<br>Reason: tour_id is empty or null. Check your 'Sản phẩm OTA' settings.";
                 }
            }
            echo "</pre>";
        } else {
            echo "Could not find booking with reference: " . $booking_ref . " for channel " . $channel_code;
        }
    }
    /**
     * Xử lý Thêm/Sửa một ánh xạ (giữ nguyên)
     */
    public function product_mapping()
    {
        if ($this->input->post()) {
            $data = $this->input->post();
            if ($data['id'] == '') {
                if (!has_permission('ota_management', '', 'create')) {
                    access_denied('OTA Management');
                }
                $id = $this->ota_model->add_product_mapping($data);
                if ($id) { set_alert('success', _l('added_successfully', _l('ota_product_mapping'))); }
            } else {
                if (!has_permission('ota_management', '', 'edit')) {
                    access_denied('OTA Management');
                }
                $success = $this->ota_model->update_product_mapping($data, $data['id']);
                if ($success) { set_alert('success', _l('updated_successfully', _l('ota_product_mapping'))); }
            }
        }
        redirect(admin_url('ota_management/products'));
    }
    
    /**
     * Xóa một ánh xạ (giữ nguyên)
     */
    public function delete_product_mapping($id)
    {
        if (!has_permission('ota_management', '', 'delete')) {
            access_denied('OTA Management');
        }
        if (!$id) {
            redirect(admin_url('ota_management/products'));
        }
        $success = $this->ota_model->delete_product_mapping($id);
        if ($success) {
            set_alert('success', _l('deleted', _l('ota_product_mapping')));
        }
        redirect(admin_url('ota_management/products'));
    }
    /**
     * HÀM MỚI: Lấy và định dạng các booking mới
     * @param int $last_id ID của booking cuối cùng trên bảng
     */
    private function fetch_new_bookings($last_id)
    {
        $this->db->where(db_prefix() . 'ota_bookings.id >', (int)$last_id);
        
        // Đoạn code này được tái sử dụng từ file tables/bookings.php
        $aColumns = [
            'tbl_ota_bookings.id as booking_id', // Thêm để lấy ID mới nhất
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
        $sTable       = db_prefix() . 'ota_bookings';
        $join = [
            'LEFT JOIN ' . db_prefix() . 'items as tblitems ON tblitems.id = ' . db_prefix() . 'ota_bookings.tour_id',
            'LEFT JOIN ' . db_prefix() . 'ota_channels ON ' . db_prefix() . 'ota_channels.id = ' . db_prefix() . 'ota_bookings.channel_id',
        ];

        $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, [], [db_prefix() . 'ota_bookings.id']);
        $output  = $result['output'];
        $rResult = $result['rResult'];
        
        $new_data = [];
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
            // Thêm thuộc tính data-id cho thẻ <tr>
            $row['DT_RowAttr'] = ['data-id' => $aRow['booking_id']];
            $new_data[] = $row;
        }

        echo json_encode(['data' => $new_data]);
        die();
    }
}