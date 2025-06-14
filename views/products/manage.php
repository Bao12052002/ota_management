<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <a href="#" class="btn btn-info pull-left display-block" onclick="new_mapping(); return false;">
                                <?php echo _l('new_product_mapping'); ?>
                            </a>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />
                        <?php
                        render_datatable([
                            _l('internal_tour'),
                            _l('ota_channel'),
                            _l('ota_product_code'),
                            _l('is_active'),
                            _l('options')
                        ], 'product-mappings');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal để thêm/sửa -->
<div class="modal fade" id="mapping_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?php echo form_open(admin_url('ota_management/product_mapping'), ['id' => 'mapping-form']); // Thêm id cho form ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="edit-title hide"><?php echo _l('edit_product_mapping'); ?></span>
                    <span class="add-title"><?php echo _l('new_product_mapping'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="mapping_id">
                <?php
                // Lấy danh sách tour nội bộ và kênh OTA
                // SỬA LỖI: Không gọi model trực tiếp trong view. 
                // Dữ liệu này nên được truyền từ controller, nhưng để đơn giản, ta sẽ gọi ở đây
                // và đảm bảo nó hoạt động đúng.
                $this->load->model('ota_management/ota_model');
                $tours = $this->ota_model->get_internal_tours();
                $channels = $this->ota_model->get_ota_channels();
                
                // SỬA LỖI: Thêm class 'selectpicker' và 'ajax-search' nếu cần,
                // và data-live-search="true" để đảm bảo Javascript hoạt động đúng.
                echo render_select('tour_id', $tours, ['id', 'description'], 'internal_tour', '', ['data-live-search' => 'true']);
                echo render_select('channel_id', $channels, ['id', 'name'], 'ota_channel');
                echo render_input('ota_product_code', 'ota_product_code');
                ?>
                <div class="checkbox checkbox-primary">
                     <input type="checkbox" name="is_active" id="is_active" checked>
                     <label for="is_active"><?php echo _l('is_active'); ?></label>
                  </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(function(){
        // Khởi tạo bảng
        initDataTable('.table-product-mappings', window.location.href, undefined, undefined, 'undefined');

        // SỬA LỖI: Thêm validator cho form để đảm bảo dữ liệu hợp lệ trước khi gửi
        appValidateForm($('#mapping-form'), {
            tour_id: 'required',
            channel_id: 'required',
            ota_product_code: 'required'
        });
    });

    // Hàm mở modal để thêm mới
    function new_mapping() {
        // Xóa dữ liệu cũ
        $('#mapping_id').val('');
        $('#mapping_modal select[name="tour_id"]').selectpicker('val', '');
        $('#mapping_modal select[name="channel_id"]').selectpicker('val', '');
        $('#mapping_modal input[name="ota_product_code"]').val('');
        $('#mapping_modal input[name="is_active"]').prop('checked', true);
        
        // Cập nhật tiêu đề và hiển thị modal
        $('#mapping_modal .add-title').removeClass('hide');
        $('#mapping_modal .edit-title').addClass('hide');
        $('#mapping_modal').modal('show');
    }
    
    // Hàm mở modal để sửa
    function edit_mapping(invoker, id) {
        var tour_id = $(invoker).data('tour-id');
        var channel_id = $(invoker).data('channel-id');
        var ota_product_code = $(invoker).data('ota-product-code');
        var is_active = $(invoker).data('is-active');

        // Điền dữ liệu vào form
        $('#mapping_id').val(id);
        $('#mapping_modal select[name="tour_id"]').selectpicker('val', tour_id);
        $('#mapping_modal select[name="channel_id"]').selectpicker('val', channel_id);
        $('#mapping_modal input[name="ota_product_code"]').val(ota_product_code);
        $('#mapping_modal input[name="is_active"]').prop('checked', is_active == 1);
        
        // Cập nhật tiêu đề và hiển thị modal
        $('#mapping_modal .add-title').addClass('hide');
        $('#mapping_modal .edit-title').removeClass('hide');
        $('#mapping_modal').modal('show');
    }
</script>
