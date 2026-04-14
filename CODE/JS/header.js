const sidebar = document.getElementById("sidebar");
const openSidebar = document.getElementById("openSidebar");
const closeSidebar = document.getElementById("closeSidebar");
const overlay = document.getElementById("overlay");

const categoryToggle = document.getElementById("categoryToggle");
const categoryMenu = document.getElementById("categoryMenu");
const dropdownArrow = document.getElementById("dropdownArrow");

/* SIDEBAR */
function openSidebarMenu() {
    if (sidebar) {
        sidebar.classList.add("active");
    }

    if (overlay) {
        overlay.classList.add("show");
    }

    document.body.style.overflow = "hidden";
}

function closeSidebarMenu() {
    if (sidebar) {
        sidebar.classList.remove("active");
    }

    if (overlay) {
        overlay.classList.remove("show");
    }

    document.body.style.overflow = "";
}

/* Make functions accessible to inline HTML onclick */
window.openSidebarMenu = openSidebarMenu;
window.closeSidebarMenu = closeSidebarMenu;

/* CLICK SUPPORT */
if (openSidebar) {
    openSidebar.addEventListener("click", openSidebarMenu);

    openSidebar.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            openSidebarMenu();
        }
    });
}

if (closeSidebar) {
    closeSidebar.addEventListener("click", closeSidebarMenu);

    closeSidebar.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            closeSidebarMenu();
        }
    });
}

if (overlay) {
    overlay.addEventListener("click", closeSidebarMenu);
}

/* DROPDOWN */
if (categoryToggle && categoryMenu) {
    const hasActiveSub = categoryMenu.querySelector(".active-sub");

    if (hasActiveSub) {
        categoryMenu.classList.add("show");
        if (dropdownArrow) {
            dropdownArrow.style.transform = "rotate(180deg)";
        }
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

/* POPUP */
const popupOverlay = document.getElementById("popupOverlay");
const popupBox = document.getElementById("popupBox");

function closePopup() {
    if (popupOverlay && popupBox) {
        popupBox.classList.add("popup-hide");
        popupOverlay.classList.add("popup-overlay-hide");

        setTimeout(() => {
            popupOverlay.remove();
        }, 600);
    }
}

if (popupOverlay && popupBox) {
    setTimeout(() => {
        closePopup();
    }, 7000);

    popupOverlay.addEventListener("click", () => {
        closePopup();
    });

    popupBox.addEventListener("click", (e) => {
        e.stopPropagation();
    });
}

/* PROFILE IMAGE AUTO SUBMIT */
const profileInput = document.getElementById("new_profile_image");
const submitProfileBtn = document.getElementById("submitProfileBtn");

if (profileInput && submitProfileBtn) {
    profileInput.addEventListener("change", function () {
        if (this.files.length > 0) {
            submitProfileBtn.click();
        }
    });
}