const sidebar = document.getElementById("sidebar");
const openSidebar = document.getElementById("openSidebar");
const closeSidebar = document.getElementById("closeSidebar");
const overlay = document.getElementById("overlay");

const categoryToggle = document.getElementById("categoryToggle");
const categoryMenu = document.getElementById("categoryMenu");
const dropdownArrow = document.getElementById("dropdownArrow");

const productModal = document.getElementById("productModal");
const openProductModal = document.getElementById("openProductModal");
const closeProductModal = document.getElementById("closeProductModal");

const editProductModal = document.getElementById("editProductModal");
const closeEditProductModal = document.getElementById("closeEditProductModal");

const stockModal = document.getElementById("stockModal");
const closeStockModal = document.getElementById("closeStockModal");

const categoryModal = document.getElementById("categoryModal");
const openCategoryModal = document.getElementById("openCategoryModal");
const closeCategoryModal = document.getElementById("closeCategoryModal");

const productImageInput = document.getElementById("product_image");
const previewImage = document.getElementById("previewImage");

const editProductImageInput = document.getElementById("edit_product_image");
const editPreviewImage = document.getElementById("editPreviewImage");

const stockMovementType = document.getElementById("stock_movement_type");
const onOrderOwnerFields = document.getElementById("onOrderOwnerFields");
const deductOnOrderWrap = document.getElementById("deductOnOrderWrap");

let activeRequestController = null;

function getDynamicArea() {
    return document.getElementById("inventoryDynamicArea");
}

function getFilterForm() {
    return document.getElementById("inventoryFilterForm");
}

function getSearchInput() {
    return document.getElementById("inventorySearchInput");
}

function getCategoryFilter() {
    return document.getElementById("inventoryCategoryFilter");
}

function getStatusFilter() {
    return document.getElementById("inventoryStatusFilter");
}

function openSidebarMenu() {
    if (sidebar) sidebar.classList.add("active");
    if (overlay) overlay.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeSidebarMenu() {
    if (sidebar) sidebar.classList.remove("active");
    if (overlay) overlay.classList.remove("show");
    document.body.style.overflow = "";
}

window.openSidebarMenu = openSidebarMenu;
window.closeSidebarMenu = closeSidebarMenu;

function openModal(modal) {
    if (!modal) return;
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("show");
    document.body.style.overflow = "";
}

function closeAllMenus(exceptMenu = null) {
    document.querySelectorAll("[data-action-menu].show").forEach((menu) => {
        if (menu !== exceptMenu) {
            menu.classList.remove("show");
        }
    });
}

function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.value = value ?? "";
    }
}

function toggleStockOrderFields() {
    if (!stockMovementType) return;

    const movement = stockMovementType.value;

    if (onOrderOwnerFields) {
        onOrderOwnerFields.style.display = "block";
    }

    if (deductOnOrderWrap) {
        deductOnOrderWrap.style.display = movement === "stock_in" ? "block" : "none";
    }
}

function showDynamicLoading() {
    const dynamicArea = getDynamicArea();
    if (!dynamicArea) return;
    dynamicArea.classList.add("is-loading");
}

function hideDynamicLoading() {
    const dynamicArea = getDynamicArea();
    if (!dynamicArea) return;
    dynamicArea.classList.remove("is-loading");
}

function syncBrowserUrl(url) {
    window.history.replaceState({}, "", url);
}

async function refreshInventoryContent(url) {
    const dynamicArea = getDynamicArea();
    if (!dynamicArea) {
        window.location.href = url;
        return;
    }

    if (activeRequestController) {
        activeRequestController.abort();
    }

    activeRequestController = new AbortController();
    showDynamicLoading();
    closeAllMenus();

    try {
        const response = await fetch(url, {
            method: "GET",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            signal: activeRequestController.signal
        });

        if (!response.ok) {
            throw new Error("Failed to refresh inventory content.");
        }

        const html = await response.text();
        const parser = new DOMParser();
        const nextDocument = parser.parseFromString(html, "text/html");
        const nextDynamicArea = nextDocument.getElementById("inventoryDynamicArea");

        if (!nextDynamicArea) {
            window.location.href = url;
            return;
        }

        dynamicArea.innerHTML = nextDynamicArea.innerHTML;
        syncBrowserUrl(url);
        initDynamicFilterControls();
    } catch (error) {
        if (error.name !== "AbortError") {
            window.location.href = url;
        }
    } finally {
        hideDynamicLoading();
    }
}

function submitFiltersInstantly() {
    const filterForm = getFilterForm();
    if (!filterForm) return;

    const formData = new FormData(filterForm);
    const query = new URLSearchParams();

    for (const [key, value] of formData.entries()) {
        if (String(value).trim() !== "" && String(value) !== "0") {
            query.set(key, value);
        }
    }

    const queryString = query.toString();
    const url = queryString
        ? `${filterForm.action}?${queryString}`
        : filterForm.action;

    refreshInventoryContent(url);
}

function initDynamicFilterControls() {
    const inventorySearchInput = getSearchInput();
    const inventoryCategoryFilter = getCategoryFilter();
    const inventoryStatusFilter = getStatusFilter();

    if (inventorySearchInput && !inventorySearchInput.dataset.enterBound) {
        inventorySearchInput.dataset.enterBound = "1";

        inventorySearchInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                submitFiltersInstantly();
            }
        });
    }

    if (inventoryCategoryFilter && !inventoryCategoryFilter.dataset.instantBound) {
        inventoryCategoryFilter.dataset.instantBound = "1";
        inventoryCategoryFilter.addEventListener("change", () => {
            submitFiltersInstantly();
        });
    }

    if (inventoryStatusFilter && !inventoryStatusFilter.dataset.instantBound) {
        inventoryStatusFilter.dataset.instantBound = "1";
        inventoryStatusFilter.addEventListener("change", () => {
            submitFiltersInstantly();
        });
    }
}

/* SIDEBAR */
if (openSidebar) {
    openSidebar.addEventListener("click", openSidebarMenu);
}

if (closeSidebar) {
    closeSidebar.addEventListener("click", closeSidebarMenu);
}

if (overlay) {
    overlay.addEventListener("click", closeSidebarMenu);
}

/* DROPDOWN IN SIDEBAR */
if (categoryToggle && categoryMenu) {
    const hasActiveSub = categoryMenu.querySelector(".active-sub, .active-submenu");

    if (hasActiveSub) {
        categoryMenu.classList.add("show");
        if (dropdownArrow) dropdownArrow.style.transform = "rotate(180deg)";
    }

    categoryToggle.addEventListener("click", () => {
        categoryMenu.classList.toggle("show");
        if (dropdownArrow) {
            dropdownArrow.style.transform = categoryMenu.classList.contains("show")
                ? "rotate(180deg)"
                : "rotate(0deg)";
        }
    });
}

/* OPEN / CLOSE MODALS */
if (openProductModal) {
    openProductModal.addEventListener("click", () => openModal(productModal));
}

if (closeProductModal) {
    closeProductModal.addEventListener("click", () => closeModal(productModal));
}

if (openCategoryModal) {
    openCategoryModal.addEventListener("click", () => openModal(categoryModal));
}

if (closeCategoryModal) {
    closeCategoryModal.addEventListener("click", () => closeModal(categoryModal));
}

if (closeEditProductModal) {
    closeEditProductModal.addEventListener("click", () => closeModal(editProductModal));
}

if (closeStockModal && stockModal) {
    closeStockModal.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal(stockModal);
    });
}

[productModal, editProductModal, stockModal, categoryModal].forEach((modal) => {
    if (!modal) return;

    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            closeModal(modal);
        }
    });
});

/* IMAGE PREVIEW */
if (productImageInput && previewImage) {
    productImageInput.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
            previewImage.src = URL.createObjectURL(file);
        }
    });
}

if (editProductImageInput && editPreviewImage) {
    editProductImageInput.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
            editPreviewImage.src = URL.createObjectURL(file);
        }
    });
}

/* GLOBAL CLICK HANDLER */
document.addEventListener("click", (event) => {
    const filterLink = event.target.closest("[data-filter-link]");
    if (filterLink) {
        event.preventDefault();
        refreshInventoryContent(filterLink.href);
        return;
    }

    const editButton = event.target.closest(".edit-btn");
    if (editButton) {
        setValue("edit_id", editButton.dataset.id);
        setValue("edit_product_code", editButton.dataset.code);
        setValue("edit_product_name", editButton.dataset.name);
        setValue("edit_category_id", editButton.dataset.category);
        setValue("edit_brand", editButton.dataset.brand);
        setValue("edit_unit", editButton.dataset.unit);
        setValue("edit_cost_price", editButton.dataset.cost);
        setValue("edit_selling_price", editButton.dataset.selling);
        setValue("edit_stock_quantity", editButton.dataset.stock);
        setValue("edit_reorder_level", editButton.dataset.reorder);
        setValue("edit_on_order_level", editButton.dataset.onorder);
        setValue("edit_expiry_date", editButton.dataset.expiry);
        setValue("edit_description", editButton.dataset.description);
        setValue("edit_is_active", editButton.dataset.active);
        setValue("edit_old_image", editButton.dataset.image);

        if (editPreviewImage) {
            editPreviewImage.src = editButton.dataset.image
                ? "/NexGen/CODE/PHP/" + editButton.dataset.image
                : "/NexGen/IMAGES/default-product.png";
        }

        closeAllMenus();
        openModal(editProductModal);
        return;
    }

    const stockButton = event.target.closest(".stock-btn");
    if (stockButton) {
        const productId = document.getElementById("stock_product_id");
        const productName = document.getElementById("stock_product_name");
        const currentOnOrder = document.getElementById("stock_current_on_order");
        const onOrderAdd = document.getElementById("on_order_add");
        const deductCheckbox = document.getElementById("deduct_from_on_order");

        if (productId) productId.value = stockButton.dataset.stockId;
        if (productName) productName.value = stockButton.dataset.stockName;
        if (currentOnOrder) currentOnOrder.value = stockButton.dataset.currentOnorder || "0";
        if (onOrderAdd) onOrderAdd.value = "0";
        if (deductCheckbox) deductCheckbox.checked = false;

        toggleStockOrderFields();
        closeAllMenus();
        openModal(stockModal);
        return;
    }

    const toggleButton = event.target.closest("[data-action-menu-toggle]");
    if (toggleButton) {
        event.stopPropagation();

        const wrap = toggleButton.closest(".action-menu-wrap");
        const menu = wrap ? wrap.querySelector("[data-action-menu]") : null;

        if (!menu) return;

        const isOpen = menu.classList.contains("show");
        closeAllMenus();

        if (!isOpen) {
            menu.classList.add("show");
        }
        return;
    }

    if (!event.target.closest(".action-menu-wrap")) {
        closeAllMenus();
    }
});

/* FILTER FORM SUBMIT */
document.addEventListener("submit", (event) => {
    const filterForm = event.target.closest("#inventoryFilterForm");
    if (filterForm) {
        event.preventDefault();
        submitFiltersInstantly();
    }
});

/* STOCK FIELD TOGGLE */
if (stockMovementType) {
    stockMovementType.addEventListener("change", toggleStockOrderFields);
    toggleStockOrderFields();
}

initDynamicFilterControls();

/* POPUP AUTO HIDE */
const popupOverlay = document.getElementById("popupOverlay");
if (popupOverlay) {
    setTimeout(() => {
        popupOverlay.remove();
    }, 2600);
}