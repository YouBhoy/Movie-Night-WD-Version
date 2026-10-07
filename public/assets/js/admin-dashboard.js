// Filter existing rows in place so clearing a no-match search restores them.
let searchTimeout;
let allRegistrations = [];
let noResultsRow;

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('searchInput');
    allRegistrations = Array.from(document.querySelectorAll('.registration-row'));
    noResultsRow = document.createElement('tr');
    noResultsRow.hidden = true;
    const cell = document.createElement('td');
    cell.colSpan = 7;
    cell.className = 'no-results';
    noResultsRow.appendChild(cell);
    document.getElementById('registrationsTableBody').appendChild(noResultsRow);
    input.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => performSearch(input.value.trim()), 200);
    });
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            input.focus();
        }
        if (event.key === 'Escape' && document.activeElement === input) clearSearch();
    });
});

function highlightSearchTerms(rows, query) {
    for (const row of rows) {
        for (const element of row.querySelectorAll('.emp-number, .staff-name')) {
            const original = element.dataset.originalText ?? element.textContent;
            element.dataset.originalText = original;
            element.replaceChildren();
            const needle = query.toLowerCase();
            const haystack = original.toLowerCase();
            let cursor = 0;
            let match = needle ? haystack.indexOf(needle) : -1;
            while (match !== -1) {
                element.appendChild(document.createTextNode(original.slice(cursor, match)));
                const mark = document.createElement('mark');
                mark.textContent = original.slice(match, match + query.length);
                element.appendChild(mark);
                cursor = match + query.length;
                match = haystack.indexOf(needle, cursor);
            }
            element.appendChild(document.createTextNode(original.slice(cursor)));
        }
    }
}

function performSearch(query) {
    const needle = query.toLowerCase();
    const matches = allRegistrations.filter(row =>
        (row.dataset.empNumber ?? '').toLowerCase().includes(needle) ||
        (row.dataset.staffName ?? '').toLowerCase().includes(needle));
    const matchingRows = new Set(matches);
    allRegistrations.forEach(row => { row.style.display = matchingRows.has(row) ? '' : 'none'; });
    highlightSearchTerms(allRegistrations, query);
    noResultsRow.hidden = !query || matches.length > 0;
    noResultsRow.firstElementChild.textContent = 'No registrations found for "' + query + '". Try an employee number or name.';
    document.getElementById('resultCount').textContent = matches.length;
    document.getElementById('searchTerm').textContent = query;
    document.getElementById('searchResultsInfo').style.display = query ? 'block' : 'none';
    document.getElementById('loadingIndicator').style.display = 'none';
}

function showAllRegistrations() {
    clearTimeout(searchTimeout);
    performSearch('');
}
function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    showAllRegistrations();
    input.focus();
}
function refreshRegistrations() {
    window.location.reload();
}
