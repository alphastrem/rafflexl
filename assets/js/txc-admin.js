/**
 * TXC Admin Scripts
 */
(function($) {
    'use strict';

    // Gallery uploader
    $(document).on('click', '#txc-gallery-add', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Select Competition Images',
            multiple: true,
            library: { type: 'image' },
            button: { text: 'Add to Gallery' }
        });

        frame.on('select', function() {
            var selection = frame.state().get('selection');
            var ids = $('#txc_gallery_ids').val() ? $('#txc_gallery_ids').val().split(',').filter(Boolean) : [];

            selection.each(function(attachment) {
                var a = attachment.toJSON();
                ids.push(a.id);
                var thumb = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
                $('#txc-gallery-images').append(
                    '<div class="txc-gallery-item" data-id="' + a.id + '">' +
                    '<img src="' + thumb + '" />' +
                    '<button type="button" class="txc-gallery-remove">&times;</button>' +
                    '</div>'
                );
            });

            $('#txc_gallery_ids').val(ids.join(','));
        });

        frame.open();
    });

    // Remove gallery image
    $(document).on('click', '.txc-gallery-remove', function(e) {
        e.preventDefault();
        var item = $(this).closest('.txc-gallery-item');
        var id = item.data('id');
        var ids = $('#txc_gallery_ids').val().split(',').filter(Boolean);
        ids = ids.filter(function(i) { return parseInt(i) !== parseInt(id); });
        $('#txc_gallery_ids').val(ids.join(','));
        item.remove();
    });

    // Instant Wins - Pick Random Numbers
    $(document).on('click', '#txc-pick-instant-wins', function() {
        var compId = $(this).data('competition');
        var count = parseInt($('#txc_instant_wins_count').val(), 10);

        if (!count || count < 1) {
            alert('Please enter the number of instant wins first.');
            return;
        }

        var hasEntries = $('#txc-iw-entries table').length > 0;
        if (hasEntries && !confirm('This will replace all unclaimed instant win numbers. Are you sure?')) {
            return;
        }

        var $btn = $(this);
        var $status = $('#txc-iw-status');
        $btn.prop('disabled', true);
        $status.text('Picking random numbers...').css('color', '#2271b1');

        $.post(txcAdmin.ajaxUrl, {
            action: 'txc_generate_instant_wins',
            nonce: txcAdmin.nonce,
            competition_id: compId,
            count: count
        }, function(res) {
            if (res.success) {
                $('#txc-iw-entries').html(res.data.html);
                $status.text(res.data.count + ' instant win numbers picked!').css('color', '#00a32a');
                $btn.text('Re-pick Random Numbers');
            } else {
                $status.text(res.data.message || 'Error picking numbers.').css('color', '#d63638');
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            $status.text('Network error. Please try again.').css('color', '#d63638');
            $btn.prop('disabled', false);
        });
    });

    // Draw button
    $(document).on('click', '.txc-draw-btn', function() {
        var compId = $(this).data('competition');
        if (!confirm('Are you sure you want to draw this competition?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Drawing...');

        $.post(txcAdmin.ajaxUrl, {
            action: 'txc_manual_draw',
            nonce: txcAdmin.nonce,
            competition_id: compId
        }, function(res) {
            if (res.success) {
                alert('Draw complete! Winning ticket: #' + res.data.winning_ticket);
                location.reload();
            } else {
                alert('Draw failed: ' + (res.data ? res.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Draw Now');
            }
        }).fail(function() {
            alert('Network error');
            $btn.prop('disabled', false).text('Draw Now');
        });
    });

    // Redraw button - open modal
    var redrawCompId = 0;
    $(document).on('click', '.txc-redraw-btn', function() {
        redrawCompId = $(this).data('competition');
        $('#txc-redraw-reason').val('');
        $('#txc-redraw-modal').show();
    });

    $(document).on('click', '#txc-redraw-cancel', function() {
        $('#txc-redraw-modal').hide();
        redrawCompId = 0;
    });

    $(document).on('click', '#txc-redraw-confirm', function() {
        var reason = $('#txc-redraw-reason').val().trim();
        if (!reason) {
            alert('A public reason is required for a redraw.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Redrawing...');

        $.post(txcAdmin.ajaxUrl, {
            action: 'txc_force_redraw',
            nonce: txcAdmin.nonce,
            competition_id: redrawCompId,
            reason: reason
        }, function(res) {
            if (res.success) {
                alert('Redraw complete! New winning ticket: #' + res.data.winning_ticket);
                location.reload();
            } else {
                alert('Redraw failed: ' + (res.data ? res.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Confirm Redraw');
            }
        }).fail(function() {
            alert('Network error');
            $btn.prop('disabled', false).text('Confirm Redraw');
        });
    });

    // View draw detail
    $(document).on('click', '.txc-view-draw-btn', function() {
        var data = $(this).data('draw');
        var html = '<table class="widefat">';
        html += '<thead><tr><th>Roll</th><th>Ticket</th><th>Result</th></tr></thead><tbody>';

        if (data.rolls) {
            data.rolls.forEach(function(roll) {
                var resultClass = roll.result === 'winner' ? 'color:green;font-weight:bold;' : 'color:red;';
                html += '<tr>';
                html += '<td>' + roll.roll_number + '</td>';
                html += '<td>#' + roll.ticket + '</td>';
                html += '<td style="' + resultClass + '">' + (roll.result === 'winner' ? 'WINNER' : roll.message || 'Rejected') + '</td>';
                html += '</tr>';
            });
        }

        html += '</tbody></table>';
        html += '<p><strong>Seed Hash:</strong> <code>' + (data.seed_hash || 'N/A') + '</code></p>';
        html += '<p><strong>Status:</strong> ' + (data.status || 'N/A') + '</p>';

        if (data.forced_redraw_reason) {
            html += '<p><strong>Redraw Reason:</strong> ' + data.forced_redraw_reason + '</p>';
        }

        $('#txc-draw-detail-content').html(html);
        $('#txc-draw-detail-modal').show();
    });

    $(document).on('click', '#txc-draw-detail-close', function() {
        $('#txc-draw-detail-modal').hide();
    });

})(jQuery);
