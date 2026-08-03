/* Portal order edit/cancel actions (orders.php) */
(function($) {
    'use strict';

    var activeOrderId = 0;

    function esc(text) {
        return $('<div>').text(text == null ? '' : text).html();
    }

    function money(amount) {
        return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
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

    function renderItemsTable(data, context) {
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
                html += '<img src="' + esc(item.picture_url) + '" alt="" class="portal-orders-item-thumb">';
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
        html += '<td class="portal-orders-col-amount" id="modalOrderTotal"><strong>' + money(data.total_amount) + '</strong></td>';
        html += '<td></td></tr></tfoot></table>';
        return html;
    }

    function statusClass(status) {
        var s = (status || '').toLowerCase().trim();
        if (s === 'completed' || s === 'delivered') {
            return 'is-success';
        }
        if (s === 'cancelled' || s === 'canceled') {
            return 'is-danger';
        }
        if (s === 'pending' || s === 'processing') {
            return 'is-warning';
        }
        return 'is-neutral';
    }

    function renderExpandDetailPanel(data) {
        var orderId = data.order_id;
        var html = '<div class="portal-orders-detail-panel">';
        html += '<div class="portal-orders-detail-head">';
        html += '<h3>Order #' + orderId + ' — Items</h3>';
        if (data.remarks) {
            html += '<p class="portal-orders-remarks"><strong>Remarks:</strong> ' + esc(data.remarks) + '</p>';
        }
        html += '</div>';

        if (!data.items || !data.items.length) {
            html += '<p class="portal-orders-no-items">No line items found for this order.</p>';
        } else {
            html += '<table class="portal-orders-items-table"><thead><tr>';
            html += '<th>Item</th><th class="portal-orders-col-qty">Qty</th>';
            html += '<th class="portal-orders-col-amount">Unit Price</th>';
            html += '<th class="portal-orders-col-amount">Line Total</th>';
            html += '</tr></thead><tbody>';
            $.each(data.items, function(_, item) {
                html += '<tr>';
                html += '<td><div class="portal-orders-item-cell">';
                html += '<img src="' + esc(item.picture_url) + '" alt="" class="portal-orders-item-thumb">';
                html += '<span>' + esc(item.name) + '</span></div></td>';
                html += '<td class="portal-orders-col-qty">' + esc(item.orders_details_qty) + '</td>';
                html += '<td class="portal-orders-col-amount">' + money(item.price) + '</td>';
                html += '<td class="portal-orders-col-amount"><strong>' + money(item.line_total) + '</strong></td>';
                html += '</tr>';
            });
            html += '</tbody><tfoot><tr>';
            html += '<td colspan="3" class="portal-orders-detail-total-label">Order Grand Total</td>';
            html += '<td class="portal-orders-col-amount"><strong>' + money(data.total_amount) + '</strong></td>';
            html += '</tr></tfoot></table>';
        }

        html += '</div>';
        return html;
    }

    function refreshExpandPanel(orderId, data) {
        var $detailRow = $('#order-details-' + orderId);
        if (!$detailRow.length) {
            return;
        }
        $detailRow.find('td').first().html(renderExpandDetailPanel(data));
    }

    function fillEditModal(data) {
        activeOrderId = data.order_id;
        $('#editOrderId').text('#' + data.order_id);
        $('#editOrderStatus').text(data.status).attr('class', 'portal-orders-status portal-orders-status-inline');
        $('#editTransDate').text(data.trans_date);
        $('#editMenuDate').text(data.menu_date);
        $('#editRestaurant').text(data.restaurant);
        $('#editPayment').text(data.payment);
        $('#editShipping').text(data.shipping);
        $('#editFullName').text(data.fullname);
        $('#editEmail').text(data.email);
        $('#editContact').text(data.contact);
        $('#editCompany').text(data.company);
        $('#editDepartment').text(data.department);
        $('#editRemarks').text(data.remarks || '—');

        var $address = $('#editAddress');
        if (data.is_pending && !data.is_cancelled) {
            $address.prop('readonly', false).val(data.address);
        } else {
            $address.prop('readonly', true).val(data.address);
        }

        $('#editOrderItems').html(renderItemsTable(data, 'portal'));
        $('#editOrderMsg').hide().empty();
        $('#btnSaveOrderAddress').prop('disabled', !data.is_pending || data.is_cancelled);
    }

    function refreshRowSummary(orderId, data) {
        var $row = $('.portal-orders-row[data-order-id="' + orderId + '"]');
        if (!$row.length) {
            return;
        }
        $row.find('.portal-orders-col-amount strong').text(money(data.total_amount));
        $row.find('.portal-orders-col-items').text(data.item_count + ' item' + (data.item_count === 1 ? '' : 's'));
        $row.find('.portal-orders-status')
            .text(data.status)
            .attr('class', 'portal-orders-status ' + statusClass(data.status));

        var isPending = data.is_pending;
        $row.find('.portal-orders-action-edit').prop('disabled', false);
        $row.find('.portal-orders-action-cancel')
            .prop('disabled', !isPending)
            .toggleClass('is-disabled', !isPending);

        refreshExpandPanel(orderId, data);
    }

    function loadOrderDetail(orderId, callback) {
        $.ajax({
            type: 'POST',
            url: 'process.php',
            data: { function: 'getCustomerOrderDetail', order_id: orderId },
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
        $('.portal-orders-expand-btn').on('click', function() {
            var orderId = $(this).data('order-id');
            var $detailRow = $('#order-details-' + orderId);
            var isOpen = !$detailRow.prop('hidden');
            $detailRow.prop('hidden', isOpen);
            $(this).attr('aria-expanded', !isOpen);
            $(this).closest('.portal-orders-row').toggleClass('is-expanded', !isOpen);
        });

        $('.portal-orders-action-edit').on('click', function() {
            var orderId = $(this).data('order-id');
            loadOrderDetail(orderId, function(err, data) {
                if (err) {
                    alert(err);
                    return;
                }
                fillEditModal(data);
                openModal($('#orderEditModal'));
            });
        });

        $('.portal-orders-action-cancel').on('click', function() {
            if ($(this).prop('disabled')) {
                return;
            }
            activeOrderId = $(this).data('order-id');
            $('#cancelOrderId').text('#' + activeOrderId);
            $('#cancelReason').val('');
            $('#cancelOrderMsg').hide().empty();
            openModal($('#orderCancelModal'));
        });

        $('.portal-order-modal-close, .portal-order-modal-dismiss, .portal-order-modal-backdrop').on('click', function() {
            closeModal($(this).closest('.portal-order-modal'));
        });

        $('#btnSaveOrderAddress').on('click', function() {
            if (!activeOrderId) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving…');
            $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'updateCustomerOrderAddress',
                    order_id: activeOrderId,
                    address: $('#editAddress').val()
                },
                dataType: 'json',
                success: function(res) {
                    $btn.prop('disabled', false).text('Save Address');
                    if (res.validation_status === 'T') {
                        if (res.data) {
                            refreshRowSummary(activeOrderId, res.data);
                        }
                        closeModal($('#orderEditModal'));
                    } else {
                        showAlert($('#editOrderMsg'), res.msg || 'Could not save address.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Save Address');
                    showAlert($('#editOrderMsg'), 'Something went wrong. Please try again.');
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
                    function: 'deleteCustomerOrderItem',
                    order_id: activeOrderId,
                    detail_id: detailId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.validation_status === 'T' && res.data) {
                        fillEditModal(res.data);
                        refreshRowSummary(activeOrderId, res.data);
                    } else {
                        showAlert($('#editOrderMsg'), res.msg || 'Could not remove item.');
                    }
                },
                error: function() {
                    showAlert($('#editOrderMsg'), 'Something went wrong. Please try again.');
                }
            });
        });

        $('#btnConfirmCancelOrder').on('click', function() {
            var reason = $.trim($('#cancelReason').val());
            if (!activeOrderId) {
                return;
            }
            if (reason === '') {
                showAlert($('#cancelOrderMsg'), 'Please provide a cancellation reason.');
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).text('Cancelling…');
            $.ajax({
                type: 'POST',
                url: 'process.php',
                data: {
                    function: 'cancelCustomerOrder',
                    order_id: activeOrderId,
                    reason: reason
                },
                dataType: 'json',
                success: function(res) {
                    if (res.validation_status === 'T') {
                        closeModal($('#orderCancelModal'));
                        location.reload();
                    } else {
                        $btn.prop('disabled', false).text('Confirm Cancellation');
                        showAlert($('#cancelOrderMsg'), res.msg || 'Could not cancel order.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Confirm Cancellation');
                    showAlert($('#cancelOrderMsg'), 'Something went wrong. Please try again.');
                }
            });
        });
    });
})(jQuery);
