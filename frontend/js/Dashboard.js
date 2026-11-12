"use strict";

/* ==========================================================================
   REGIX DASHBOARD
   Frontend Application
========================================================================== */

/* ==========================================================================
   1. CONFIGURATION
========================================================================== */

// Use the current Dashboard page as the API endpoint.
// Normalize duplicated slashes in the pathname.
const API_URL =
  window.location.origin + window.location.pathname.replace(/\/{2,}/g, "/");

const CURRENCY = "JD";

let salesChartInstance = null;
let categoryChartInstance = null;

let dashboardData = null;
let allOrders = [];

const categoryPalette = [
  "#a3e635",
  "#38bdf8",
  "#a78bfa",
  "#f59e0b",
  "#fb7185",
  "#2dd4bf",
];

/* ==========================================================================
   2. DOM ELEMENTS
========================================================================== */

const elements = {
  periodForm: document.getElementById("periodForm"),
  periodSelect: document.getElementById("periodSelect"),

  refreshButton: document.getElementById("refreshButton"),
  retryButton: document.getElementById("retryButton"),

  dashboardContent: document.getElementById("dashboardContent"),

  loadingState: document.getElementById("loadingState"),

  errorBanner: document.getElementById("errorBanner"),
  errorMessage: document.getElementById("errorMessage"),

  orderSearch: document.getElementById("orderSearch"),
  statusFilter: document.getElementById("statusFilter"),

  ordersBody: document.getElementById("ordersBody"),
  ordersEmpty: document.getElementById("ordersEmpty"),

  toast: document.getElementById("toast"),
};

/* ==========================================================================
   3. GENERAL HELPERS
========================================================================== */

function escapeHTML(value) {
  return String(value ?? "").replace(
    /[&<>"']/g,
    (character) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[character],
  );
}

function money(value) {
  const number = Number(value) || 0;

  return `${CURRENCY} ${number.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function compactMoney(value) {
  const number = Number(value) || 0;

  if (number >= 1000000) {
    return (number / 1000000).toFixed(1) + "m";
  }

  if (number >= 1000) {
    return (number / 1000).toFixed(1) + "k";
  }

  return number.toLocaleString("en-US", {
    maximumFractionDigits: 0,
  });
}

function numberFormat(value) {
  return (Number(value) || 0).toLocaleString("en-US");
}

function formatGrowth(value) {
  if (value === null || value === undefined) {
    return {
      text: "—",
      className: "neutral",
    };
  }

  const number = Number(value);

  return {
    text: `${number > 0 ? "+" : ""}${number.toFixed(1)}%`,

    className: number > 0 ? "positive" : number < 0 ? "negative" : "neutral",
  };
}

function setTrend(elementId, value) {
  const element = document.getElementById(elementId);

  if (!element) return;

  const result = formatGrowth(value);

  element.className = `trend ${result.className}`;

  element.textContent = result.text;
}

function showToast(message) {
  if (!elements.toast) return;

  elements.toast.textContent = message;

  elements.toast.classList.add("show");

  window.setTimeout(() => {
    elements.toast.classList.remove("show");
  }, 2800);
}

/* ==========================================================================
   4. LOADING AND ERROR HANDLING
========================================================================== */

function setLoading(loading) {
  elements.loadingState?.classList.toggle("visible", loading);

  elements.dashboardContent?.classList.toggle("loading-state", loading);

  if (elements.refreshButton) {
    elements.refreshButton.disabled = loading;

    elements.refreshButton.textContent = loading ? "…" : "↻ Refresh";
  }
}

function showError(message) {
  if (!elements.errorBanner || !elements.errorMessage) return;

  elements.errorMessage.textContent = message;

  elements.errorBanner.classList.add("visible");
}

function hideError() {
  elements.errorBanner?.classList.remove("visible");
}

/* ==========================================================================
   5. LOAD DASHBOARD DATA
========================================================================== */

async function loadDashboard() {
  if (!elements.periodSelect) {
    console.error("Missing periodSelect element.");
    return;
  }

  const period = elements.periodSelect.value;

  if (!["7", "30", "90"].includes(period)) {
    showError("Invalid reporting period.");

    return;
  }

  setLoading(true);

  hideError();

  try {
    // Build API URL using the current dashboard.php page.
    const url = new URL(API_URL);

    url.searchParams.set("action", "dashboard");
    url.searchParams.set("period", String(period));

    console.log("Dashboard API URL:", url.toString());

    const response = await fetch(url.toString(), {
      method: "GET",
      headers: {
        Accept: "application/json",
      },
      cache: "no-store",
    });

    // Read the response as text first.
    const rawResponse = await response.text();

    let result;

    // Convert JSON safely.
    try {
      result = JSON.parse(rawResponse);
    } catch (parseError) {
      console.error("Invalid API Response:", rawResponse);

      throw new Error(
        `The server returned invalid JSON. HTTP ${response.status}. Check the API URL.`,
      );
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || `HTTP Error: ${response.status}`);
    }

    if (!result.data) {
      throw new Error("The API returned empty dashboard data.");
    }

    dashboardData = result.data;

    renderDashboard(dashboardData);

    // Update browser URL without reloading the page.
    const currentUrl = new URL(window.location.href);

    currentUrl.searchParams.set("period", period);

    currentUrl.searchParams.delete("action");

    window.history.replaceState({}, "", currentUrl);
  } catch (error) {
    console.error("REGIX Dashboard Error:", error);

    showError(error.message || "Unable to load dashboard data.");
  } finally {
    setLoading(false);
  }
}

/* ==========================================================================
   6. RENDER DASHBOARD
========================================================================== */

function renderDashboard(data) {
  renderKpis(data.summary);

  renderSalesChart(data.chart);

  renderCategories(data.categories, data.category_total);

  renderProducts(data.products);

  renderInventory(data.inventory);

  allOrders = Array.isArray(data.orders) ? data.orders : [];

  renderOrders();

  // Update timestamp.
  const updatedAt = data.generated_at
    ? new Date(data.generated_at.replace(" ", "T"))
    : new Date();

  const salesUpdated = document.getElementById("salesUpdated");

  const ordersLastUpdated = document.getElementById("ordersLastUpdated");

  if (salesUpdated) {
    salesUpdated.textContent = `Updated ${updatedAt.toLocaleTimeString(
      "en-US",
      {
        hour: "2-digit",
        minute: "2-digit",
      },
    )}`;
  }

  if (ordersLastUpdated) {
    ordersLastUpdated.textContent = `${allOrders.length} latest records`;
  }
}

/* ==========================================================================
   7. KPI CARDS
========================================================================== */

function renderKpis(summary) {
  if (!summary) return;

  document.getElementById("salesKpi").textContent = money(summary.sales);

  document.getElementById("ordersKpi").textContent = numberFormat(
    summary.orders,
  );

  document.getElementById("aovKpi").textContent = money(summary.aov);

  document.getElementById("customersKpi").textContent = numberFormat(
    summary.customers,
  );

  setTrend("salesTrend", summary.sales_growth);

  setTrend("ordersTrend", summary.orders_growth);

  setTrend("aovTrend", summary.aov_growth);

  setTrend("customersTrend", summary.customers_growth);

  document.getElementById("chartSalesTotal").textContent = money(summary.sales);

  const growth = formatGrowth(summary.sales_growth);

  const chartTrend = document.getElementById("chartSummaryTrend");

  chartTrend.className = `summary-trend ${
    growth.className === "negative" ? "negative" : ""
  }`;

  chartTrend.textContent =
    summary.sales_growth === null
      ? "No previous data"
      : `${summary.sales_growth > 0 ? "↗" : "↘"} ${growth.text} compared to prior period`;
}

/* ==========================================================================
   8. SALES PERFORMANCE CHART
========================================================================== */

function renderSalesChart(chartData) {
  const canvas = document.getElementById("salesChart");

  if (!canvas) return;

  if (!window.Chart) {
    console.error("Chart.js is not available.");

    return;
  }

  if (salesChartInstance) {
    salesChartInstance.destroy();

    salesChartInstance = null;
  }

  const context = canvas.getContext("2d");

  const gradient = context.createLinearGradient(0, 0, 0, 250);

  gradient.addColorStop(0, "rgba(163, 230, 53, .25)");

  gradient.addColorStop(1, "rgba(163, 230, 53, 0)");

  salesChartInstance = new Chart(context, {
    type: "line",

    data: {
      labels: chartData.labels,

      datasets: [
        {
          label: "Current Period",

          data: chartData.current,

          borderColor: "#a3e635",

          backgroundColor: gradient,

          borderWidth: 2.5,

          fill: true,

          tension: 0.4,

          pointRadius: 0,

          pointHoverRadius: 5,

          pointHoverBackgroundColor: "#a3e635",

          pointHoverBorderColor: "#090a0c",

          pointHoverBorderWidth: 3,
        },

        {
          label: "Previous Period",

          data: chartData.previous,

          borderColor: "#526071",

          backgroundColor: "transparent",

          borderWidth: 1.8,

          borderDash: [5, 5],

          fill: false,

          tension: 0.4,

          pointRadius: 0,

          pointHoverRadius: 4,
        },
      ],
    },

    options: {
      responsive: true,

      maintainAspectRatio: false,

      interaction: {
        intersect: false,
        mode: "index",
      },

      plugins: {
        legend: {
          display: true,

          position: "bottom",

          labels: {
            color: "#94a3b8",

            usePointStyle: true,

            pointStyle: "circle",

            boxWidth: 7,

            boxHeight: 7,

            padding: 20,

            font: {
              size: 10,
            },
          },
        },

        tooltip: {
          backgroundColor: "#1e222a",

          borderColor: "#333c4d",

          borderWidth: 1,

          titleColor: "#ffffff",

          bodyColor: "#94a3b8",

          padding: 12,

          displayColors: true,

          callbacks: {
            label: function (context) {
              return `${context.dataset.label}: ${money(context.raw)}`;
            },
          },
        },
      },

      scales: {
        x: {
          grid: {
            display: false,
          },

          border: {
            display: false,
          },

          ticks: {
            color: "#64748b",

            maxTicksLimit: 10,

            font: {
              size: 9,
            },
          },
        },

        y: {
          beginAtZero: true,

          grid: {
            color: "rgba(255,255,255,.05)",
          },

          border: {
            display: false,
          },

          ticks: {
            color: "#64748b",

            font: {
              size: 9,
            },

            callback: function (value) {
              return compactMoney(value);
            },
          },
        },
      },
    },
  });
}

/* ==========================================================================
   9. CATEGORY CHART
========================================================================== */

function renderCategories(categories, total) {
  const canvas = document.getElementById("categoryChart");

  const legend = document.getElementById("categoryLegend");

  const empty = document.getElementById("categoryEmpty");

  const topCategoryText = document.getElementById("topCategoryText");

  const topCategoryShare = document.getElementById("topCategoryShare");

  document.getElementById("categoryTotal").textContent = compactMoney(total);

  if (categoryChartInstance) {
    categoryChartInstance.destroy();

    categoryChartInstance = null;
  }

  legend.innerHTML = "";

  if (!Array.isArray(categories) || categories.length === 0) {
    canvas.hidden = true;

    empty.hidden = false;

    topCategoryText.textContent = "No category data";

    topCategoryShare.textContent = "0%";

    return;
  }

  canvas.hidden = false;

  empty.hidden = true;

  const labels = categories.map((category) => category.name);

  const values = categories.map((category) => Number(category.amount));

  const colors = categories.map((category) => category.color);

  categoryChartInstance = new Chart(canvas, {
    type: "doughnut",

    data: {
      labels,

      datasets: [
        {
          data: values,

          backgroundColor: colors,

          borderColor: "#121418",

          borderWidth: 3,

          hoverOffset: 5,
        },
      ],
    },

    options: {
      responsive: true,

      maintainAspectRatio: false,

      cutout: "75%",

      plugins: {
        legend: {
          display: false,
        },

        tooltip: {
          backgroundColor: "#1e222a",

          borderColor: "#333c4d",

          borderWidth: 1,

          padding: 11,

          callbacks: {
            label: function (context) {
              return `${context.label}: ${money(context.raw)}`;
            },
          },
        },
      },
    },
  });

  categories.forEach((category, index) => {
    const color =
      category.color || categoryPalette[index % categoryPalette.length];

    const row = document.createElement("div");

    row.className = "legend-row";

    row.innerHTML = `
      <span
        class="legend-color"
        style="background:${escapeHTML(color)}">
      </span>

      <span class="legend-name">
        ${escapeHTML(category.name)}
      </span>

      <span class="legend-amount">
        ${escapeHTML(money(category.amount))}
      </span>

      <span class="legend-percent">
        ${Number(category.percentage).toFixed(1)}%
      </span>
    `;

    legend.appendChild(row);
  });

  const top = categories[0];

  topCategoryText.textContent = top.name;

  topCategoryShare.textContent = `${Number(top.percentage).toFixed(1)}%`;
}

/* ==========================================================================
   10. PRODUCT ICONS
========================================================================== */

function getProductIcon(category) {
  const value = String(category || "").toLowerCase();

  if (value.includes("gpu") || value.includes("graphic")) {
    return "▣";
  }

  if (value.includes("cpu") || value.includes("processor")) {
    return "▦";
  }

  if (value.includes("ram") || value.includes("memory")) {
    return "▤";
  }

  if (value.includes("ssd") || value.includes("storage")) {
    return "▧";
  }

  if (value.includes("keyboard")) {
    return "⌨";
  }

  if (value.includes("mouse")) {
    return "⌖";
  }

  if (value.includes("power") || value.includes("psu")) {
    return "ϟ";
  }

  return "▣";
}

/* ==========================================================================
   11. TOP SELLING PRODUCTS
========================================================================== */

function renderProducts(products) {
  const tbody = document.getElementById("topProductsBody");

  tbody.innerHTML = "";

  if (!Array.isArray(products) || products.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4">
          No product sales found for this period.
        </td>
      </tr>
    `;

    document.getElementById("productsCount").textContent = "No product data";

    return;
  }

  products.forEach((product) => {
    const row = document.createElement("tr");

    row.innerHTML = `
      <td>
        <div class="product-cell">

          <span class="product-thumb">
            ${getProductIcon(product.category)}
          </span>

          <span class="product-name">
            <strong>
              ${escapeHTML(product.name)}
            </strong>

            <small>
              ${escapeHTML(product.brand || product.category)}
            </small>
          </span>

        </div>
      </td>

      <td>
        ${escapeHTML(money(product.revenue))}
      </td>

      <td>
        ${numberFormat(product.units)}
      </td>

      <td>
        <div class="performance-wrap">

          <span class="performance-track">
            <span style="width:${Math.min(
              Math.max(Number(product.performance), 0),
              100,
            )}%"></span>
          </span>

          <small>
            ${Number(product.performance)}%
          </small>

        </div>
      </td>
    `;

    tbody.appendChild(row);
  });

  document.getElementById("productsCount").textContent =
    `Showing ${products.length} products`;
}

/* ==========================================================================
   12. INVENTORY ALERTS
========================================================================== */

function renderInventory(items) {
  const container = document.getElementById("inventoryList");

  document.getElementById("alertCount").textContent = Array.isArray(items)
    ? items.length
    : 0;

  container.innerHTML = "";

  if (!Array.isArray(items) || items.length === 0) {
    container.innerHTML = `
      <div class="empty-state">

        <span style="color:#a3e635;font-size:20px">✓</span>

        <div>
          All products have sufficient stock.
        </div>

      </div>
    `;

    return;
  }

  items.forEach((item) => {
    const critical = item.state === "Critical";

    const row = document.createElement("div");

    row.className = "inventory-item";

    row.innerHTML = `
      <span class="stock-symbol">
        ${getProductIcon(item.category)}
      </span>

      <span class="stock-info">

        <strong>
          ${escapeHTML(item.name)}
        </strong>

        <small>
          ${escapeHTML(item.brand || item.category)}
        </small>

        <span class="stock-pill ${critical ? "critical" : "low"}">
          ${escapeHTML(item.state)}
        </span>

      </span>

      <span class="stock-level">

        <strong>
          ${numberFormat(item.stock)} left
        </strong>

        <small>
          Low stock threshold: 5
        </small>

      </span>
    `;

    container.appendChild(row);
  });
}

/* ==========================================================================
   13. RECENT ORDERS
========================================================================== */

function renderOrders() {
  const searchValue = elements.orderSearch.value.trim().toLowerCase();

  const selectedStatus = elements.statusFilter.value;

  const filteredOrders = allOrders.filter((order) => {
    const statusMatch =
      selectedStatus === "all" || order.status === selectedStatus;

    const searchableText = [order.id, order.customer, order.email, order.status]
      .join(" ")
      .toLowerCase();

    const searchMatch = searchableText.includes(searchValue);

    return statusMatch && searchMatch;
  });

  elements.ordersBody.innerHTML = "";

  elements.ordersEmpty.hidden = filteredOrders.length > 0;

  filteredOrders.forEach((order) => {
    const row = document.createElement("tr");

    const statusClass = String(order.status).toLowerCase();

    row.innerHTML = `
      <td>
        <span class="order-id">
          #${escapeHTML(order.id)}
        </span>
      </td>

      <td>
        <div class="customer-cell">

          <span class="customer-avatar">
            ${escapeHTML(order.initials || "U")}
          </span>

          <span class="customer-name">

            <strong>
              ${escapeHTML(order.customer)}
            </strong>

            <small>
              ${escapeHTML(order.email)}
            </small>

          </span>

        </div>
      </td>

      <td>
        ${escapeHTML(order.date)}
      </td>

      <td>
        ${numberFormat(order.items)}
        ${Number(order.items) === 1 ? "item" : "items"}
      </td>

      <td>
        ${escapeHTML(money(order.amount))}
      </td>

      <td>
        <span class="status-badge ${escapeHTML(statusClass)}">
          ${escapeHTML(order.status)}
        </span>
      </td>
    `;

    elements.ordersBody.appendChild(row);
  });

  document.getElementById("ordersCountLabel").textContent =
    filteredOrders.length > 0
      ? `Showing ${filteredOrders.length} of ${allOrders.length} latest orders`
      : "No orders found";
}

/* ==========================================================================
   14. EVENT LISTENERS
========================================================================== */

/* Reporting Period */

elements.periodForm?.addEventListener("submit", function (event) {
  event.preventDefault();

  loadDashboard();
});

/* Refresh Dashboard */

elements.refreshButton?.addEventListener("click", function () {
  loadDashboard();

  showToast("Refreshing dashboard...");
});

/* Retry */

elements.retryButton?.addEventListener("click", loadDashboard);

/* Search Orders */

elements.orderSearch?.addEventListener("input", renderOrders);

/* Filter Orders */

elements.statusFilter?.addEventListener("change", renderOrders);

/* Clear Filters */

document.getElementById("clearFilters")?.addEventListener("click", function () {
  elements.orderSearch.value = "";

  elements.statusFilter.value = "all";

  renderOrders();
});

/* ==========================================================================
   15. INITIALIZATION
========================================================================== */

function initializeDashboard() {
  const params = new URLSearchParams(window.location.search);

  const initialPeriod = params.get("period");

  if (["7", "30", "90"].includes(initialPeriod)) {
    elements.periodSelect.value = initialPeriod;
  }

  loadDashboard();
}

document.addEventListener("DOMContentLoaded", initializeDashboard);
