document.addEventListener("DOMContentLoaded", function () {
  const profileInput = document.getElementById("new_profile_image");
  const submitProfileBtn = document.getElementById("submitProfileBtn");

  if (profileInput && submitProfileBtn) {
    profileInput.addEventListener("change", function () {
      if (this.files.length > 0) {
        submitProfileBtn.click();
      }
    });
  }

  const popupOverlay = document.getElementById("popupOverlay");
  if (popupOverlay) {
    setTimeout(() => {
      popupOverlay.style.opacity = "0";
      popupOverlay.style.transition = "opacity 0.4s ease";
      setTimeout(() => popupOverlay.remove(), 400);
    }, 2500);

    popupOverlay.addEventListener("click", () => {
      popupOverlay.remove();
    });
  }

  const dashboardSection = document.getElementById("dashboardSection");
  const appendicesSection = document.getElementById("appendicesSection");
  const appendicesToggleBtn = document.getElementById("appendicesToggleBtn");
  const backToDashboardBtn = document.getElementById("backToDashboardBtn");

  if (appendicesToggleBtn && dashboardSection && appendicesSection) {
    appendicesToggleBtn.addEventListener("click", function () {
      dashboardSection.classList.add("d-none");
      appendicesSection.classList.remove("d-none");
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  if (backToDashboardBtn && dashboardSection && appendicesSection) {
    backToDashboardBtn.addEventListener("click", function () {
      appendicesSection.classList.add("d-none");
      dashboardSection.classList.remove("d-none");
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Notifications are marked as read only via the "Mark all read" button
  // (handled inline in sales_analytics.php) — opening the modal alone
  // no longer marks anything as seen.

  const analyticsData = window.salesAnalyticsData || {
    dailyLabels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
    dailySalesData: [0, 0, 0, 0, 0, 0, 0],
    monthlyLabels: [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
    ],
    monthlyRevenueData: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    categoryLabels: ["No Category"],
    categorySalesData: [1],
    rangeLabel: "Today",
    netProfit: 0,
    grossRevenue: 0,
    costOfGoodsSold: 0,
    totalTransactions: 0,
    salesGrowth: 0,
  };

  function formatPeso(value) {
    return (
      "₱" +
      Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function formatPesoCompact(value) {
    return "₱" + Number(value || 0).toLocaleString();
  }

  function getToastRoot() {
    let root = document.getElementById("nxToastRoot");
    if (!root) {
      root = document.createElement("div");
      root.id = "nxToastRoot";
      root.className = "nx-toast-root";
      document.body.appendChild(root);
    }
    return root;
  }

  function showToast(message, type = "info", duration = 3200) {
    const root = getToastRoot();
    const toast = document.createElement("div");
    toast.className = `nx-toast ${type}`;

    const titleMap = {
      success: "Success",
      error: "Attention",
      warning: "Notice",
      info: "Message",
    };

    const accent = document.createElement("div");
    accent.className = "nx-toast-accent";

    const content = document.createElement("div");
    content.className = "nx-toast-content";

    const title = document.createElement("div");
    title.className = "nx-toast-title";
    title.textContent = titleMap[type] || "Message";

    const msg = document.createElement("div");
    msg.className = "nx-toast-message";
    msg.textContent = String(message ?? "");

    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "nx-toast-close";
    closeBtn.setAttribute("aria-label", "Close notification");
    closeBtn.textContent = "×";

    content.appendChild(title);
    content.appendChild(msg);

    toast.appendChild(accent);
    toast.appendChild(content);
    toast.appendChild(closeBtn);

    root.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add("show");
    });

    const removeToast = () => {
      toast.classList.remove("show");
      setTimeout(() => {
        if (toast.parentNode) {toast.parentNode.removeChild(toast);}
      }, 220);
    };

    closeBtn.addEventListener("click", removeToast);
    setTimeout(removeToast, duration);
  }

  function percentFormatter(value, context) {
    const dataArr = context.chart.data.datasets[0].data || [];
    const sum = dataArr.reduce((a, b) => a + b, 0);
    if (sum <= 0) {return "0%";}
    return ((value / sum) * 100).toFixed(1) + "%";
  }

  function addMetricRings() {
    const cards = document.querySelectorAll(".summary-card");
    cards.forEach((card) => {
      if (!card.querySelector(".metric-ring")) {
        const ring = document.createElement("span");
        ring.className = "metric-ring";
        card.appendChild(ring);
      }
      card.classList.add("float-card");
    });
  }

  function addDecorativeOrbs() {
    const targets = document.querySelectorAll(".summary-card, .analytics-card");
    const palette = [
      "rgba(89, 212, 255, 0.22)",
      "rgba(74, 141, 255, 0.20)",
      "rgba(78, 242, 180, 0.18)",
      "rgba(255, 200, 105, 0.16)",
    ];

    targets.forEach((target, index) => {
      if (target.querySelector(".design-orb")) {return;}

      const orb = document.createElement("span");
      orb.className = "design-orb";
      const size = 26 + ((index * 9) % 30);
      orb.style.width = `${size}px`;
      orb.style.height = `${size}px`;
      orb.style.right = `${16 + ((index * 13) % 40)}px`;
      orb.style.bottom = `${16 + ((index * 17) % 45)}px`;
      orb.style.background = palette[index % palette.length];
      orb.style.setProperty("--float-duration", `${6 + (index % 5)}s`);
      target.appendChild(orb);
    });

    document.querySelectorAll(".analytics-card").forEach((card) => {
      card.classList.add("chart-pulse");
    });
  }

  function add3DHover() {
    const cards = document.querySelectorAll(
      ".summary-card, .analytics-card, .appendix-item",
    );

    cards.forEach((card) => {
      card.addEventListener("mousemove", (e) => {
        if (window.innerWidth < 992) {return;}

        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const rotateY = (x / rect.width - 0.5) * 8;
        const rotateX = (y / rect.height - 0.5) * -8;

        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-6px)`;
        card.style.transition = "transform 0.08s linear";
      });

      card.addEventListener("mouseleave", () => {
        card.style.transform = "";
        card.style.transition = "transform 0.28s ease";
      });
    });
  }

  function animateCounter(el, finalValue) {
    if (!el) {return;}

    const text = el.textContent || "";
    const isPercent = text.includes("%");
    const isPeso = text.includes("₱");
    const hasDecimal = text.includes(".") || !Number.isInteger(finalValue);
    const safeFinal = Number(finalValue || 0);

    const start = 0;
    const duration = 1200;
    const startTime = performance.now();

    function frame(now) {
      const progress = Math.min((now - startTime) / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = start + (safeFinal - start) * ease;

      if (isPeso) {
        el.textContent = formatPeso(current);
      } else if (isPercent) {
        el.textContent = `${current.toFixed(2)}%`;
      } else {
        el.textContent = hasDecimal
          ? current.toFixed(2)
          : Math.round(current).toLocaleString();
      }

      if (progress < 1) {
        requestAnimationFrame(frame);
      } else {
        if (isPeso) {
          el.textContent = formatPeso(safeFinal);
        } else if (isPercent) {
          el.textContent = `${safeFinal.toFixed(2)}%`;
        } else {
          el.textContent = hasDecimal
            ? safeFinal.toFixed(2)
            : Math.round(safeFinal).toLocaleString();
        }
      }
    }

    requestAnimationFrame(frame);
  }

  function animateSummaryValues() {
    const summaryCards = document.querySelectorAll(".summary-card");
    if (!summaryCards.length) {return;}

    const values = [
      Number(analyticsData.netProfit || 0),
      Number(analyticsData.grossRevenue || 0),
      Number(analyticsData.costOfGoodsSold || 0),
      Number(analyticsData.salesGrowth || 0),
      Number(analyticsData.totalTransactions || 0),
    ];

    summaryCards.forEach((card, index) => {
      const valueEl = card.querySelector(".summary-value");
      animateCounter(valueEl, values[index] ?? 0);
    });
  }

  function createLineGradient(ctx, chartArea) {
    const gradient = ctx.createLinearGradient(
      0,
      chartArea.top,
      0,
      chartArea.bottom,
    );
    gradient.addColorStop(0, "rgba(89, 212, 255, 0.95)");
    gradient.addColorStop(0.55, "rgba(74, 141, 255, 0.90)");
    gradient.addColorStop(1, "rgba(120, 160, 255, 0.82)");
    return gradient;
  }

  function createFillGradient(ctx, chartArea) {
    const gradient = ctx.createLinearGradient(
      0,
      chartArea.top,
      0,
      chartArea.bottom,
    );
    gradient.addColorStop(0, "rgba(89, 212, 255, 0.35)");
    gradient.addColorStop(1, "rgba(89, 212, 255, 0.02)");
    return gradient;
  }

  function createBarGradient(ctx, chartArea) {
    const gradient = ctx.createLinearGradient(
      0,
      chartArea.bottom,
      0,
      chartArea.top,
    );
    gradient.addColorStop(0, "rgba(89, 212, 255, 0.78)");
    gradient.addColorStop(0.55, "rgba(74, 141, 255, 0.86)");
    gradient.addColorStop(1, "rgba(120, 160, 255, 0.94)");
    return gradient;
  }

  const commonGrid = "rgba(255,255,255,0.08)";
  const commonTicks = "#a9bbda";

  const lineShadowPlugin = {
    id: "lineShadowPlugin",
    beforeDatasetsDraw(chart) {
      const { ctx } = chart;
      ctx.save();
      ctx.shadowColor = "rgba(89, 212, 255, 0.30)";
      ctx.shadowBlur = 16;
      ctx.shadowOffsetX = 0;
      ctx.shadowOffsetY = 0;
    },
    afterDatasetsDraw(chart) {
      chart.ctx.restore();
    },
  };

  const chartAreaGlowPlugin = {
    id: "chartAreaGlowPlugin",
    beforeDraw(chart) {
      const { ctx, chartArea } = chart;
      if (!chartArea) {return;}

      ctx.save();
      const grd = ctx.createLinearGradient(
        0,
        chartArea.top,
        chartArea.right,
        chartArea.bottom,
      );
      grd.addColorStop(0, "rgba(89,212,255,0.05)");
      grd.addColorStop(0.5, "rgba(74,141,255,0.04)");
      grd.addColorStop(1, "rgba(78,242,180,0.03)");
      ctx.fillStyle = grd;
      ctx.fillRect(
        chartArea.left,
        chartArea.top,
        chartArea.right - chartArea.left,
        chartArea.bottom - chartArea.top,
      );
      ctx.restore();
    },
  };

  if (typeof Chart !== "undefined") {
    Chart.defaults.color = "#d4e3ff";
    Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
  }

  const dailySalesCanvas = document.getElementById("dailySalesChart");
  if (dailySalesCanvas && typeof Chart !== "undefined") {
    new Chart(dailySalesCanvas, {
      type: "line",
      data: {
        labels: analyticsData.dailyLabels,
        datasets: [
          {
            label: "Daily Net Profit",
            data: analyticsData.dailySalesData,
            borderColor: (context) => {
              const { chart } = context;
              const { ctx, chartArea } = chart;
              if (!chartArea) {return "#59d4ff";}
              return createLineGradient(ctx, chartArea);
            },
            backgroundColor: (context) => {
              const { chart } = context;
              const { ctx, chartArea } = chart;
              if (!chartArea) {return "rgba(89, 212, 255, 0.20)";}
              return createFillGradient(ctx, chartArea);
            },
            tension: 0.42,
            fill: true,
            borderWidth: 3.5,
            pointRadius: 4,
            pointHoverRadius: 7,
            pointBackgroundColor: "#ffffff",
            pointBorderColor: "#59d4ff",
            pointBorderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 1700,
          easing: "easeOutQuart",
        },
        interaction: {
          intersect: false,
          mode: "index",
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: "rgba(8, 14, 28, 0.96)",
            borderColor: "rgba(89,212,255,0.22)",
            borderWidth: 1,
            titleColor: "#ffffff",
            bodyColor: "#dbeaff",
            displayColors: false,
            callbacks: {
              label: function (context) {
                return " " + formatPesoCompact(context.parsed.y);
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            min: 0,
            max: 3000,
            ticks: {
              stepSize: 500,
              callback: function (value) {
                return "₱" + Number(value).toLocaleString();
              },
              color: commonTicks,
            },
            grid: {
              color: commonGrid,
              drawBorder: false,
            },
          },
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: commonTicks,
            },
          },
        },
      },
      plugins: [lineShadowPlugin, chartAreaGlowPlugin],
    });
  }

  const monthlyRevenueCanvas = document.getElementById("monthlyRevenueChart");
  if (monthlyRevenueCanvas && typeof Chart !== "undefined") {
    new Chart(monthlyRevenueCanvas, {
      type: "bar",
      data: {
        labels: analyticsData.monthlyLabels,
        datasets: [
          {
            label: "Monthly Net Profit",
            data: analyticsData.monthlyRevenueData,
            backgroundColor: (context) => {
              const { chart } = context;
              const { ctx, chartArea } = chart;
              if (!chartArea) {return "rgba(103,162,255,0.85)";}
              return createBarGradient(ctx, chartArea);
            },
            borderRadius: 12,
            borderSkipped: false,
            barThickness: 22,
            hoverBorderWidth: 1.5,
            hoverBorderColor: "rgba(255,255,255,0.28)",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 1800,
          easing: "easeOutBounce",
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: "rgba(8, 14, 28, 0.96)",
            borderColor: "rgba(74,141,255,0.22)",
            borderWidth: 1,
            titleColor: "#ffffff",
            bodyColor: "#dbeaff",
            displayColors: false,
            callbacks: {
              label: function (context) {
                return " " + formatPesoCompact(context.parsed.y);
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            min: 0,
            max: 30000,
            ticks: {
              stepSize: 5000,
              callback: function (value) {
                return "₱" + Number(value).toLocaleString();
              },
              color: commonTicks,
            },
            grid: {
              color: commonGrid,
              drawBorder: false,
            },
          },
          x: {
            grid: {
              display: false,
            },
            ticks: {
              color: commonTicks,
            },
          },
        },
      },
      plugins: [chartAreaGlowPlugin],
    });
  }

  const categoryCanvas = document.getElementById("categoryChart");
  if (categoryCanvas && typeof Chart !== "undefined") {
    if (typeof ChartDataLabels !== "undefined") {
      Chart.register(ChartDataLabels);
    }

    new Chart(categoryCanvas, {
      type: "doughnut",
      data: {
        labels: analyticsData.categoryLabels,
        datasets: [
          {
            data: analyticsData.categorySalesData,
            backgroundColor: [
              "#59d4ff",
              "#4a8dff",
              "#76a8ff",
              "#4ef2b4",
              "#ffc869",
              "#ff8ba2",
              "#7ce8ff",
              "#96b6ff",
            ],
            borderColor: "rgba(6, 11, 22, 0.92)",
            borderWidth: 4,
            hoverOffset: 10,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "56%",
        animation: {
          duration: 1800,
          easing: "easeOutExpo",
        },
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              color: "#333333",
              boxWidth: 12,
              font: {
                weight: "bold",
              },
            },
          },
          datalabels:
            typeof ChartDataLabels !== "undefined"
              ? {
                  color: "#ffffff",
                  font: {
                    weight: "bold",
                    size: 11,
                  },
                  formatter: percentFormatter,
                }
              : {},
          tooltip: {
            backgroundColor: "rgba(8, 14, 28, 0.96)",
            borderColor: "rgba(89,212,255,0.22)",
            borderWidth: 1,
            titleColor: "#ffffff",
            bodyColor: "#dbeaff",
            callbacks: {
              label: function (context) {
                const value = Number(context.raw);
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percent =
                  total > 0 ? ((value / total) * 100).toFixed(2) : "0.00";
                return ` ${context.label}: ${value} unit(s) - ${percent}%`;
              },
            },
          },
        },
      },
    });
  }

  const exportPdfBtn = document.getElementById("exportPdfBtn");
  if (exportPdfBtn) {
    exportPdfBtn.addEventListener("click", async function () {
      const captureArea = document.getElementById("analyticsCaptureArea");
      if (!captureArea || !window.jspdf || !window.html2canvas) {return;}

      const { jsPDF } = window.jspdf;

      exportPdfBtn.disabled = true;
      exportPdfBtn.innerHTML =
        '<i class="bi bi-file-earmark-pdf-fill"></i> Exporting...';

      try {
        const canvas = await window.html2canvas(captureArea, {
          scale: 2,
          useCORS: true,
          backgroundColor: "#08111d",
        });

        const imgData = canvas.toDataURL("image/png");
        const pdf = new jsPDF("p", "mm", "a4");

        const pageWidth = 210;
        const pageHeight = 297;
        const imgWidth = pageWidth - 10;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        let heightLeft = imgHeight;
        let position = 5;

        pdf.addImage(imgData, "PNG", 5, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft > 0) {
          position = heightLeft - imgHeight + 5;
          pdf.addPage();
          pdf.addImage(imgData, "PNG", 5, position, imgWidth, imgHeight);
          heightLeft -= pageHeight;
        }

        pdf.save("sales_analytics_dashboard.pdf");
      } catch (error) {
        console.error("PDF export error:", error);
        const root = getToastRoot();
        if (root) {
          showToast("PDF export failed. Please try again.", "error");
        }
      }

      exportPdfBtn.disabled = false;
      exportPdfBtn.innerHTML =
        '<i class="bi bi-file-earmark-pdf-fill"></i> Export PDF';
    });
  }

  const exportExcelBtn = document.getElementById("exportExcelBtn");
  if (exportExcelBtn) {
    exportExcelBtn.addEventListener("click", function () {
      const data = window.salesAnalyticsData || {};
      let csvContent = "data:text/csv;charset=utf-8,";

      csvContent += "Sales Analytics Dashboard\r\n";
      csvContent += "Range," + (data.rangeLabel || "") + "\r\n\r\n";

      csvContent += "Summary\r\n";
      csvContent +=
        "Net Sales," + (data.netSales ?? data.netProfit ?? 0) + "\r\n";
      csvContent += "Gross Revenue," + (data.grossRevenue ?? 0) + "\r\n";
      csvContent += "Transactions," + (data.totalTransactions ?? 0) + "\r\n";
      csvContent += "Sales Growth," + (data.salesGrowth ?? 0) + "%\r\n\r\n";

      csvContent += "Daily Sales\r\n";
      csvContent += "Day,Amount\r\n";
      (data.dailyLabels || []).forEach((label, index) => {
        csvContent += `${label},${(data.dailySalesData || [])[index] ?? 0}\r\n`;
      });
      csvContent += "\r\n";

      csvContent += "Monthly Revenue\r\n";
      csvContent += "Month,Amount\r\n";
      (data.monthlyLabels || []).forEach((label, index) => {
        csvContent += `${label},${(data.monthlyRevenueData || [])[index] ?? 0}\r\n`;
      });
      csvContent += "\r\n";

      csvContent += "Sales By Category\r\n";
      csvContent += "Category,Units Sold\r\n";
      (data.categoryLabels || []).forEach((label, index) => {
        csvContent += `${label},${(data.categorySalesData || [])[index] ?? 0}\r\n`;
      });

      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", "sales_analytics_dashboard.csv");
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  }

  addMetricRings();
  addDecorativeOrbs();
  add3DHover();

  setTimeout(() => {
    animateSummaryValues();
  }, 220);
});
