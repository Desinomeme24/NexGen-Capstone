/* ============================================================
   NexGen — Shared Header / Sidebar
   Merged version:
   - Reference sliding-pill header/sidebar design
   - Current working sidebar/dropdown/profile behavior
   - Clickable light/dark toggle
   - Synced with Settings -> Personalization via "nexgen-theme"
   ============================================================ */

(function () {
  "use strict";

  const THEME_KEY = "nexgen-theme";

  const sidebar = document.getElementById("sidebar");
  const openSidebar = document.getElementById("openSidebar");
  const closeSidebar = document.getElementById("closeSidebar");
  const overlay = document.getElementById("overlay");

  const categoryToggle = document.getElementById("categoryToggle");
  const categoryMenu = document.getElementById("categoryMenu");
  const dropdownArrow = document.getElementById("dropdownArrow");

  const themeToggle = document.getElementById("themeToggle");
  const themeIcon = document.getElementById("themeIcon");

  /* =========================
     THEME
     ========================= */

  function normalizeTheme(theme) {
    return theme === "light" ? "light" : "dark";
  }

  function getCurrentTheme() {
    return normalizeTheme(document.documentElement.getAttribute("data-theme"));
  }

  function syncThemeToggle(theme) {
    const currentTheme = normalizeTheme(theme);

    if (themeIcon) {
      themeIcon.className =
        currentTheme === "light" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
    }

    if (themeToggle) {
      const isLight = currentTheme === "light";

      themeToggle.setAttribute(
        "aria-label",
        isLight ? "Switch to dark mode" : "Switch to light mode",
      );

      themeToggle.setAttribute("aria-pressed", isLight ? "true" : "false");
    }
  }

  function applyTheme(theme, persist = true) {
    const nextTheme = normalizeTheme(theme);

    document.documentElement.setAttribute("data-theme", nextTheme);

    if (persist) {
      try {
        localStorage.setItem(THEME_KEY, nextTheme);
      } catch (error) {
        console.warn("Could not save NexGen theme preference.", error);
      }
    }

    syncThemeToggle(nextTheme);

    /* Settings.js can listen for this so its selected card updates too. */
    window.dispatchEvent(
      new CustomEvent("nexgen-theme-change", {
        detail: { theme: nextTheme },
      }),
    );
  }

  /* theme_init.php should set this before first paint.
     This fallback is kept for pages that forgot to include theme_init.php. */
  try {
    const savedTheme = localStorage.getItem(THEME_KEY);
    if (
      !document.documentElement.getAttribute("data-theme") &&
      (savedTheme === "light" || savedTheme === "dark")
    ) {
      document.documentElement.setAttribute("data-theme", savedTheme);
    }
  } catch (error) {}

  syncThemeToggle(getCurrentTheme());

  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      const nextTheme = getCurrentTheme() === "light" ? "dark" : "light";

      applyTheme(nextTheme, true);
    });
  }

  /* Sync the current page if another browser tab changes the preference. */
  window.addEventListener("storage", function (event) {
    if (
      event.key === THEME_KEY &&
      (event.newValue === "light" || event.newValue === "dark")
    ) {
      applyTheme(event.newValue, false);
    }
  });

  /* =========================
     SIDEBAR
     ========================= */

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

  /* header.php uses inline onclick for compatibility with existing pages. */
  window.openSidebarMenu = openSidebarMenu;
  window.closeSidebarMenu = closeSidebarMenu;

  if (openSidebar) {
    openSidebar.addEventListener("click", openSidebarMenu);

    openSidebar.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openSidebarMenu();
      }
    });
  }

  if (closeSidebar) {
    closeSidebar.addEventListener("click", closeSidebarMenu);

    closeSidebar.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        closeSidebarMenu();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener("click", closeSidebarMenu);
  }

  /* =========================
     CATEGORY DROPDOWN
     ========================= */

  if (categoryToggle && categoryMenu) {
    const hasActiveSub = categoryMenu.querySelector(".active-sub");

    if (hasActiveSub) {
      categoryMenu.classList.add("show");
    }

    function syncDropdownArrow() {
      if (!dropdownArrow) return;

      dropdownArrow.style.transform = categoryMenu.classList.contains("show")
        ? "rotate(180deg)"
        : "rotate(0deg)";
    }

    syncDropdownArrow();

    categoryToggle.addEventListener("click", function () {
      categoryMenu.classList.toggle("show");
      syncDropdownArrow();
    });
  }

  /* =========================
     POPUP
     ========================= */

  const popupOverlay = document.getElementById("popupOverlay");
  const popupBox = document.getElementById("popupBox");

  function closePopup() {
    if (!popupOverlay) return;

    if (popupBox) {
      popupBox.classList.add("popup-hide");
    }

    popupOverlay.classList.add("popup-overlay-hide");

    window.setTimeout(function () {
      if (popupOverlay.isConnected) {
        popupOverlay.remove();
      }
    }, 600);
  }

  if (popupOverlay) {
    window.setTimeout(closePopup, 7000);

    popupOverlay.addEventListener("click", closePopup);

    if (popupBox) {
      popupBox.addEventListener("click", function (event) {
        event.stopPropagation();
      });
    }
  }

  /* =========================
     PROFILE IMAGE AUTO-SUBMIT
     ========================= */

  const profileInput = document.getElementById("new_profile_image");

  const submitProfileBtn = document.getElementById("submitProfileBtn");

  if (profileInput && submitProfileBtn) {
    profileInput.addEventListener("change", function () {
      if (this.files && this.files.length > 0) {
        submitProfileBtn.click();
      }
    });
  }
})();
