(function() {
        function parseValue(value, type) {
                if (type === 'numeric') {
                        var numeric = parseFloat(String(value).replace(/[^0-9.-]/g, ''));
                        return isNaN(numeric) ? 0 : numeric;
                }

                return String(value).toLowerCase();
        }

        function sortTable(table, columnIndex, type, direction) {
                var tbody = table.tBodies[0];
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

                rows.sort(function(a, b) {
                        var aText = a.cells[columnIndex] ? a.cells[columnIndex].textContent.trim() : '';
                        var bText = b.cells[columnIndex] ? b.cells[columnIndex].textContent.trim() : '';
                        var aValue = parseValue(aText, type);
                        var bValue = parseValue(bText, type);

                        if (aValue < bValue) {
                                return direction === 'asc' ? -1 : 1;
                        }
                        if (aValue > bValue) {
                                return direction === 'asc' ? 1 : -1;
                        }
                        return 0;
                });

                rows.forEach(function(row) {
                        tbody.appendChild(row);
                });
        }

        function handleHeaderClick(event) {
                var th = event.currentTarget;
                var table = th.closest('table');
                var headers = Array.prototype.slice.call(table.querySelectorAll('th'));
                var index = headers.indexOf(th);
                var type = th.getAttribute('data-type') || 'text';
                var currentSort = th.getAttribute('data-sort');
                var nextSort = currentSort === 'asc' ? 'desc' : 'asc';

                headers.forEach(function(header) {
                        header.removeAttribute('data-sort');
                });

                th.setAttribute('data-sort', nextSort);
                sortTable(table, index, type, nextSort);
        }

        function init() {
                var tables = document.querySelectorAll('[data-plem-dashboard]');
                tables.forEach(function(table) {
                        var headers = table.querySelectorAll('thead th');
                        headers.forEach(function(th) {
                                th.addEventListener('click', handleHeaderClick);
                        });
                });
        }

        if ('complete' === document.readyState || 'interactive' === document.readyState) {
                init();
        } else {
                document.addEventListener('DOMContentLoaded', init);
        }
})();
