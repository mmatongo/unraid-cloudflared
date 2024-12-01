<?php header('Content-Type: application/javascript'); ?>
$(function() {
    $('#cloudflared_settings').submit(function(event) {
        event.preventDefault();
        $('#btnApply').prop('disabled', true);

        let formData = $(this).serializeArray();
        let config = {};
        formData.forEach(function(item) {
            config[item.name] = item.value;
        });

        $.post('/plugins/cloudflared/include/ajax/service_handler.php', {
            action: 'apply',
            config: config
        }, function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                $('#btnApply').prop('disabled', false);
            }
        }, 'json')
        .fail(function(jqXHR, textStatus, errorThrown) {
            $('#btnApply').prop('disabled', false);
        });
    });
    $('#cloudflared_settings input, #cloudflared_settings select').on('input change', function() {
        $('#btnApply').prop('disabled', false);
    });

    const initialState = $('#service_toggle').val() === 'enabled';
    $('#tunnel_settings').toggle(initialState);
});

$('#service_toggle').change(function() {
    $('#tunnel_settings').slideToggle(400);
    $('#btnApply').prop('disabled', false);
});

let autoRefreshInterval;

function refreshLogs() {
    $.get('/plugins/cloudflared/include/ajax/fetch_logs.php')
        .done(function(data) {
            if (data) {
                $('#logContent').html(data);
            } else {
                $('#logContent').html('<div class="log-line">No logs available</div>');
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            $('#logContent').html('<div class="log-line error">Failed to fetch logs: ' + errorThrown + '</div>');
        });
}

function clearLogs() {
    $.post('/plugins/cloudflared/include/ajax/log_clear.php')
        .done(function(response) {
            $('#logContent').empty();
            $('#logContent').append('<div class="log-line">' + response + '</div>');
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            $('#logContent').html('<div class="log-line error">Failed to clear logs: ' + errorThrown + '</div>');
        });
}

function toggleAutoRefresh() {
    if ($('#autoRefresh').is(':checked')) {
        localStorage.setItem('cloudflared_autorefresh', 'true');
        refreshLogs();
        autoRefreshInterval = setInterval(function() {
            try {
                refreshLogs();
            } catch (error) {
                console.error('Auto-refresh error:', error);
                clearInterval(autoRefreshInterval);
                $('#autoRefresh').prop('checked', false);
            }
        }, 5000);
    } else {
        localStorage.setItem('cloudflared_autorefresh', 'false');
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
}

$(window).on('unload', function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});

$(document).ready(function() {
    const autoRefreshEnabled = localStorage.getItem('cloudflared_autorefresh') === 'true';
    $('#autoRefresh').prop('checked', autoRefreshEnabled);
    if (autoRefreshEnabled) {
        toggleAutoRefresh();
    }
});
