// ============================================================
// assets/js/pagination.js — shared pagination UI + debounce helper.
// Consumes the {data, total, page, per_page, last_page} shape returned by
// includes/Pagination.php's paginate(). Caller supplies a container element
// (e.g. <div class="pagination-wrap"></div>) that this fully re-renders.
//
// Gmail-style pager: a "1–10 of 34" range readout plus round prev/next
// arrows in a pill, matching the citizen portal's list-pager. The old
// numbered-buttons layout was retired with the staff redesign.
// ============================================================

function debounce(fn, delay) {
    delay = delay || 300;
    var timer = null;
    return function () {
        var context = this;
        var args = arguments;
        clearTimeout(timer);
        timer = setTimeout(function () {
            fn.apply(context, args);
        }, delay);
    };
}

// ── Client-side lists (full array already in memory) ──
// Ported from the citizen portal's list controller: search + pager over a
// local array, re-rendered instantly. For server-paginated tables keep
// using renderPagination; this is for tables whose API returns everything.
// cfg: {bodyId, searchId, pagerId, columns?, pageSize?, emptyText,
//       searchText(item), rowHtml(item)}
var clientListStates = {};

function initClientList(key, cfg) {
    clientListStates[key] = { cfg: cfg, data: [], page: 1 };
    var search = document.getElementById(cfg.searchId);
    if (search) {
        search.addEventListener('input', debounce(function () {
            clientListStates[key].page = 1;
            renderClientList(key);
        }, 200));
    }
}

function setClientListData(key, data) {
    var state = clientListStates[key];
    if (!state) return;
    state.data = Array.isArray(data) ? data : [];
    state.page = 1;
    renderClientList(key);
}

function renderClientList(key) {
    var state = clientListStates[key];
    if (!state) return;
    var cfg = state.cfg;
    var body = document.getElementById(cfg.bodyId);
    if (!body) return;

    var query = (document.getElementById(cfg.searchId) || { value: '' }).value.trim().toLowerCase();
    var filtered = state.data.filter(function (item) {
        return !query || cfg.searchText(item).toLowerCase().indexOf(query) !== -1;
    });

    var pageSize = cfg.pageSize || 10;
    var total = filtered.length;
    var lastPage = Math.max(1, Math.ceil(total / pageSize));
    state.page = Math.min(Math.max(1, state.page), lastPage);
    var start = (state.page - 1) * pageSize;
    var end = Math.min(start + pageSize, total);

    var emptyMsg = state.data.length ? 'No results match your search.' : cfg.emptyText;
    body.innerHTML = total
        ? filtered.slice(start, end).map(cfg.rowHtml).join('')
        : (cfg.columns
            ? '<tr><td colspan="' + cfg.columns + '" class="table-empty">' + emptyMsg + '</td></tr>'
            : '<p class="empty-state">' + emptyMsg + '</p>');

    renderPagination(document.getElementById(cfg.pagerId), {
        page: state.page, lastPage: lastPage, total: total, perPage: pageSize,
        onPageChange: function (nextPage) {
            state.page = nextPage;
            renderClientList(key);
        },
    });
}

// Standard toolbar: pill search box + a pagination-wrap the pager renders into.
function listToolbarHtml(searchId, placeholder, pagerId) {
    return '<div class="list-toolbar">' +
        '<label class="list-search">' +
        '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>' +
        '<input type="text" id="' + searchId + '" placeholder="' + placeholder + '">' +
        '</label>' +
        '<div class="pagination-wrap" id="' + pagerId + '" style="margin:0 0 0 auto;"></div>' +
        '</div>';
}

/** options: {page, lastPage, total, perPage, onPageChange(nextPage)} */
function renderPagination(container, options) {
    if (!container) {
        return;
    }

    var page = options.page || 1;
    var lastPage = Math.max(1, options.lastPage || 1);
    var total = options.total || 0;
    var perPage = options.perPage || 10;
    var onPageChange = options.onPageChange || function () {};

    var start = total === 0 ? 0 : (page - 1) * perPage + 1;
    var end = Math.min(page * perPage, total);

    var prevSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
    var nextSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>';

    var html = '<div class="list-pager">';
    html += '<span class="list-pager-info">' + (total === 0 ? '0 of 0' : start + '–' + end + ' of ' + total) + '</span>';
    html += '<button type="button" class="list-pager-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Previous page">' + prevSvg + '</button>';
    html += '<button type="button" class="list-pager-btn" data-page="' + (page + 1) + '"' + (page >= lastPage || total === 0 ? ' disabled' : '') + ' aria-label="Next page">' + nextSvg + '</button>';
    html += '</div>';

    container.innerHTML = html;

    container.querySelectorAll('.list-pager-btn:not([disabled])').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetPage = parseInt(btn.getAttribute('data-page'), 10);
            if (targetPage >= 1 && targetPage <= lastPage && targetPage !== page) {
                onPageChange(targetPage);
            }
        });
    });
}
