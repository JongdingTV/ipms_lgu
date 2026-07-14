// ============================================================
// assets/js/pagination.js — shared pagination UI + debounce helper.
// Consumes the {data, total, page, per_page, last_page} shape returned by
// includes/Pagination.php's paginate(). Caller supplies a container element
// (e.g. <div class="pagination-wrap"></div>) that this fully re-renders.
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

function paginationRange(current, last) {
    var delta = 1;
    var range = [];
    var withDots = [];
    var lastAdded = null;

    for (var i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
            range.push(i);
        }
    }

    range.forEach(function (i) {
        if (lastAdded !== null) {
            if (i - lastAdded === 2) {
                withDots.push(lastAdded + 1);
            } else if (i - lastAdded > 2) {
                withDots.push('...');
            }
        }
        withDots.push(i);
        lastAdded = i;
    });

    return withDots;
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

    if (total === 0) {
        container.innerHTML = '<div class="pagination-info">No results found.</div>';
        return;
    }

    var start = (page - 1) * perPage + 1;
    var end = Math.min(page * perPage, total);
    var pages = paginationRange(page, lastPage);

    var html = '<div class="pagination-info">Showing ' + start + '–' + end + ' of ' + total + '</div>';
    html += '<div class="pagination">';
    html += '<button type="button" class="pagination-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Previous page">&laquo;</button>';

    pages.forEach(function (p) {
        if (p === '...') {
            html += '<span class="pagination-ellipsis">&hellip;</span>';
        } else {
            html += '<button type="button" class="pagination-btn' + (p === page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
    });

    html += '<button type="button" class="pagination-btn" data-page="' + (page + 1) + '"' + (page >= lastPage ? ' disabled' : '') + ' aria-label="Next page">&raquo;</button>';
    html += '</div>';

    container.innerHTML = html;

    container.querySelectorAll('.pagination-btn:not([disabled])').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetPage = parseInt(btn.getAttribute('data-page'), 10);
            if (targetPage >= 1 && targetPage <= lastPage && targetPage !== page) {
                onPageChange(targetPage);
            }
        });
    });
}
