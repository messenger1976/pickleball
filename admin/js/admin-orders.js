/* Admin order edit/cancel actions (admin/orders.php) */
(function($) {
    'use strict';

    var activeOrderId = 0;
    var elapsedTimer = null;

    function esc(text) {
        return $('<div>').text(text == null ? '' : text).html();
    }

    function money(amount) {
        return parseFloat(amount || 0).toFixed(2);
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatElapsed(totalSeconds) {
        if (totalSeconds < 0) {
            totalSeconds = 0;
        }
        var days = Math.floor(totalSeconds / 86400);
        var hours = Math.floor((totalSeconds % 86400) / 3600);
        var mins = Math.floor((totalSeconds % 3600) / 60);
        var secs = totalSeconds % 60;
        var parts = [];
        if (days > 0) {
            parts.push(days + 'd');
        }
        if (days > 0 || hours > 0) {
            parts.push(pad2(hours) + 'h');
        }
        parts.push(pad2(mins) + 'm');
        parts.push(pad2(secs) + 's');
        return parts.join(' ');
    }

    function updateElapsedClocks() {
        var nowSec = Math.floor(Date.now() / 1000);
        $('.order-elapsed').each(function() {
            var $el = $(this);
            var start = parseInt($el.attr('data-start'), 10);
            if (!start) {
                $el.text('—');
                return;
            }
            var isTerminal = $el.attr('data-terminal') === '1';
            var endAttr = $el.attr('data-end');
            var end = endAttr ? parseInt(endAttr, 10) : 0;
            if (isTerminal) {
                if (!end) {
                    $el.text('—').attr('title', 'Completion time was not recorded');
                    return;
                }
                $el.text(formatElapsed(end - start));
                return;
            }
            $el.text(formatElapsed(nowSec - start));
        });
    }

    function startElapsedClocks() {
        updateElapsedClocks();
        if (elapsedTimer) {
            clearInterval(elapsedTimer);
        }
        elapsedTimer = setInterval(updateElapsedClocks, 1000);
    }

    function showAlert($box, message, isSuccess) {
        $box.removeClass('is-success');
        if (isSuccess) {
            $box.addClass('is-success');
        }
        $box.html(message).show();
    }

    function openModal($modal) {
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('portal-modal-open');
    }

    function closeModal($modal) {
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('.portal-order-modal.is-open').length) {
            $('body').removeClass('portal-modal-open');
        }
    }

    function renderItemsTable(data) {
        var isPending = !!data.is_pending;
        var html = '<table class="portal-orders-items-table portal-orders-modal-items"><thead><tr>';
        html += '<th>Item</th><th class="portal-orders-col-qty">Qty</th>';
        html += '<th class="portal-orders-col-amount">Unit Price</th>';
        html += '<th class="portal-orders-col-amount">Line Total</th>';
        html += '<th class="portal-orders-col-action">Action</th></tr></thead><tbody>';

        if (!data.items || !data.items.length) {
            html += '<tr><td colspan="5" class="portal-orders-no-items">No items on this order.</td></tr>';
        } else {
            $.each(data.items, function(_, item) {
                var disabled = isPending ? '' : ' disabled';
                var btnClass = isPending ? '' : ' is-disabled';
                html += '<tr data-detail-id="' + item.orders_details_id + '">';
                html += '<td><div class="portal-orders-item-cell">';
                html += '<img src="' + esc(item.picture_url.indexOf('admin/') === 0 ? item.picture_url.substring(6) : item.picture_url) + '" alt="" class="portal-orders-item-thumb">';
                html += '<span>' + esc(item.name) + '</span></div></td>';
                html += '<td class="portal-orders-col-qty">' + esc(item.orders_details_qty) + '</td>';
                html += '<td class="portal-orders-col-amount">' + money(item.price) + '</td>';
                html += '<td class="portal-orders-col-amount"><strong>' + money(item.line_total) + '</strong></td>';
                html += '<td class="portal-orders-col-action"><button type="button" class="portal-orders-item-delete' + btnClass + '" data-detail-id="' + item.orders_details_id + '"' + disabled + '>Remove</button></td>';
                html += '</tr>';
            });
        }

        html += '</tbody><tfoot><tr>';
        html += '<td colspan="3" class="portal-orders-detail-total-label">Order Grand Total</td>';
        html += '<td class="portal-orders-col-amount" id="adminModalOrderTotal"><strong>' + money(data.total_amount) + '</strong></td>';
        html += '<td></td></tr></tfoot></table>';
        return html;
    }

    function fillEditModal(data) {
        activeOrderId = data.order_id;
        $('#adminEditOrderId').text('#' + data.order_id);
        $('#adminEditTransDate').text(data.trans_date);
        $('#adminEditMenuDate').text(data.menu_date);
        $('#adminEditRestaurant').text(data.restaurant);
        $('#adminEditPayment').text(data.payment);
        $('#adminEditShipping').text(data.shipping);
        $('#adminEditFullName').text(data.fullname);
        $('#adminEditEmail').text(data.email);
        $('#adminEditContact').text(data.contact);
        $('#adminEditCompany').text(data.company);
        $('#adminEditDepartment').text(data.department);
        $('#adminEditAddress').val(data.address).prop('readonly', !!data.is_cancelled);
        $('#adminEditRemarks').val(data.remarks || '');
        $('#adminEditStatus').val(data.status);
        $('#adminEditStatus').prop('disabled', data.is_cancelled);
        $('#adminEditOrderItems').html(renderItemsTable(data));
        $('#adminEditOrderMsg').hide().empty();
        $('#btnAdminSaveOrder').prop('disabled', !!data.is_cancelled);
    }

    function loadOrderDetail(orderId, callback) {
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'getAdminOrderDetail', order_id: orderId },
            dataType: 'json',
            success: function(res) {
                if (res.validation_status === 'T' && res.data) {
                    callback(null, res.data);
                } else {
                    callback(res.msg || 'Could not load order.');
                }
            },
            error: function() {
                callback('Something went wrong. Please try again.');
            }
        });
    }

    $(function() {
        startElapsedClocks();

        $(document).on('click', '.portal-orders-expand-btn', function() {
            var orderId = $(this).data('order-id');
            var $detailRow = $('#order-details-' + orderId);
            var isOpen = !$detailRow.prop('hidden');
            $detailRow.prop('hidden', isOpen);
            $(this).attr('aria-expanded', !isOpen);
            $(this).closest('.portal-orders-row').toggleClass('is-expanded', !isOpen);
        });

        $(document).on('click', '.btn-admin-order-edit', function() {
            var orderId = $(this).data('orderid');
            loadOrderDetail(orderId, function(err, data) {
                if (err) {
                    alert(err);
                    return;
                }
                fillEditModal(data);
                openModal($('#adminOrderEditModal'));
            });
        });

        $(document).on('click', '.btn-admin-order-cancel', function() {
            if ($(this).prop('disabled')) {
                return;
            }
            activeOrderId = $(this).data('orderid');
            $('#adminCancelOrderId').text('#' + activeOrderId);
            $('#adminCancelReason').val('');
            $('#adminCancelOrderMsg').hide().empty();
            openModal($('#adminOrderCancelModal'));
        });

        $('.portal-order-modal-close, .portal-order-modal-dismiss, .portal-order-modal-backdrop').on('click', function() {
            closeModal($(this).closest('.portal-order-modal'));
        });

        $('#btnAdminSaveOrder').on('click', function() {
            if (!activeOrderId) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving…');
            $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'UpdateOrder',
                    orderid: activeOrderId,
                    address: $('#adminEditAddress').val(),
                    status: $('#adminEditStatus').val(),
                    remarks: $('#adminEditRemarks').val()
                },
                dataType: 'json',
                success: function(res) {
                    if (res.msg === true) {
                        closeModal($('#adminOrderEditModal'));
                        location.reload();
                    } else {
                        $btn.prop('disabled', false).text('Save Changes');
                        showAlert($('#adminEditOrderMsg'), res.error || 'Could not save order.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Save Changes');
                    showAlert($('#adminEditOrderMsg'), 'Something went wrong. Please try again.');
                }
            });
        });

        $(document).on('click', '.portal-orders-item-delete:not(:disabled)', function() {
            var detailId = $(this).data('detail-id');
            if (!activeOrderId || !detailId || !confirm('Remove this item from the order?')) {
                return;
            }
            $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'deleteAdminOrderItem',
                    order_id: activeOrderId,
                    detail_id: detailId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.validation_status === 'T' && res.data) {
                        fillEditModal(res.data);
                        showAlert($('#adminEditOrderMsg'), res.msg, true);
                    } else {
                        showAlert($('#adminEditOrderMsg'), res.msg || 'Could not remove item.');
                    }
                },
                error: function() {
                    showAlert($('#adminEditOrderMsg'), 'Something went wrong. Please try again.');
                }
            });
        });

        $('#btnAdminConfirmCancel').on('click', function() {
            var reason = $.trim($('#adminCancelReason').val());
            if (!activeOrderId) {
                return;
            }
            if (reason === '') {
                showAlert($('#adminCancelOrderMsg'), 'Please provide a cancellation reason.');
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text('Cancelling…');
            $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'cancelAdminOrder',
                    order_id: activeOrderId,
                    reason: reason
                },
                dataType: 'json',
                success: function(res) {
                    if (res.validation_status === 'T') {
                        closeModal($('#adminOrderCancelModal'));
                        location.reload();
                    } else {
                        $btn.prop('disabled', false).text('Confirm Cancellation');
                        showAlert($('#adminCancelOrderMsg'), res.msg || 'Could not cancel order.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Confirm Cancellation');
                    showAlert($('#adminCancelOrderMsg'), 'Something went wrong. Please try again.');
                }
            });
        });
    });
})(jQuery);
