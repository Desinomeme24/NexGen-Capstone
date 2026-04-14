const popupOverlay = document.getElementById("popupOverlay");

if (popupOverlay) {
    setTimeout(() => {
        popupOverlay.remove();
    }, 7000);

    popupOverlay.addEventListener("click", () => {
        popupOverlay.remove();
    });
}

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