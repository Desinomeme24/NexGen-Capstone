const popupOverlay = document.getElementById("popupOverlay");
const popupBox = document.getElementById("popupBox");

document.addEventListener("DOMContentLoaded", function () {
    const popupOverlay = document.getElementById("popupOverlay");

    if (popupOverlay) {
        setTimeout(() => {
            popupOverlay.remove();
        }, 7000);
    }
});
if (popupOverlay && popupBox) {
    popupOverlay.style.display = "flex";
    popupOverlay.style.opacity = "1";
    popupOverlay.style.visibility = "visible";
    popupBox.classList.add("show-popup");

    setTimeout(() => {
        popupOverlay.style.opacity = "0";
        popupOverlay.style.visibility = "hidden";
        setTimeout(() => {
            popupOverlay.remove();
        }, 400);
    }, 7000);

    popupOverlay.addEventListener("click", () => {
        popupOverlay.style.opacity = "0";
        popupOverlay.style.visibility = "hidden";
        setTimeout(() => {
            popupOverlay.remove();
        }, 400);
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const popupOverlay = document.getElementById("popupOverlay");

    if (popupOverlay) {
        setTimeout(() => {
            popupOverlay.remove();
        }, 7000);
    }
});