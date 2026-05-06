/* global jQuery, KolaiLogs */
(function ($) {
    'use strict';

    if (typeof KolaiLogs === 'undefined') {
        return;
    }

    var state = {
        page: 1,
        perPage: 100,
        total: 0,
        autoTimer: null
    };

    var $tbody     = $('#kolai-logs-tbody');
    var $level     = $('#kolai-logs-filter-level');
    var $context   = $('#kolai-logs-filter-context');
    var $search    = $('#kolai-logs-filter-search');
    var $refresh   = $('#kolai-logs-refresh');
    var $clearBtn  = $('#kolai-logs-clear');
    var $autoBtn   = $('#kolai-logs-auto');
    var $prev      = $('#kolai-logs-prev');
    var $next      = $('#kolai-logs-next');
    var $pageInfo  = $('#kolai-logs-pageinfo');
    var $countBadge = $('.kolai-logs-count');

    function escapeHtml(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatJson(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }
        try {
            return JSON.stringify(value, null, 2);
        } catch (e) {
            return String(value);
        }
    }

    function renderRows(rows) {
        if (!rows || !rows.length) {
            $tbody.html(
                '<tr><td colspan="7" class="kolai-logs-empty">' +
                escapeHtml(KolaiLogs.i18n.noLogs) +
                '</td></tr>'
            );
            return;
        }

        var html = rows.map(function (row) {
            var dataPreview = row.data ? '<button type="button" class="button-link kolai-log-toggle">▶ data</button>' : '';
            var dataBlock   = row.data
                ? '<pre class="kolai-log-data" hidden>' + escapeHtml(formatJson(row.data)) + '</pre>'
                : '';
            var requestId   = row.request_id ? '<div class="kolai-log-rid">rid: ' + escapeHtml(row.request_id) + '</div>' : '';
            var duration    = row.duration_ms !== null ? escapeHtml(row.duration_ms) + ' ms' : '';

            return ''
                + '<tr class="kolai-log-row kolai-log-row--' + escapeHtml(row.level) + '">'
                +   '<td class="kolai-log-time">' + escapeHtml(row.created_at) + '</td>'
                +   '<td><span class="kolai-log-level kolai-log-level--' + escapeHtml(row.level) + '">' + escapeHtml(row.level) + '</span></td>'
                +   '<td>' + escapeHtml(row.context) + '</td>'
                +   '<td>' + escapeHtml(row.method || '') + '</td>'
                +   '<td class="kolai-log-route">' + escapeHtml(row.route || '') + requestId + '</td>'
                +   '<td>'
                +     '<div class="kolai-log-message">' + escapeHtml(row.message) + '</div>'
                +     dataPreview
                +     dataBlock
                +   '</td>'
                +   '<td>' + duration + '</td>'
                + '</tr>';
        }).join('');

        $tbody.html(html);
    }

    function updatePagination() {
        var totalPages = Math.max(1, Math.ceil(state.total / state.perPage));
        if (state.page > totalPages) {
            state.page = totalPages;
        }
        $pageInfo.text(state.page + ' / ' + totalPages + '  (' + state.total + ' kayıt)');
        $prev.prop('disabled', state.page <= 1);
        $next.prop('disabled', state.page >= totalPages);
        $countBadge.text('(' + state.total + ')');
    }

    function refreshContextOptions(contexts) {
        var current = $context.val();
        var options = '<option value="">' + escapeHtml($context.find('option[value=""]').text()) + '</option>';
        (contexts || []).forEach(function (ctx) {
            options += '<option value="' + escapeHtml(ctx) + '">' + escapeHtml(ctx) + '</option>';
        });
        $context.html(options);
        if (current && (contexts || []).indexOf(current) !== -1) {
            $context.val(current);
        }
    }

    function fetchLogs() {
        $tbody.html(
            '<tr><td colspan="7" class="kolai-logs-loading">' +
            escapeHtml('Yükleniyor…') +
            '</td></tr>'
        );

        $.post(KolaiLogs.ajaxUrl, {
            action:     'kolai_logs_fetch',
            nonce:      KolaiLogs.nonce,
            level:      $level.val(),
            context:    $context.val(),
            search:     $search.val(),
            limit:      state.perPage,
            offset:     (state.page - 1) * state.perPage
        }).done(function (resp) {
            if (!resp || !resp.success) {
                $tbody.html('<tr><td colspan="7" class="kolai-logs-error">' + escapeHtml(KolaiLogs.i18n.fetchError) + '</td></tr>');
                return;
            }
            state.total = resp.data.total || 0;
            renderRows(resp.data.rows);
            refreshContextOptions(resp.data.contexts);
            updatePagination();
        }).fail(function () {
            $tbody.html('<tr><td colspan="7" class="kolai-logs-error">' + escapeHtml(KolaiLogs.i18n.fetchError) + '</td></tr>');
        });
    }

    function clearLogs() {
        if (!window.confirm(KolaiLogs.i18n.confirmClear)) {
            return;
        }

        $clearBtn.prop('disabled', true);

        $.post(KolaiLogs.ajaxUrl, {
            action: 'kolai_logs_clear',
            nonce:  KolaiLogs.nonce
        }).done(function (resp) {
            if (!resp || !resp.success) {
                window.alert(KolaiLogs.i18n.clearError);
                return;
            }
            state.page = 1;
            fetchLogs();
        }).fail(function () {
            window.alert(KolaiLogs.i18n.clearError);
        }).always(function () {
            $clearBtn.prop('disabled', false);
        });
    }

    function toggleAutoRefresh() {
        var on = $autoBtn.attr('data-on') === '1';
        if (on) {
            window.clearInterval(state.autoTimer);
            state.autoTimer = null;
            $autoBtn.attr('data-on', '0').text('Otomatik yenileme: kapalı');
        } else {
            state.autoTimer = window.setInterval(fetchLogs, 5000);
            $autoBtn.attr('data-on', '1').text('Otomatik yenileme: 5 sn');
        }
    }

    // Wire events
    $refresh.on('click', function () {
        state.page = 1;
        fetchLogs();
    });

    $level.on('change', function () {
        state.page = 1;
        fetchLogs();
    });

    $context.on('change', function () {
        state.page = 1;
        fetchLogs();
    });

    var searchTimer = null;
    $search.on('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            state.page = 1;
            fetchLogs();
        }, 300);
    });

    $clearBtn.on('click', clearLogs);
    $autoBtn.on('click', toggleAutoRefresh);

    $prev.on('click', function () {
        if (state.page > 1) {
            state.page -= 1;
            fetchLogs();
        }
    });
    $next.on('click', function () {
        state.page += 1;
        fetchLogs();
    });

    // Toggle expanded data block
    $tbody.on('click', '.kolai-log-toggle', function () {
        var $btn = $(this);
        var $pre = $btn.next('pre.kolai-log-data');
        if (!$pre.length) {
            return;
        }
        var open = !$pre.prop('hidden');
        $pre.prop('hidden', open);
        $btn.text((open ? '▶' : '▼') + ' data');
    });

    // Initial load
    fetchLogs();
})(jQuery);
