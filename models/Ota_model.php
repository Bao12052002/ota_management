<?php
// Path: /application/modules/ota_management/models/Ota_model.php
// File này chứa toàn bộ logic xử lý dữ liệu và đồng bộ.

defined('BASEPATH') or exit('No direct script access allowed');

class Ota_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }
    
    // --- CÁC HÀM LẤY DỮ LIỆU CHO VIEW (DÙNG CHO MODAL) --- //

    public function get_internal_tours()
    {
        return $this->db->select('id, description')->order_by('description', 'asc')->get('tblitems')->result_array();
    }
    
    public function get_ota_channels()
    {
        return $this->db->get('tbl_ota_channels')->result_array();
    }
    
    // --- CÁC HÀM CRUD CHO PRODUCT MAPPINGS --- //
    
    public function add_product_mapping($data)
    {
        $this->db->insert('tbl_ota_product_mappings', [
            'tour_id' => $data['tour_id'],
            'channel_id' => $data['channel_id'],
            'ota_product_code' => trim($data['ota_product_code']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        ]);
        return $this->db->insert_id();
    }

    public function update_product_mapping($data, $id)
    {
        $this->db->where('id', $id);
        $this->db->update('tbl_ota_product_mappings', [
            'tour_id' => $data['tour_id'],
            'channel_id' => $data['channel_id'],
            'ota_product_code' => trim($data['ota_product_code']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        ]);
        return $this->db->affected_rows() > 0;
    }

    public function delete_product_mapping($id)
    {
        $this->db->where('id', $id);
        $this->db->delete('tbl_ota_product_mappings');
        return $this->db->affected_rows() > 0;
    }
    
    // --- LOGIC ĐỒNG BỘ TỰ ĐỘNG BẰNG CRON JOB --- //

    private function get_last_sync_date($channel_id)
    {
        $this->db->select_max('booking_date');
        $this->db->where('channel_id', $channel_id);
        $result = $this->db->get('tbl_ota_bookings')->row();
        return $result && $result->booking_date ? $result->booking_date : date('Y-m-d H:i:s', strtotime('-90 days'));
    }

    public function sync_viator_bookings()
    {
        $channel_code = 'VIATOR';
        $channel_id = $this->get_channel_id_by_code($channel_code);
        if(!$channel_id) return;
        $last_sync_date = $this->get_last_sync_date($channel_id);
        $new_bookings = $this->db->where('created_at >', $last_sync_date)->get('tbl_viator_bookings')->result_array();
        foreach ($new_bookings as $booking) {
            $this->process_booking($booking, $channel_id, $channel_code);
        }
    }

    public function sync_klook_bookings()
    {
        $channel_code = 'KLOOK';
        $channel_id = $this->get_channel_id_by_code($channel_code);
        if(!$channel_id) return;
        $last_sync_date = $this->get_last_sync_date($channel_id);
        $new_bookings = $this->db->where('created_at >', $last_sync_date)->get('tbl_klook_bookings')->result_array();
        foreach ($new_bookings as $booking) {
            $this->process_booking($booking, $channel_id, $channel_code);
        }
    }

    public function sync_trip_bookings()
    {
        $channel_code = 'TRIP';
        $channel_id = $this->get_channel_id_by_code($channel_code);
        if(!$channel_id) return;
        $last_sync_date = $this->get_last_sync_date($channel_id);
        $new_bookings = $this->db->where('created_at >', $last_sync_date)->get('tbl_trip_orders')->result_array();
        foreach ($new_bookings as $booking) {
            $this->process_booking($booking, $channel_id, $channel_code);
        }
    }

    public function sync_gyg_bookings()
    {
        $channel_code = 'GYG';
        $channel_id = $this->get_channel_id_by_code($channel_code);
        if(!$channel_id) return;
        $last_sync_date = $this->get_last_sync_date($channel_id);
        $new_bookings = $this->db->where('created_at >', $last_sync_date)->get('tbl_gyg_bookings')->result_array();
        foreach ($new_bookings as $booking) {
            $this->process_booking($booking, $channel_id, $channel_code);
        }
    }

    private function process_booking($booking, $channel_id, $channel_code)
    {
        $normalized_data = $this->normalize_booking_data($booking, $channel_id, $channel_code);

        if (!$normalized_data || empty($normalized_data['tour_id']) || empty($normalized_data['channel_booking_ref'])) {
            log_activity('OTA Sync: Could not normalize or find mapping for booking from channel ' . $channel_code . '. Old Booking Data: ' . json_encode($booking));
            return false;
        }

        $this->db->where('channel_id', $channel_id);
        $this->db->where('channel_booking_ref', $normalized_data['channel_booking_ref']);
        $exists = $this->db->get('tbl_ota_bookings')->row();

        if ($exists) { return true; }

        $this->db->insert('tbl_ota_bookings', $normalized_data);
        $new_booking_id = $this->db->insert_id();

        if ($new_booking_id && strtoupper($normalized_data['status']) != 'CANCELLED') {
            $this->update_availability_on_new_booking(
                $normalized_data['tour_id'],
                $normalized_data['travel_date'],
                $normalized_data['pax']
            );
        }
        
        log_activity('OTA Sync: Synced new booking ' . $normalized_data['channel_booking_ref'] . ' from ' . $channel_code);
        return true;
    }

    public function normalize_booking_data($booking, $channel_id, $channel_code)
    {
        $data = [];
        $product_code = null;

        switch ($channel_code) {
            case 'VIATOR':
                $product_code = $booking['supplier_product_code'] ?? null;
                $total_pax = 0;
                $customer_name = 'N/A';
                
                if (!empty($booking['rawData'])) {
                    $raw = json_decode($booking['rawData']);
                    if ($raw) {
                         if (isset($raw->items) && is_array($raw->items)) {
                            foreach ($raw->items as $item) { $total_pax += $item->numberOfTravelers ?? 0; }
                        }
                        $holder = $raw->bookingHolderDetails ?? null;
                        $customer_name = ($holder && isset($holder->firstName)) ? trim($holder->firstName . ' ' . ($holder->lastName ?? '')) : 'N/A';
                    }
                }
                $data['channel_booking_ref'] = $booking['booking_reference'] ?? null;
                $data['travel_date'] = $booking['travel_date'] ?? null;
                $data['pax'] = $total_pax;
                $data['customer_name'] = $customer_name;
                $data['total_amount'] = $booking['amount'] ?? 0;
                $data['currency'] = 'USD';
                $data['status'] = strtoupper($booking['transaction_status'] ?? 'UNKNOWN');
                $data['booking_date'] = $booking['created_at'] ?? null;
                $data['raw_data'] = $booking['rawData'] ?? null;
                break;
            case 'KLOOK':
                // SỬA LỖI: Đọc dữ liệu từ đúng các cột và các bảng liên quan
                $product_code = $booking['product_id'] ?? null;
                $booking_uuid = $booking['uuid'] ?? null;

                // --- Tính toán logic cho Pax từ bảng tbl_klook_booking_units ---
                $total_pax = 0;
                if ($booking_uuid) {
                    // SỬA LỖI: Dùng đúng tên cột là `pax_count` thay vì `count`
                    $this->db->select_sum('pax_count', 'total_pax');
                    $this->db->where('booking_id', $booking_uuid);
                    $pax_result = $this->db->get('tbl_klook_booking_units')->row();
                    if ($pax_result && $pax_result->total_pax) {
                        $total_pax = $pax_result->total_pax;
                    }
                }
                
                // --- Lấy tên khách hàng trực tiếp từ tbl_klook_bookings ---
                $customer_name = $booking['customer_name'] ?? 'N/A';

                $data['channel_booking_ref'] = $booking_uuid;
                $data['travel_date'] = $booking['booking_date'] ?? null;
                $data['pax'] = $total_pax;
                $data['customer_name'] = $customer_name;
                $data['total_amount'] = $booking['total_amount'] ?? 0;
                $data['currency'] = $booking['currency'] ?? 'USD';
                $data['status'] = strtoupper($booking['status'] ?? 'UNKNOWN');
                $data['booking_date'] = $booking['created_at'] ?? null;
                break;
            case 'GYG':
                // SỬA LỖI: Đọc dữ liệu từ đúng các cột và các bảng liên quan của GYG
                $product_code = $booking['product_id'] ?? null;
                $booking_ref = $booking['booking_reference'] ?? null;

                $total_pax = 0;
                $total_amount = 0;

                if ($booking_ref) {
                    // Truy vấn vào bảng items để tính tổng số khách và tổng tiền
                    $this->db->where('booking_reference', $booking_ref);
                    $items = $this->db->get('tbl_gyg_booking_items')->result_array();
                    
                    foreach ($items as $item) {
                        $count = (int)($item['count'] ?? 0);
                        $price = (float)($item['retail_price'] ?? 0);
                        $total_pax += $count;
                        $total_amount += $count * $price;
                    }
                }
                
                $data['channel_booking_ref'] = $booking_ref;
                $data['travel_date'] = isset($booking['date_time']) ? date('Y-m-d', strtotime($booking['date_time'])) : null;
                $data['pax'] = $total_pax;
                $data['customer_name'] = 'N/A'; // GYG không cung cấp tên khách hàng trong bảng này
                $data['total_amount'] = $total_amount;
                $data['currency'] = $booking['currency'] ?? 'USD';
                $data['status'] = strtoupper($booking['status'] ?? 'UNKNOWN');
                $data['booking_date'] = $booking['created_at'] ?? null;
                break;
            case 'TRIP':
                // SỬA LỖI: Đọc dữ liệu từ đúng các cột và các bảng liên quan của Tripadvisor
                $product_code = $booking['item_id'] ?? null;
                $order_id = $booking['id'] ?? null; // Dùng id của order để JOIN

                $total_pax = 0;
                $customer_name = 'N/A';

                if ($order_id) {
                    // Đếm tổng số hành khách trong bảng tbl_trip_passengers
                    $this->db->where('order_id', $order_id);
                    $total_pax = $this->db->count_all_results('tbl_trip_passengers');

                    // Lấy tên của hành khách đầu tiên làm tên khách hàng
                    $this->db->where('order_id', $order_id);
                    $this->db->limit(1);
                    $passenger = $this->db->get('tbl_trip_passengers')->row();
                    if ($passenger) {
                        $customer_name = trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? ''));
                    }
                }
                
                $data['channel_booking_ref'] = $booking['ota_order_id'] ?? null;
                $data['travel_date'] = $booking['use_start_date'] ?? null;
                $data['pax'] = $total_pax;
                $data['customer_name'] = $customer_name;
                $data['total_amount'] = $booking['total_amount'] ?? 0;
                $data['currency'] = $booking['currency'] ?? 'USD';
                $data['status'] = strtoupper($booking['status'] ?? 'UNKNOWN');
                $data['booking_date'] = $booking['created_at'] ?? null;
                break;
            default:
                return false;
           
        }

        if (!$product_code) {
             log_activity('OTA Sync: Product code is missing for channel ' . $channel_code);
             return false;
        }

        $mapping = $this->get_mapping_by_code($product_code, $channel_id);
        $data['tour_id'] = $mapping ? $mapping->tour_id : null;
        $data['channel_id'] = $channel_id;

        return $data;
    }
    
    // --- CÁC HÀM HỖ TRỢ --- //
    
    public function get_channel_id_by_code($code)
    {
        $channel = $this->db->where('code', $code)->get('tbl_ota_channels')->row();
        return $channel ? $channel->id : null;
    }
    
    private function get_mapping_by_code($product_code, $channel_id)
    {
        if (empty($product_code) || empty($channel_id)) { return null; }
        $this->db->where('TRIM(ota_product_code)', trim($product_code));
        $this->db->where('channel_id', $channel_id);
        return $this->db->get('tbl_ota_product_mappings')->row();
    }
    
    private function update_availability_on_new_booking($tour_id, $travel_date, $pax_change)
    {
        if(!$tour_id || !$travel_date || !$pax_change) return;
        $this->db->where('tour_id', $tour_id)->where('available_date', $travel_date);
        $this->db->set('booked_slots', 'booked_slots + ' . (int)$pax_change, FALSE);
        $this->db->update('tbl_ota_availability');
    }
}
