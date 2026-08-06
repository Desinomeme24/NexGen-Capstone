const popupOverlay = document.getElementById("popupOverlay");

if (popupOverlay) {
    setTimeout(() => {
        popupOverlay.remove();
    }, 7000);

    popupOverlay.addEventListener("click", () => {
        popupOverlay.remove();
    });
}

/* THEME (light / dark) — shares the "nexgen-theme" key with header.js so
   the choice made here stays in sync on every page that has the topbar toggle. */
const htmlEl = document.documentElement;
const themeOptions = document.querySelectorAll(".theme-option");

function markActiveTheme(theme) {
    themeOptions.forEach((btn) => {
        btn.classList.toggle("selected", btn.dataset.themeChoice === theme);
    });
}

markActiveTheme(htmlEl.getAttribute("data-theme") || "dark");

themeOptions.forEach((btn) => {
    btn.addEventListener("click", function () {
        const theme = this.dataset.themeChoice;
        htmlEl.setAttribute("data-theme", theme);
        localStorage.setItem("nexgen-theme", theme);
        markActiveTheme(theme);
    });
});

const navItems = document.querySelectorAll(".nav-item");
const panels = document.querySelectorAll(".settings-panel");

navItems.forEach((item) => {
    item.addEventListener("click", function () {
        const targetId = this.getAttribute("data-target");

        navItems.forEach((nav) => nav.classList.remove("active"));
        this.classList.add("active");

        panels.forEach((panel) => panel.classList.remove("active-panel"));

        const targetPanel = document.getElementById(targetId);
        if (targetPanel) {
            targetPanel.classList.add("active-panel");
        }
    });
});