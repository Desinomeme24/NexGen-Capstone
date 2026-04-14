const sidebar = document.getElementById("sidebar");
const openSidebar = document.getElementById("openSidebar");
const closeSidebar = document.getElementById("closeSidebar");
const overlay = document.getElementById("overlay");

const categoryToggle = document.getElementById("categoryToggle");
const categoryMenu = document.getElementById("categoryMenu");
const dropdownArrow = document.getElementById("dropdownArrow");

/* SIDEBAR */
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

/* DROPDOWN */
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

/* VIDEO ROTATION */
const bgVideos = document.querySelectorAll(".bg-video");
let currentVideo = 0;

if (bgVideos.length > 0) {
    bgVideos[0].classList.add("active");
}

if (bgVideos.length > 1) {
    setInterval(() => {
        bgVideos[currentVideo].classList.remove("active");
        currentVideo = (currentVideo + 1) % bgVideos.length;
        bgVideos[currentVideo].classList.add("active");
    }, 7000);
}

/* HERO BUTTON SCROLL */
const openModulesBtn = document.querySelector('a[href="#module-section"]');
const moduleSection = document.getElementById("module-section");

if (openModulesBtn && moduleSection) {
    openModulesBtn.addEventListener("click", (e) => {
        e.preventDefault();
        moduleSection.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    });
}

/* HERO MOVES UP ON SCROLL */
const heroShell = document.getElementById("heroShell");
const topVideoArea = document.getElementById("topVideoArea");

function animateHeroOnScroll() {
    if (!heroShell || !topVideoArea) return;

    const areaRect = topVideoArea.getBoundingClientRect();
    const viewportHeight = window.innerHeight;

    if (areaRect.bottom > 0 && areaRect.top < viewportHeight) {
        const scrolled = Math.max(0, -areaRect.top);
        const moveY = Math.min(scrolled * 0.18, 90);
        heroShell.style.transform = `translateY(-${moveY}px)`;
    } else {
        heroShell.style.transform = `translateY(0px)`;
    }
}

/* MODULES REVEAL EVERY TIME */
const moduleRevealItems = document.querySelectorAll(".module-reveal");

function animateModulesOnScroll() {
    if (!moduleRevealItems.length) return;

    const triggerPoint = window.innerHeight * 0.85;

    moduleRevealItems.forEach((item) => {
        const rect = item.getBoundingClientRect();

        if (rect.top < triggerPoint && rect.bottom > 80) {
            item.classList.add("show");
        } else {
            item.classList.remove("show");
        }
    });
}

function runScrollAnimations() {
    animateHeroOnScroll();
    animateModulesOnScroll();
}

window.addEventListener("scroll", runScrollAnimations);
window.addEventListener("load", runScrollAnimations);
window.addEventListener("resize", runScrollAnimations);