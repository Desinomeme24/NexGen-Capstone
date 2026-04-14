const sidebar = document.getElementById("sidebar");
const openSidebar = document.getElementById("openSidebar");
const closeSidebar = document.getElementById("closeSidebar");
const overlay = document.getElementById("overlay");

const categoryToggle = document.getElementById("categoryToggle");
const categoryMenu = document.getElementById("categoryMenu");
const dropdownArrow = document.getElementById("dropdownArrow");

function openSidebarMenu() {
    if (sidebar && overlay) {
        sidebar.classList.add("active");
        overlay.classList.add("show");
        document.body.style.overflow = "hidden";
    }
}

function closeSidebarMenu() {
    if (sidebar && overlay) {
        sidebar.classList.remove("active");
        overlay.classList.remove("show");
        document.body.style.overflow = "";
    }
}

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
}

if (overlay) {
    overlay.addEventListener("click", closeSidebarMenu);
}

if (categoryToggle && categoryMenu) {
    categoryToggle.addEventListener("click", () => {
        categoryMenu.classList.toggle("show");

        if (dropdownArrow) {
            dropdownArrow.style.transform = categoryMenu.classList.contains("show")
                ? "rotate(180deg)"
                : "rotate(0deg)";
        }
    });
}

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        closeSidebarMenu();
    }
});

/* ABOUT US SCROLL ANIMATION */
document.addEventListener("DOMContentLoaded", () => {
    const animatedCards = document.querySelectorAll(".animate-card");

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("show");
                obs.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.22
    });

    animatedCards.forEach((card) => {
        observer.observe(card);
    });
});