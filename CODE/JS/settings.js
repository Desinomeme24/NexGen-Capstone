/* =========================
   POPUP (success / error banner)
========================= */
const popupOverlay = document.getElementById("popupOverlay");

if (popupOverlay) {
  setTimeout(() => {
    popupOverlay.remove();
  }, 7000);

  popupOverlay.addEventListener("click", () => {
    popupOverlay.remove();
  });
}

/* =========================
   THEME (light / dark)
   Shares the "nexgen-theme" key with header.js so the choice made
   here stays in sync on every page that has the topbar toggle.
========================= */
const htmlEl = document.documentElement;
const themeOptions = document.querySelectorAll(".theme-card");

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

/* =========================
   NAV / PANEL SWITCHING (Account, Security, Personalization)
========================= */
const navItems = document.querySelectorAll(".settings-nav .nav-item");
const panels = document.querySelectorAll(".settings-panel");
const segmentedBtns = document.querySelectorAll(".segmented-btn");
const securityMethods = document.querySelectorAll(".security-method");

const accountForm = document.getElementById("accountForm");
const directPasswordForm = document.getElementById("directPasswordForm");
const otpRequestForm = document.getElementById("otpRequestForm");
const otpVerifyForm = document.getElementById("otpVerifyForm");

const profileInput = document.getElementById("new_profile_image");
const profileForm = document.getElementById("profileImageForm");

function openPanel(panelId) {
  navItems.forEach((item) => {
    item.classList.toggle("active", item.dataset.target === panelId);
  });

  panels.forEach((panel) => {
    panel.classList.toggle("active-panel", panel.id === panelId);
  });

  localStorage.setItem("settingsActivePanel", panelId);
}

function openSecurityMethod(methodId) {
  securityMethods.forEach((method) => {
    method.classList.remove("active-method");
  });

  segmentedBtns.forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.method === methodId);
  });

  if (methodId) {
    const selectedMethod = document.getElementById(methodId);
    if (selectedMethod) {
      selectedMethod.classList.add("active-method");
      localStorage.setItem("settingsSecurityMethod", methodId);
    }
  }
}

navItems.forEach((item) => {
  item.addEventListener("click", function () {
    openPanel(this.dataset.target);
  });
});

segmentedBtns.forEach((btn) => {
  btn.addEventListener("click", function () {
    openSecurityMethod(this.dataset.method);
    openPanel("security-panel");
  });
});

if (accountForm) {
  accountForm.addEventListener("submit", function () {
    localStorage.setItem("settingsActivePanel", "account-panel");
  });
}

if (directPasswordForm) {
  directPasswordForm.addEventListener("submit", function () {
    localStorage.setItem("settingsActivePanel", "security-panel");
    localStorage.setItem("settingsSecurityMethod", "current-password-method");
  });
}

if (otpRequestForm) {
  otpRequestForm.addEventListener("submit", function () {
    localStorage.setItem("settingsActivePanel", "security-panel");
    localStorage.setItem("settingsSecurityMethod", "otp-method");
  });
}

if (otpVerifyForm) {
  otpVerifyForm.addEventListener("submit", function () {
    localStorage.setItem("settingsActivePanel", "security-panel");
    localStorage.setItem("settingsSecurityMethod", "otp-method");
  });
}

const savedPanel =
  localStorage.getItem("settingsActivePanel") || "account-panel";
const savedMethod = localStorage.getItem("settingsSecurityMethod") || "";

openPanel(savedPanel);

if (savedPanel === "security-panel" && savedMethod) {
  openSecurityMethod(savedMethod);
}

if (profileInput && profileForm) {
  profileInput.addEventListener("change", function () {
    if (this.files && this.files.length > 0) {
      profileForm.submit();
    }
  });
}

/* =========================
   OTP PASSWORD FIELD PERSISTENCE (survives OTP request/verify redirect)
========================= */
const otpNewPassword = document.getElementById("otpNewPassword");
const otpConfirmPassword = document.getElementById("otpConfirmPassword");
const popupBox = document.getElementById("popupBox");
const popupText = popupBox ? popupBox.textContent.toLowerCase() : "";

if (otpNewPassword) {
  otpNewPassword.value = sessionStorage.getItem("otp_new_password") || "";

  otpNewPassword.addEventListener("input", function () {
    sessionStorage.setItem("otp_new_password", otpNewPassword.value);
  });
}

if (otpConfirmPassword) {
  otpConfirmPassword.value =
    sessionStorage.getItem("otp_confirm_password") || "";

  otpConfirmPassword.addEventListener("input", function () {
    sessionStorage.setItem("otp_confirm_password", otpConfirmPassword.value);
  });
}

if (otpRequestForm) {
  otpRequestForm.addEventListener("submit", function () {
    if (otpNewPassword) {
      sessionStorage.setItem("otp_new_password", otpNewPassword.value);
    }
    if (otpConfirmPassword) {
      sessionStorage.setItem("otp_confirm_password", otpConfirmPassword.value);
    }
  });
}

if (popupText.includes("password changed successfully")) {
  sessionStorage.removeItem("otp_new_password");
  sessionStorage.removeItem("otp_confirm_password");
}
