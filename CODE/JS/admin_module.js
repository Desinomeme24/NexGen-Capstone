document.addEventListener("DOMContentLoaded", function () {
  const popupOverlay = document.getElementById("popupOverlay");
  const popupBox = document.getElementById("popupBox");

  /* ==========================================
       POPUP HANDLER
    ========================================== */
  if (popupOverlay && popupBox) {
    popupOverlay.style.display = "flex";
    popupOverlay.style.opacity = "1";
    popupOverlay.style.visibility = "visible";
    popupBox.classList.add("show-popup");

    function closePopup() {
      popupOverlay.style.opacity = "0";
      popupOverlay.style.visibility = "hidden";

      setTimeout(() => {
        popupOverlay.remove();
      }, 400);
    }

    setTimeout(closePopup, 7000);

    popupOverlay.addEventListener("click", closePopup);
  }

  /* ==========================================
       TABLE ALIGNMENT
       Automatically center Role, Status,
       Position, Action, etc.
    ========================================== */

  const centeredHeaders = [
    "ROLE",
    "REQUESTED ROLE",
    "POSITION",
    "STATUS",
    "ACCOUNT STATUS",
    "VERIFIED",
    "CREATED",
    "SUBMITTED",
    "ACTION",
    "ACTIONS",
  ];

  function normalize(text) {
    return text.trim().replace(/\s+/g, " ").toUpperCase();
  }

  function alignTables(root = document) {
    const tables = root.querySelectorAll("table");

    tables.forEach((table) => {
      const headers = table.querySelectorAll("thead th");

      headers.forEach((header, index) => {
        const title = normalize(header.innerText);

        if (!centeredHeaders.includes(title)) return;

        header.classList.add("admin-col-center");

        table.querySelectorAll("tbody tr").forEach((row) => {
          if (row.children[index]) {
            row.children[index].classList.add("admin-col-center");
          }
        });
      });
    });
  }

  alignTables();

  /* ==========================================
       AUTO ALIGN NEW AJAX TABLES
    ========================================== */

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType !== 1) return;

        if (node.matches("table")) {
          alignTables(node.parentElement);
        } else {
          alignTables(node);
        }
      });
    });
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
});
