<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: OTA Management
Description: Centralized management for OTA bookings, availability, and products.
Version: 1.0.1
Requires at least: 2.3.2
*/

define('OTA_MANAGEMENT_MODULE_NAME', 'ota_management');

/**
* Register activation module hook
*/
register_activation_hook(OTA_MANAGEMENT_MODULE_NAME, 'ota_management_activation_hook');

function ota_management_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}


/**
* Register permissions
*/
hooks()->add_filter('staff_permissions', 'ota_management_permissions_filter');

function ota_management_permissions_filter($permissions)
{
    $permissions['ota_management'] = [
        'name'         => _l('ota_management'),
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
        ],
    ];
    return $permissions;
}

/**
* Add sidebar menu item
*/
hooks()->add_action('admin_init', 'ota_management_init_menu_items');

function ota_management_init_menu_items()
{
    $CI = &get_instance();
    
    // Chỉ thực hiện nếu nhân viên có quyền xem module
    if (has_permission('ota_management', '', 'view')) {
        
        // Menu cha: QUAN TRỌNG - KHÔNG có thuộc tính 'href'
        $CI->app_menu->add_sidebar_menu_item('ota-management-parent', [
            'name'     => _l('ota_management'),
            'position' => 20,
            'icon'     => 'fa fa-sync',
        ]);

        // Menu con - Booking
        $CI->app_menu->add_sidebar_children_item('ota-management-parent', [
            'slug'     => 'ota-management-bookings',
            'name'     => _l('ota_bookings'),
            'href'     => admin_url('ota_management/bookings'), // <-- Menu con thì CÓ href
            'position' => 1,
        ]);

        // Menu con - Sản phẩm OTA
        $CI->app_menu->add_sidebar_children_item('ota-management-parent', [
            'slug'     => 'ota-management-products',
            'name'     => _l('ota_product_mappings'),
            'href'     => admin_url('ota_management/products'), // <-- Menu con thì CÓ href
            'position' => 2,
        ]);
    }
}

/**
* Load language files
*/
hooks()->add_action('after_setup_theme', 'ota_management_load_language');

function ota_management_load_language() {
    $CI = &get_instance();
    // Thay 'vietnamese' bằng ngôn ngữ bạn dùng nếu cần
    $CI->lang->load('ota_management', 'vietnamese');
}

hooks()->add_action('after_cron_run', 'ota_management_cron_run');

function ota_management_cron_run($manually)
{
    $CI = &get_instance();
    $CI->load->model('ota_management/ota_model');
    
    log_activity('OTA Sync Cron Job Started.');

    // TRẢ LẠI NHƯ CŨ
    $CI->ota_model->sync_viator_bookings();
    $CI->ota_model->sync_klook_bookings();
    $CI->ota_model->sync_trip_bookings();
    $CI->ota_model->sync_gyg_bookings();
    
    log_activity('OTA Sync Cron Job Finished.');
}
