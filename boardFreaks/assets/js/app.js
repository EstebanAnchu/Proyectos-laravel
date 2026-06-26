(function () {
    const toggle = document.querySelector('#sidebarToggle');
    const backdrop = document.querySelector('#sidebarBackdrop');
    const desktopQuery = window.matchMedia('(min-width: 992px)');

    function closeMobileSidebar() {
        document.body.classList.remove('sidebar-open');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (desktopQuery.matches) {
                document.body.classList.toggle('sidebar-collapsed');
                return;
            }

            document.body.classList.toggle('sidebar-open');
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMobileSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    desktopQuery.addEventListener('change', function () {
        closeMobileSidebar();
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm || 'Confirmar accion?')) {
                event.preventDefault();
            }
        });
    });

    function setField(modal, field, value) {
        const input = modal.querySelector('[data-field="' + field + '"]');
        if (input) {
            input.value = value || '';
        }
    }

    function resetModalForm(modal) {
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        setField(modal, 'id', '');
    }

    const userModal = document.querySelector('#userModal');
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function (event) {
            resetModalForm(userModal);
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('btn-edit-user')) {
                return;
            }
            setField(userModal, 'id', button.dataset.id);
            setField(userModal, 'name', button.dataset.name);
            setField(userModal, 'email', button.dataset.email);
            setField(userModal, 'accountType', button.dataset.accountType);
        });
    }

    const clientModal = document.querySelector('#clientModal');
    if (clientModal) {
        clientModal.addEventListener('show.bs.modal', function (event) {
            resetModalForm(clientModal);
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('btn-edit-client')) {
                return;
            }
            setField(clientModal, 'id', button.dataset.id);
            setField(clientModal, 'name', button.dataset.name);
            setField(clientModal, 'email', button.dataset.email);
            setField(clientModal, 'phone', button.dataset.phone);
            setField(clientModal, 'membershipLevel', button.dataset.membershipLevel);
            setField(clientModal, 'status', button.dataset.status);
        });
    }

    const productModal = document.querySelector('#productModal');
    if (productModal) {
        productModal.addEventListener('show.bs.modal', function (event) {
            resetModalForm(productModal);
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('btn-edit-product')) {
                return;
            }
            setField(productModal, 'id', button.dataset.id);
            setField(productModal, 'name', button.dataset.name);
            setField(productModal, 'category', button.dataset.category);
            setField(productModal, 'description', button.dataset.description);
            setField(productModal, 'imageUrl', button.dataset.imageUrl);
            setField(productModal, 'price', button.dataset.price);
            setField(productModal, 'stock', button.dataset.stock);
            setField(productModal, 'status', button.dataset.status);
        });
    }

    function setupTable(table) {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const pageSize = Number(table.dataset.pageSize || 5);
        const searchInput = document.querySelector('[data-table-search="' + table.id + '"]');
        const pagination = document.querySelector('[data-table-pagination="' + table.id + '"]');
        const empty = document.querySelector('[data-table-empty="' + table.id + '"]');
        const counter = document.querySelector('[data-table-counter="' + table.id + '"]');
        let currentPage = 1;
        let filteredRows = rows;

        function render() {
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            filteredRows = rows.filter(function (row) {
                return row.innerText.toLowerCase().includes(query);
            });

            const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

            rows.forEach(function (row) {
                row.classList.add('d-none');
            });
            filteredRows.slice(start, end).forEach(function (row) {
                row.classList.remove('d-none');
            });

            if (empty) {
                empty.classList.toggle('is-visible', filteredRows.length === 0);
            }
            if (counter) {
                counter.textContent = filteredRows.length + ' registro' + (filteredRows.length === 1 ? '' : 's');
            }
            if (!pagination) {
                return;
            }

            pagination.innerHTML = '';
            if (filteredRows.length <= pageSize) {
                return;
            }

            const list = document.createElement('ul');
            list.className = 'pagination pagination-sm mb-0';

            function addButton(label, page, disabled, active) {
                const item = document.createElement('li');
                item.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                const button = document.createElement('button');
                button.className = 'page-link';
                button.type = 'button';
                button.textContent = label;
                button.addEventListener('click', function () {
                    if (disabled) {
                        return;
                    }
                    currentPage = page;
                    render();
                });
                item.appendChild(button);
                list.appendChild(item);
            }

            addButton('Anterior', Math.max(1, currentPage - 1), currentPage === 1, false);
            for (let page = 1; page <= pageCount; page += 1) {
                addButton(String(page), page, false, page === currentPage);
            }
            addButton('Siguiente', Math.min(pageCount, currentPage + 1), currentPage === pageCount, false);
            pagination.appendChild(list);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                render();
            });
        }
        render();
    }

    document.querySelectorAll('table[data-page-size]').forEach(setupTable);

    function setupCatalog() {
        const grid = document.querySelector('[data-catalog-grid]');
        if (!grid) {
            return;
        }

        const cards = Array.from(grid.querySelectorAll('[data-catalog-card]'));
        const pageSize = Number(grid.dataset.pageSize || 6);
        const searchInput = document.querySelector('[data-catalog-search]');
        const priceInput = document.querySelector('[data-price-filter]');
        const priceOutput = document.querySelector('[data-price-output]');
        const categoryButtons = Array.from(document.querySelectorAll('[data-category-filter]'));
        const pagination = document.querySelector('[data-catalog-pagination]');
        const counter = document.querySelector('[data-catalog-counter]');
        const empty = document.querySelector('[data-catalog-empty]');
        let activeCategory = 'all';
        let currentPage = 1;

        function formatMoney(value) {
            return '$' + Number(value).toFixed(2);
        }

        function render() {
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const maxPrice = priceInput ? Number(priceInput.value) : Number.POSITIVE_INFINITY;

            if (priceOutput && priceInput) {
                priceOutput.textContent = formatMoney(maxPrice);
            }

            const filteredCards = cards.filter(function (card) {
                const categoryMatch = activeCategory === 'all' || card.dataset.category === activeCategory;
                const priceMatch = Number(card.dataset.price || 0) <= maxPrice;
                const searchMatch = (card.dataset.search || '').includes(query);
                return categoryMatch && priceMatch && searchMatch;
            });

            const pageCount = Math.max(1, Math.ceil(filteredCards.length / pageSize));
            currentPage = Math.min(currentPage, pageCount);
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

            cards.forEach(function (card) {
                card.classList.add('d-none');
            });
            filteredCards.slice(start, end).forEach(function (card) {
                card.classList.remove('d-none');
            });

            if (counter) {
                counter.textContent = filteredCards.length + ' juego' + (filteredCards.length === 1 ? '' : 's');
            }
            if (empty) {
                empty.classList.toggle('is-visible', filteredCards.length === 0);
            }
            if (!pagination) {
                return;
            }

            pagination.innerHTML = '';
            if (filteredCards.length <= pageSize) {
                return;
            }

            const list = document.createElement('ul');
            list.className = 'pagination pagination-sm mb-0';

            function addButton(label, page, disabled, active) {
                const item = document.createElement('li');
                item.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                const button = document.createElement('button');
                button.className = 'page-link';
                button.type = 'button';
                button.textContent = label;
                button.addEventListener('click', function () {
                    if (disabled) {
                        return;
                    }
                    currentPage = page;
                    render();
                    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                item.appendChild(button);
                list.appendChild(item);
            }

            addButton('Anterior', Math.max(1, currentPage - 1), currentPage === 1, false);
            for (let page = 1; page <= pageCount; page += 1) {
                addButton(String(page), page, false, page === currentPage);
            }
            addButton('Siguiente', Math.min(pageCount, currentPage + 1), currentPage === pageCount, false);
            pagination.appendChild(list);
        }

        categoryButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeCategory = button.dataset.categoryFilter || 'all';
                categoryButtons.forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                currentPage = 1;
                render();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                currentPage = 1;
                render();
            });
        }
        if (priceInput) {
            priceInput.addEventListener('input', function () {
                currentPage = 1;
                render();
            });
        }

        render();
    }

    setupCatalog();
})();
