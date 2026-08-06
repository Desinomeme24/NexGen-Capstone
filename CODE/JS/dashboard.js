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

/* FLOATING PARTICLES */
(function createFloatingElements() {
  const container = document.querySelector(".animated-bg");
  if (!container) return;

  const particleCount = 25;
  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement("div");
    particle.classList.add("floating-particle");
    const size = Math.random() * 6 + 2;
    const left = Math.random() * 100;
    const delay = Math.random() * 15;
    const duration = Math.random() * 10 + 15;
    const opacity = Math.random() * 0.4 + 0.1;
    particle.style.cssText = `
      width: ${size}px;
      height: ${size}px;
      left: ${left}%;
      animation-delay: ${delay}s;
      animation-duration: ${duration}s;
      opacity: ${opacity};
    `;
    container.appendChild(particle);
  }
})();

/* HERO BUTTON SCROLL */
const openModulesBtn = document.querySelector('a[href="#module-section"]');
const moduleSection = document.getElementById("module-section");

if (openModulesBtn && moduleSection) {
  openModulesBtn.addEventListener("click", (e) => {
    e.preventDefault();
    moduleSection.scrollIntoView({
      behavior: "smooth",
      block: "start",
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

/* WHY US SECTION - SHOW/HIDE ON DEMAND */
const whySectionToggleBtn = document.getElementById("whySectionToggleBtn");
const whySection = document.getElementById("why-section");
const whyRevealItems = document.querySelectorAll(".why-reveal");

if (whySectionToggleBtn && whySection) {
  whySectionToggleBtn.addEventListener("click", () => {
    const isOpen = whySection.classList.toggle("show");
    whySectionToggleBtn.classList.toggle("open", isOpen);
    whySectionToggleBtn.setAttribute(
      "aria-expanded",
      isOpen ? "true" : "false",
    );

    const label = whySectionToggleBtn.querySelector("span");
    if (label) {
      label.textContent = isOpen ? "Hide" : "Why NextGen?";
    }

    if (isOpen) {
      whyRevealItems.forEach((item) => item.classList.add("show"));

      setTimeout(() => {
        whySection.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 120);
    } else {
      whyRevealItems.forEach((item) => item.classList.remove("show"));
    }
  });
}

/* WHY US - EXPAND/COLLAPSE EXTRA ABOUT TEXT */
const whyToggleBtn = document.getElementById("whyToggleBtn");
const whyExtra = document.getElementById("whyExtra");
const whyToggleIcon = document.getElementById("whyToggleIcon");

if (whyToggleBtn && whyExtra) {
  whyToggleBtn.addEventListener("click", () => {
    const isOpen = whyExtra.classList.toggle("show");
    whyToggleBtn.classList.toggle("open", isOpen);
    whyToggleBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");

    const label = whyToggleBtn.querySelector("span");
    if (label) {
      label.textContent = isOpen ? "Show Less" : "More About Us";
    }
  });
}
