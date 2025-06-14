<?php
// =================================================================
// File 3.1: View quản lý Bookings
// Path: /application/modules/ota_management/views/bookings/manage.php
// =================================================================

init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                             <h4><?php echo _l('ota_bookings'); ?></h4>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />
                        <?php
                        render_datatable([
                            _l('travel_date'),
                            _l('internal_tour'),
                            _l('ota_channel'),
                            _l('channel_booking_ref'),
                            _l('customer_name'),
                            _l('pax'),
                            _l('total_amount'),
                            _l('status'),
                            _l('booking_date'),
                        ], 'bookings');
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function(){
        initDataTable('.table-bookings', window.location.href, undefined, undefined, 'undefined', [8, 'desc']);
    });
</script>

<script>
    $(function(){
        // Khởi tạo bảng DataTables
        var bookingsTable = initDataTable('.table-bookings', window.location.href, undefined, undefined, 'undefined', [8, 'desc']);

        var lastKnownId = 0;
        
        // Lấy ID của booking mới nhất sau khi bảng được tải lần đầu
        if (bookingsTable) {
             bookingsTable.on('draw.dt', function() {
                var latestRow = $('.table-bookings tbody tr:first');
                if (latestRow.length) {
                    var newId = latestRow.data('id');
                    if (newId > lastKnownId) {
                        lastKnownId = newId;
                    }
                }
             });
        }
       
        // Hàm để kiểm tra booking mới
        function fetchNewBookings() {
             // Chỉ chạy khi bảng đã được khởi tạo
            if (lastKnownId === 0) {
                 var firstRow = $('.table-bookings tbody tr:first');
                 if(firstRow.length) {
                     lastKnownId = firstRow.data('id') || 0;
                 }
                 if(lastKnownId === 0) {
                     // Nếu bảng trống, thử lại sau
                     return;
                 }
            }

            $.post(admin_url + 'ota_management/bookings', { last_booking_id: lastKnownId })
                .done(function(response) {
                    var result = JSON.parse(response);
                    if (result.data && result.data.length > 0) {
                        
                        // Thêm các dòng mới vào bảng
                        bookingsTable.rows.add(result.data).draw(false);
                        
                        // Cập nhật lại ID cuối cùng
                        var newLastId = result.data[result.data.length-1].DT_RowAttr['data-id'];
                        if (newLastId > lastKnownId) {
                            lastKnownId = newLastId;
                        }

                        // Làm nổi bật các dòng mới
                        result.data.forEach(function(row) {
                           $('tr[data-id="'+row.DT_RowAttr['data-id']+'"]').addClass('new-row-highlight');
                        });

                        // Xóa hiệu ứng nổi bật sau vài giây
                        setTimeout(function() {
                            $('.new-row-highlight').removeClass('new-row-highlight');
                        }, 5000);
                    }
                });
        }

        // Bắt đầu kiểm tra định kỳ mỗi 20 giây (20000 mili giây)
        setInterval(fetchNewBookings, 2000);
    });
</script>
