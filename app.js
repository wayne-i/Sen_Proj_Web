"use strict";

const API_URL = "api/index.php";
const state = {
  csrfToken: "",
  user: null,
  permissions: {},
  dashboard: {},
  rooms: [],
  roomTypes: [],
  guests: [],
  stays: [],
  employees: [],
  payments: [],
  expenses: [],
  activity: [],
  currentPage: "dashboard",
  report: null
};

const modalInstances = {};
let toastInstance;
let loginPending = false;

const pageLabels = {
  dashboard: "Dashboard",
  rooms: "Rooms",
  guests: "Guests",
  stays: "Check-in / out",
  payments: "Payments",
  employees: "Employees",
  expenses: "Expenses",
  reports: "Reports",
  activity: "Activity log"
};

function escapeHTML(value) {
  return String(value ?? "").replace(/[&<>"']/g, character => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[character]);
}

function money(value) {
  return new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(value) || 0);
}

function dateValue(value) {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return escapeHTML(value);
  return date.toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
}

function dateTimeValue(value) {
  if (!value) return "—";
  const date = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(date.getTime())) return escapeHTML(value);
  return date.toLocaleString("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}

function stayFor(id) {
  return state.stays.find(item => String(item.id) === String(id));
}

function roomFor(id) {
  return state.rooms.find(item => String(item.id) === String(id));
}

function roomForStay(stay) {
  return stay.roomNumber ? { number: stay.roomNumber, type: stay.roomType } : roomFor(stay.roomId);
}

function nightsBetween(start, end) {
  if (!start || !end) return 0;
  const first = new Date(`${start}T12:00:00`);
  const last = new Date(`${end}T12:00:00`);
  const nights = Math.round((last - first) / 86400000);
  return Number.isFinite(nights) ? nights : 0;
}

function toast(message) {
  document.getElementById("toastMessage").textContent = message;
  toastInstance.show();
}

function setAuthScreen() {
  document.getElementById("dashboard").classList.add("d-none");
  document.getElementById("authScreen").classList.remove("d-none");
}

function showSetup() {
  document.getElementById("loginForm").classList.add("d-none");
  document.getElementById("setupForm").classList.remove("d-none");
  document.getElementById("setupName").focus();
}

function showLogin() {
  document.getElementById("setupForm").classList.add("d-none");
  document.getElementById("loginForm").classList.remove("d-none");
  document.getElementById("loginEmail").focus();
}

async function api(action, options = {}) {
  const method = options.method || "GET";
  const url = new URL(API_URL, window.location.href);
  url.searchParams.set("action", action);
  Object.entries(options.query || {}).forEach(([key, value]) => {
    if (value !== "" && value !== null && value !== undefined) url.searchParams.set(key, value);
  });
  const headers = { Accept: "application/json" };
  if (options.body !== undefined) headers["Content-Type"] = "application/json";
  if (method !== "GET" && !["login", "setup"].includes(action)) headers["X-CSRF-Token"] = state.csrfToken;
  const response = await fetch(url, {
    method,
    credentials: "same-origin",
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body)
  });
  let result;
  try {
    result = await response.json();
  } catch (error) {
    console.error("API returned a response that was not JSON.", error);
    throw new Error("The server returned an invalid response. Check the PHP server and database.");
  }
  if (!response.ok) {
    if (response.status === 401 && action !== "login") setAuthScreen();
    throw new Error(result.error || "The request could not be completed.");
  }
  return result;
}

async function loadApplicationData() {
  const result = await api("data");
  Object.assign(state, result.data);
  state.csrfToken = result.data.csrfToken || state.csrfToken;
  document.getElementById("profileName").textContent = state.user.name;
  document.getElementById("profileRole").textContent = state.user.role === "admin" ? "Administrator" : "Staff";
  document.getElementById("profileAvatar").textContent = state.user.name.split(/\s+/).slice(0, 2).map(part => part[0]).join("").toUpperCase();
  document.querySelectorAll("[data-admin-only]").forEach(element => {
    element.classList.toggle("d-none", !state.permissions.manageEmployees);
  });
}

function showApiError(elementId, error) {
  const element = document.getElementById(elementId);
  element.textContent = error.message;
  element.classList.remove("d-none");
}

function setSubmitting(form, isSubmitting) {
  form.querySelectorAll('[type="submit"]').forEach(button => {
    button.disabled = isSubmitting;
    button.dataset.originalText ||= button.textContent;
    button.textContent = isSubmitting ? "Saving..." : button.dataset.originalText;
  });
}

async function submitForm(form, errorId, action, payload, afterSave) {
  const error = document.getElementById(errorId);
  error.classList.add("d-none");
  setSubmitting(form, true);
  try {
    await api(action, { method: "POST", body: payload });
    await loadApplicationData();
    afterSave();
  } catch (requestError) {
    showApiError(errorId, requestError);
  } finally {
    setSubmitting(form, false);
  }
}

function modal(id) {
  return modalInstances[id];
}

function showModal(id) {
  modal(id).show();
}

function hideModal(id) {
  modal(id).hide();
}

function clearForm(formId, errorIds = []) {
  const form = document.getElementById(formId);
  form.reset();
  form.querySelectorAll('input[type="hidden"]').forEach(input => { input.value = ""; });
  errorIds.forEach(id => document.getElementById(id).classList.add("d-none"));
  form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
}

function confirmation(message) {
  return window.confirm(message);
}

function heading(kicker, title, description, actions = "") {
  return `<div class="page-heading"><div><span class="section-kicker">${kicker}</span><h1>${title}</h1><p>${description}</p></div><div class="heading-actions">${actions}</div></div>`;
}

function emptyRow(columns, message = "No records found.") {
  return `<tr><td class="empty-state" colspan="${columns}">${message}</td></tr>`;
}

function statusBadge(status) {
  const className = String(status || "").toLowerCase().replace(/\s+/g, "-");
  return `<span class="status-badge status-${escapeHTML(className)}">${escapeHTML(status || "—")}</span>`;
}

function renderRoomOverview() {
  const emptyState = `
    <div class="col-12">
      <div class="empty-state p-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
        <span>No rooms added yet. Add a room to begin managing inventory.</span>
        <button class="btn btn-primary btn-sm" type="button" data-action="add-room">＋ Add room</button>
      </div>
    </div>
  `;

  const rooms = state.rooms.length
    ? state.rooms.slice(0, 8).map(room => `
      <div class="col-12 col-sm-6 col-xl-3">
        <article class="room-card h-100">
          <div class="room-card-top">
            <div>
              <h3>${escapeHTML(room.number)}</h3>
              <div class="room-number">${escapeHTML(room.type)}</div>
            </div>
            ${statusBadge(room.status)}
          </div>
          <div class="room-rate">${money(room.rate)} <small>/ night</small></div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="small text-secondary">${escapeHTML(room.status)}</span>
            <button class="btn btn-link btn-sm p-0" type="button" data-action="edit-room" data-id="${room.id}">Manage</button>
          </div>
        </article>
      </div>
    `).join("")
    : emptyState;

  return `
    <div class="row g-3 mb-3">
      <div class="col-12">
        <section class="content-card">
          <div class="card-heading">
            <div>
              <h2>Room overview</h2>
              <p>Live view of current room conditions and nightly rates.</p>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-page="rooms">All rooms</button>
          </div>
          <div class="p-3">
            <div class="row g-3">${rooms}</div>
          </div>
        </section>
      </div>
    </div>
  `;
}

function renderDashboard() {
  const summary = state.dashboard;
  const roomSummary = summary.rooms || {};
  const cards = [
    ["Total rooms", summary.totalRooms || 0, "Registered hotel rooms", "⌂"],
    ["Available rooms", roomSummary.Available || 0, "Ready for walk-in check-in", "✓"],
    ["Occupied rooms", roomSummary.Occupied || 0, "Currently checked in", "▣"],
    ["Under maintenance", roomSummary["Under Maintenance"] || 0, "Unavailable rooms", "⚒"],
    ["Total guests", summary.totalGuests || 0, "Registered guest records", "♙"],
    ["Today's check-ins", summary.checkInsToday || 0, "Manual arrivals today", "↓"],
    ["Today's check-outs", summary.checkOutsToday || 0, "Completed departures today", "↑"],
    ["Today's revenue", money(summary.revenueToday), "Payments received today", "₱"]
  ];
  return `${heading("HOTEL OPERATIONS", "Dashboard", "Today's rooms, guests, arrivals, departures, and hotel revenue.", '<button class="btn btn-primary btn-sm" data-action="checkin">＋ Walk-in check-in</button>')}
    <div class="row g-3 mb-3">${cards.map(([label, value, note, icon]) => `<div class="col-12 col-sm-6 col-xl-3"><article class="stat-card h-100"><div class="stat-label"><span>${label}</span><span class="stat-icon">${icon}</span></div><div class="stat-value">${value}</div><div class="stat-note">${note}</div></article></div>`).join("")}</div>
    ${renderRoomOverview()}
    <div class="row g-3">
      <div class="col-12 col-xl-7"><section class="content-card h-100"><div class="card-heading"><div><h2>Recent check-ins</h2><p>Latest manually recorded guest stays.</p></div><button class="btn btn-sm btn-outline-secondary" data-page="stays">All stays</button></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>GUEST</th><th>ROOM</th><th>CHECK-IN</th><th>EXPECTED CHECK-OUT</th><th>STATUS</th></tr></thead><tbody>${renderRecentStays()}</tbody></table></div></section></div>
      <div class="col-12 col-xl-5"><section class="content-card h-100"><div class="card-heading"><div><h2>Revenue — last 7 days</h2><p>Payments received by day.</p></div><button class="btn btn-sm btn-outline-secondary" data-page="reports">Reports</button></div>${renderRevenueChart(summary.weeklyRevenue || [])}</section></div>
    </div>`;
}

function renderRecentStays() {
  const items = state.stays.slice(0, 6);
  if (!items.length) return emptyRow(5, "No check-ins recorded yet. Start by adding a walk-in guest.");
  return items.map(stay => `<tr><td><strong class="guest-name">${escapeHTML(stay.guestName)}</strong><span class="guest-email">${escapeHTML(stay.guestCode)}</span></td><td>${escapeHTML(stay.roomNumber)} · ${escapeHTML(stay.roomType)}</td><td>${dateTimeValue(stay.checkedInAt)}</td><td>${dateValue(stay.expectedCheckout)}</td><td>${statusBadge(stay.status)}</td></tr>`).join("");
}

function renderRevenueChart(rows) {
  const totals = new Map(rows.map(row => [row.day, Number(row.total)]));
  const days = [];
  for (let offset = 6; offset >= 0; offset--) {
    const date = new Date();
    date.setDate(date.getDate() - offset);
    const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    days.push({ key, label: date.toLocaleDateString("en-PH", { weekday: "short" }), total: totals.get(key) || 0 });
  }
  const max = Math.max(1, ...days.map(day => day.total));
  return `<div class="chart-wrap"><div class="bar-chart">${days.map(day => {
    const height = day.total ? Math.max(4, Math.round(day.total / max * 100)) : 2;
    return `<div class="chart-column" title="${escapeHTML(day.key)} · ${money(day.total)}"><span class="chart-value">${day.total ? money(day.total) : "—"}</span><div class="chart-bar" style="height:${height}%"></div><span class="chart-label">${day.label}</span></div>`;
  }).join("")}</div></div>`;
}

function renderRooms() {
  const actions = '<button class="btn btn-primary btn-sm" data-action="add-room">＋ Add room</button>';
  const rows = state.rooms.length ? state.rooms.map(room => `<tr>
    <td><strong>${escapeHTML(room.number)}</strong></td><td>${escapeHTML(room.type)}</td><td>${money(room.rate)}</td><td>${statusBadge(room.status)}</td>
    <td><div class="action-buttons">
      ${room.status === "Cleaning" ? `<button class="btn btn-outline-success" data-action="room-clean" data-id="${room.id}">Ready</button>` : ""}
      ${room.status !== "Occupied" ? `<button class="btn btn-outline-secondary" data-action="edit-room" data-id="${room.id}">Edit</button><button class="btn btn-outline-danger" data-action="delete-room" data-id="${room.id}">Delete</button>` : '<span class="small text-secondary">In use</span>'}
    </div></td></tr>`).join("") : emptyRow(5, "No rooms added yet. Add a room to begin managing inventory.");
  return `${heading("ROOM MANAGEMENT", "Rooms", "Manage room type, nightly rate, and operating status.", actions)}
    <section class="content-card"><div class="card-heading"><div><h2>Room inventory</h2><p>Occupied status changes automatically through guest check-in and check-out.</p></div><div class="search-wrap"><input class="form-control form-control-sm" id="roomSearch" type="search" placeholder="Search rooms" aria-label="Search rooms"></div></div>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>ROOM</th><th>TYPE</th><th>RATE / NIGHT</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody id="roomsTableBody">${rows}</tbody></table></div></section>`;
}

function renderGuests() {
  const rows = state.guests.length ? state.guests.map(guest => `<tr>
    <td><strong class="guest-name">${escapeHTML(guest.fullName)}</strong><span class="guest-email">${escapeHTML(guest.guestCode)}</span></td>
    <td>${escapeHTML(guest.phone)}</td><td>${escapeHTML(guest.email || "—")}</td><td>${escapeHTML(guest.idType || "—")}</td>
    <td><div class="action-buttons"><button class="btn btn-outline-primary" data-action="guest-history" data-id="${guest.id}">Profile / history</button><button class="btn btn-outline-secondary" data-action="edit-guest" data-id="${guest.id}">Edit</button><button class="btn btn-outline-danger" data-action="delete-guest" data-id="${guest.id}">Archive</button></div></td>
  </tr>`).join("") : emptyRow(5, "No guests registered yet. Add a walk-in guest or register one during check-in.");
  return `${heading("GUEST RECORDS", "Guests", "Register walk-in guests and review their hotel stay history.", '<button class="btn btn-primary btn-sm" data-action="add-guest">＋ Register guest</button>')}
    <section class="content-card"><div class="card-heading"><div><h2>Guest directory</h2><p>Search by name, contact number, or guest ID.</p></div><div class="search-wrap"><input class="form-control form-control-sm" id="guestSearch" type="search" placeholder="Search guests" aria-label="Search guests"></div></div>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>GUEST</th><th>CONTACT</th><th>EMAIL</th><th>ID TYPE</th><th>ACTIONS</th></tr></thead><tbody id="guestsTableBody">${rows}</tbody></table></div></section>`;
}

function renderStays() {
  const actions = '<button class="btn btn-primary btn-sm" data-action="checkin">＋ Walk-in check-in</button><button class="btn btn-outline-primary btn-sm" data-action="checkout">Guest check-out</button>';
  const rows = state.stays.length ? state.stays.map(stay => `<tr data-status="${escapeHTML(stay.status)}">
    <td><strong class="guest-name">${escapeHTML(stay.guestName)}</strong><span class="guest-email">${escapeHTML(stay.checkinCode)}</span></td>
    <td>${escapeHTML(stay.roomNumber)} · ${escapeHTML(stay.roomType)}</td><td>${dateTimeValue(stay.checkedInAt)}</td><td>${dateValue(stay.expectedCheckout)}</td>
    <td>${statusBadge(stay.status)}</td><td>${money(stay.finalCharge ?? stay.expectedRoomCharge)}</td>
    <td>${money(stay.paidAmount)}</td><td>${money(stayBalance(stay))}</td>
    <td><div class="action-buttons">${stay.status === "Checked-in" ? `<button class="btn btn-outline-primary" data-action="checkout" data-id="${stay.id}">Check out</button>` : `<button class="btn btn-outline-secondary" data-action="stay-history" data-id="${stay.id}">Details</button>`}${stayBalance(stay) > 0 ? `<button class="btn btn-outline-success" data-action="collect-payment" data-id="${stay.id}">Payment</button>` : ""}</div></td>
  </tr>`).join("") : emptyRow(9, "No guest stays have been recorded.");
  return `${heading("MANUAL FRONT DESK", "Check-in / check-out", "Walk-in operations only. No online booking or reservation workflow.", actions)}
    <section class="content-card"><div class="card-heading"><div><h2>Guest stays</h2><p>Check-in sets the room to Occupied; completed check-out sets it to Cleaning.</p></div>
      <div class="filter-field"><label for="stayStatusFilter">Status</label><select class="form-select form-select-sm" id="stayStatusFilter"><option value="">All stay records</option><option>Checked-in</option><option>Checked-out</option><option>Cancelled</option></select></div>
    </div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>GUEST / STAY</th><th>ROOM</th><th>CHECKED IN</th><th>EXPECTED OUT</th><th>STATUS</th><th>CHARGE</th><th>PAID</th><th>BALANCE</th><th>ACTION</th></tr></thead><tbody id="staysTableBody">${rows}</tbody></table></div></section>`;
}

function renderPayments() {
  const rows = state.payments.length ? state.payments.map(payment => `<tr>
    <td><strong>${escapeHTML(payment.receiptCode)}</strong></td><td>${dateTimeValue(payment.paidAt)}</td><td>${escapeHTML(payment.guestName)}</td>
    <td>${escapeHTML(payment.method)}</td><td>${statusBadge(payment.status)}</td><td>${money(payment.amount)}</td>
    <td><div class="action-buttons"><button class="btn btn-outline-secondary" data-action="print-receipt" data-id="${payment.id}">Receipt</button>${payment.status === "Paid" && state.user.role === "admin" ? `<button class="btn btn-outline-danger" data-action="refund" data-id="${payment.id}">Refund</button>` : ""}</div></td>
  </tr>`).join("") : emptyRow(7, "No payments recorded yet.");
  const balances = state.stays.filter(stay => stayBalance(stay) > 0).map(stay => {
    const charge = stay.finalCharge ?? stay.expectedRoomCharge;
    const paid = Number(stay.paidAmount) || 0;
    const status = paid > 0 ? "Partially paid" : "Unpaid";
    return `<tr><td><strong class="guest-name">${escapeHTML(stay.guestName)}</strong><span class="guest-email">${escapeHTML(stay.checkinCode)}</span></td>
      <td>${escapeHTML(stay.roomNumber)} · ${escapeHTML(stay.roomType)}</td><td>${money(charge)}</td><td>${money(paid)}</td><td>${money(stayBalance(stay))}</td><td>${statusBadge(status)}</td>
      <td><button class="btn btn-outline-success btn-sm" data-action="collect-payment" data-id="${stay.id}">Collect payment</button></td></tr>`;
  }).join("") || emptyRow(7, "No unpaid balances.");
  return `${heading("BILLING", "Payments & billing", "Record cash, GCash, and card payments; view and print receipts.", '<button class="btn btn-primary btn-sm" data-action="add-payment">＋ Record payment</button>')}
    <section class="content-card mb-3"><div class="card-heading"><div><h2>Outstanding balances</h2><p>Unpaid and partially paid guest stays.</p></div></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>GUEST / STAY</th><th>ROOM</th><th>CHARGE</th><th>PAID</th><th>BALANCE</th><th>PAYMENT STATUS</th><th>ACTION</th></tr></thead><tbody>${balances}</tbody></table></div></section>
    <section class="content-card"><div class="card-heading"><div><h2>Payment transactions</h2><p>Financial records are retained; refunds are recorded as status changes.</p></div><div class="search-wrap"><input class="form-control form-control-sm" id="paymentSearch" type="search" placeholder="Search guest or receipt" aria-label="Search payments"></div></div>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>RECEIPT</th><th>DATE</th><th>GUEST</th><th>METHOD</th><th>STATUS</th><th>AMOUNT</th><th>ACTIONS</th></tr></thead><tbody id="paymentsTableBody">${rows}</tbody></table></div></section>`;
}

function renderEmployees() {
  const rows = state.employees.length ? state.employees.map(employee => `<tr>
    <td><strong class="guest-name">${escapeHTML(employee.fullName)}</strong><span class="guest-email">${escapeHTML(employee.employeeCode)}</span></td>
    <td>${escapeHTML(employee.position)}</td><td>${escapeHTML(employee.email || "No login account")}</td>
    <td>${employee.userId ? escapeHTML(employee.role) : "—"}</td><td>${Number(employee.active) ? statusBadge("Active") : statusBadge("Inactive")}</td>
    <td><div class="action-buttons"><button class="btn btn-outline-secondary" data-action="edit-employee" data-id="${employee.id}">Edit</button><button class="btn btn-outline-danger" data-action="delete-employee" data-id="${employee.id}">Deactivate</button></div></td>
  </tr>`).join("") : emptyRow(6, "No employees have been added.");
  return `${heading("STAFF MANAGEMENT", "Employees", "Manage staff profiles, system roles, and active accounts.", '<button class="btn btn-primary btn-sm" data-action="add-employee">＋ Add employee</button>')}
    <section class="content-card"><div class="card-heading"><div><h2>Hotel employees</h2><p>Admin accounts can manage employee access. Staff cannot manage accounts.</p></div></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>EMPLOYEE</th><th>POSITION</th><th>ACCOUNT</th><th>ROLE</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
}

function renderExpenses() {
  const rows = state.expenses.length ? state.expenses.map(expense => `<tr>
    <td>${dateValue(expense.expenseDate)}</td><td><strong>${escapeHTML(expense.category)}</strong><span class="guest-email">${escapeHTML(expense.description)}</span></td>
    <td>${escapeHTML(expense.receiptReference || "—")}</td><td>${money(expense.amount)}</td><td>${escapeHTML(expense.createdBy)}</td>
    <td><div class="action-buttons"><button class="btn btn-outline-secondary" data-action="edit-expense" data-id="${expense.id}">Edit</button><button class="btn btn-outline-danger" data-action="delete-expense" data-id="${expense.id}">Archive</button></div></td>
  </tr>`).join("") : emptyRow(6, "No expenses recorded.");
  return `${heading("EXPENSE TRACKING", "Expenses", "Track operating costs and retain records for reporting.", '<button class="btn btn-primary btn-sm" data-action="add-expense">＋ Add expense</button>')}
    <section class="content-card"><div class="card-heading"><div><h2>Operating expenses</h2><p>Expense records appear in date-filtered reports.</p></div></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>DATE</th><th>CATEGORY / DESCRIPTION</th><th>RECEIPT</th><th>AMOUNT</th><th>RECORDED BY</th><th>ACTIONS</th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
}

function renderReports() {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  return `${heading("HOTEL PERFORMANCE", "Reports", "Filter and export revenue, occupancy, guest, stay, payment, expense, and staff activity reports.")}
    <section class="content-card mb-3"><div class="card-heading"><div><h2>Report filters</h2><p>Select a report and date range, then view or export the records.</p></div></div>
      <div class="px-3 pb-3"><div class="report-filters">
        <div class="filter-field"><label for="reportType">Report</label><select class="form-select form-select-sm" id="reportType"><option value="revenue">Daily / monthly revenue</option><option value="occupancy">Room occupancy & stays</option><option value="guests">Guest records</option><option value="stays">Check-in / check-out history</option><option value="payments">Payment transactions</option><option value="expenses">Operating expenses</option><option value="activity">Employee activity</option></select></div>
        <div class="filter-field"><label for="reportFrom">From</label><input class="form-control form-control-sm" id="reportFrom" type="date" value="${dateInput(firstDay)}"></div>
        <div class="filter-field"><label for="reportTo">To</label><input class="form-control form-control-sm" id="reportTo" type="date" value="${dateInput(today)}"></div>
        <button class="btn btn-primary btn-sm" data-action="run-report">Run report</button><button class="btn btn-outline-secondary btn-sm" data-action="export-report" disabled id="exportReportButton">Export CSV</button>
      </div><div class="alert alert-danger mt-3 mb-0 d-none" id="reportError" role="alert"></div></div>
    </section><div id="reportResult"></div>`;
}

function dateInput(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function renderActivity() {
  const rows = state.activity.length ? state.activity.map(item => `<tr><td>${dateTimeValue(item.createdAt)}</td><td>${escapeHTML(item.userName || "System")}</td><td>${escapeHTML(item.action)}</td><td>${escapeHTML(item.entityType)}${item.entityId ? ` #${item.entityId}` : ""}</td><td>${escapeHTML(JSON.stringify(item.details || {}))}</td></tr>`).join("") : emptyRow(5, "No activity logged.");
  return `${heading("SYSTEM AUDIT", "Activity log", "Timestamped records of administrator and staff changes.")}<section class="content-card"><div class="card-heading"><div><h2>Recent activity</h2><p>Latest 30 recorded system actions.</p></div></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>DATE / TIME</th><th>STAFF</th><th>ACTION</th><th>RECORD</th><th>DETAILS</th></tr></thead><tbody>${rows}</tbody></table></div></section>`;
}

function renderPage() {
  const page = state.currentPage;
  document.getElementById("currentPageLabel").textContent = pageLabels[page];
  document.querySelectorAll("[data-page]").forEach(button => button.classList.toggle("active", button.dataset.page === page));
  const renderers = {
    dashboard: renderDashboard,
    rooms: renderRooms,
    guests: renderGuests,
    stays: renderStays,
    payments: renderPayments,
    employees: renderEmployees,
    expenses: renderExpenses,
    reports: renderReports,
    activity: renderActivity
  };
  document.getElementById("pageContent").innerHTML = renderers[page]();
}

function fillRoomTypeSelect(selectedId = "") {
  const select = document.getElementById("roomType");
  select.innerHTML = '<option value="">Select room type</option>' + state.roomTypes
    .map(type => `<option value="${type.id}">${escapeHTML(type.name)}</option>`).join("");
  select.value = selectedId ? String(selectedId) : "";
}

function openRoomForm(id = "") {
  const form = document.getElementById("roomForm");
  clearForm("roomForm", ["roomError"]);
  const room = id ? roomFor(id) : null;
  fillRoomTypeSelect(room?.roomTypeId || "");
  document.getElementById("roomModalTitle").textContent = room ? "Edit room" : "Add room";
  if (room) {
    document.getElementById("roomId").value = room.id;
    document.getElementById("roomNumber").value = room.number;
    document.getElementById("roomRate").value = room.rate;
    document.getElementById("roomStatus").value = room.status;
  }
  showModal("roomModal");
}

async function saveRoom(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    id: document.getElementById("roomId").value,
    number: document.getElementById("roomNumber").value.trim(),
    typeId: document.getElementById("roomType").value,
    rate: document.getElementById("roomRate").value,
    status: document.getElementById("roomStatus").value
  };
  await submitForm(form, "roomError", "room_save", payload, () => {
    hideModal("roomModal");
    renderPage();
    toast("Room details saved.");
  });
}

function openGuestForm(id = "") {
  clearForm("guestForm", ["guestError"]);
  const guest = id ? state.guests.find(item => String(item.id) === String(id)) : null;
  document.getElementById("guestModalTitle").textContent = guest ? "Edit guest" : "Register guest";
  if (guest) {
    document.getElementById("guestId").value = guest.id;
    document.getElementById("guestFullName").value = guest.fullName;
    document.getElementById("guestPhone").value = guest.phone;
    document.getElementById("guestEmail").value = guest.email || "";
    document.getElementById("guestAddress").value = guest.address || "";
    document.getElementById("guestIdType").value = guest.idType || "";
    document.getElementById("guestIdNumber").value = guest.idNumber || "";
  }
  showModal("guestModal");
}

async function saveGuest(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    id: document.getElementById("guestId").value,
    fullName: document.getElementById("guestFullName").value.trim(),
    phone: document.getElementById("guestPhone").value.trim(),
    email: document.getElementById("guestEmail").value.trim(),
    address: document.getElementById("guestAddress").value.trim(),
    idType: document.getElementById("guestIdType").value,
    idNumber: document.getElementById("guestIdNumber").value.trim()
  };
  await submitForm(form, "guestError", "guest_save", payload, () => {
    hideModal("guestModal");
    renderPage();
    toast("Guest record saved.");
  });
}

function updateCheckinGuestFields() {
  const isNewGuest = document.getElementById("checkinGuest").value === "";
  document.querySelector(".checkin-guest-fields").classList.toggle("d-none", !isNewGuest);
  document.getElementById("walkinName").required = isNewGuest;
  document.getElementById("walkinPhone").required = isNewGuest;
}

function updateCheckinEstimate() {
  const room = roomFor(document.getElementById("checkinRoom").value);
  const nights = nightsBetween(document.getElementById("checkinDateOnly").value, document.getElementById("expectedCheckout").value);
  document.getElementById("checkinEstimate").textContent = money(room && nights > 0 ? room.rate * nights : 0);
  document.getElementById("checkinNightCount").textContent = nights > 0 ? `${nights} night${nights === 1 ? "" : "s"}` : "Enter check-in and check-out dates";
}

function openCheckinForm() {
  const availableRooms = state.rooms.filter(room => room.status === "Available");
  if (!availableRooms.length) {
    state.currentPage = "rooms";
    renderPage();
    openRoomForm();
    toast("No rooms are available. Add a room first to complete check-in.");
    return;
  }

  const form = document.getElementById("checkinForm");
  clearForm("checkinForm", ["checkinError"]);
  document.getElementById("checkinGuest").innerHTML = '<option value="">New walk-in guest</option>' + state.guests
    .map(guest => `<option value="${guest.id}">${escapeHTML(guest.fullName)} · ${escapeHTML(guest.phone)} · ${escapeHTML(guest.guestCode)}</option>`).join("");
  document.getElementById("checkinRoom").innerHTML = '<option value="">Select available room</option>' + availableRooms
    .map(room => `<option value="${room.id}">${escapeHTML(room.number)} · ${escapeHTML(room.type)} (${money(room.rate)}/night)</option>`).join("");
  updateCheckinGuestFields();
  updateCheckinEstimate();
  showModal("checkinModal");
}

async function saveCheckin(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const guestId = document.getElementById("checkinGuest").value;
  const payload = {
    roomId: document.getElementById("checkinRoom").value,
    checkedInAt: document.getElementById("checkedInAt").value,
    expectedCheckout: document.getElementById("expectedCheckout").value,
    guestCount: document.getElementById("stayGuestCount").value,
    notes: document.getElementById("checkinNotes").value.trim()
  };
  if (guestId) {
    payload.guestId = guestId;
  } else {
    payload.guestName = document.getElementById("walkinName").value.trim();
    payload.guestPhone = document.getElementById("walkinPhone").value.trim();
    payload.guestEmail = document.getElementById("walkinEmail").value.trim();
    payload.guestAddress = document.getElementById("walkinAddress").value.trim();
    payload.guestIdType = document.getElementById("walkinIdType").value;
    payload.guestIdNumber = document.getElementById("walkinIdNumber").value.trim();
  }
  await submitForm(form, "checkinError", "checkin", payload, () => {
    hideModal("checkinModal");
    renderPage();
    toast("Guest checked in. The room is now occupied.");
  });
}

function activeStays() {
  return state.stays.filter(stay => stay.status === "Checked-in");
}

function selectedStay() {
  return stayFor(document.getElementById("checkoutStay").value);
}

function checkoutEstimate() {
  const stay = selectedStay();
  if (!stay) return { nights: 0, charge: 0, paid: 0, due: 0 };
  const today = dateInputValue(new Date());
  const nights = Math.max(1, nightsBetween(String(stay.checkedInAt).slice(0, 10), today));
  const roomCharge = nights * Number(stay.nightlyRate);
  const additional = Number(document.getElementById("additionalCharges").value) || 0;
  const total = roomCharge + additional;
  const paid = Number(stay.paidAmount) || 0;
  return { nights, charge: roomCharge, paid, due: Math.max(0, total - paid), total };
}

function updateCheckoutSummary() {
  const stay = selectedStay();
  const container = document.getElementById("checkoutDetails");
  if (!stay) {
    container.textContent = "Select a guest to review room and charges.";
    return;
  }
  const estimate = checkoutEstimate();
  container.innerHTML = `<strong>${escapeHTML(stay.guestName)}</strong> · Room ${escapeHTML(stay.roomNumber)} (${escapeHTML(stay.roomType)})<br>
    Check-in: ${dateTimeValue(stay.checkedInAt)} · Estimated ${estimate.nights} night(s)<br>
    Room charge: ${money(estimate.charge)} · Previously paid: ${money(estimate.paid)}<br>
    <strong>Estimated remaining: ${money(estimate.due)}</strong>`;
  const payment = document.getElementById("checkoutPayment");
  payment.max = estimate.due.toFixed(2);
}

function openCheckoutForm(stayId = "") {
  clearForm("checkoutForm", ["checkoutError"]);
  const select = document.getElementById("checkoutStay");
  select.innerHTML = '<option value="">Select checked-in guest</option>' + activeStays()
    .map(stay => `<option value="${stay.id}">${escapeHTML(stay.guestName)} · Room ${escapeHTML(stay.roomNumber)} · ${escapeHTML(stay.checkinCode)}</option>`).join("");
  if (stayId) select.value = String(stayId);
  document.getElementById("checkoutDetails").textContent = "Select a guest to review room and charges.";
  updateCheckoutSummary();
  showModal("checkoutModal");
}

async function saveCheckout(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const amount = Number(document.getElementById("checkoutPayment").value || 0);
  const paymentMethod = document.getElementById("checkoutPaymentMethod").value;
  if (amount > 0 && !paymentMethod) {
    showApiError("checkoutError", new Error("Select the payment method for the amount received."));
    return;
  }
  const payload = {
    checkinId: document.getElementById("checkoutStay").value,
    additionalCharges: document.getElementById("additionalCharges").value || "0",
    paymentAmount: document.getElementById("checkoutPayment").value || "0",
    paymentMethod,
    notes: document.getElementById("checkoutNotes").value.trim()
  };
  setSubmitting(form, true);
  document.getElementById("checkoutError").classList.add("d-none");
  try {
    const result = await api("checkout", { method: "POST", body: payload });
    await loadApplicationData();
    hideModal("checkoutModal");
    renderPage();
    toast(result.message);
    printCheckout(result, stayFor(payload.checkinId));
  } catch (error) {
    showApiError("checkoutError", error);
  } finally {
    setSubmitting(form, false);
  }
}

function stayBalance(stay) {
  const totalCharge = stay.finalCharge === null || stay.finalCharge === undefined
    ? Number(stay.expectedRoomCharge)
    : Number(stay.finalCharge);
  return Math.max(0, totalCharge - Number(stay.paidAmount || 0));
}

function openPaymentForm(stayId = "") {
  clearForm("paymentForm", ["paymentError"]);
  const select = document.getElementById("paymentStay");
  const payable = state.stays.filter(stay => stayBalance(stay) > 0);
  select.innerHTML = '<option value="">Select stay</option>' + payable
    .map(stay => `<option value="${stay.id}">${escapeHTML(stay.guestName)} · ${escapeHTML(stay.checkinCode)} · Due ${money(stayBalance(stay))}</option>`).join("");
  if (stayId) select.value = String(stayId);
  showModal("paymentModal");
}

async function savePayment(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    checkinId: document.getElementById("paymentStay").value,
    amount: document.getElementById("paymentAmount").value,
    method: document.getElementById("paymentMethod").value,
    notes: document.getElementById("paymentNotes").value.trim()
  };
  await submitForm(form, "paymentError", "payment", payload, () => {
    hideModal("paymentModal");
    renderPage();
    toast("Payment recorded.");
  });
}

function openEmployeeForm(id = "") {
  clearForm("employeeForm", ["employeeError"]);
  const employee = id ? state.employees.find(item => String(item.id) === String(id)) : null;
  document.getElementById("employeeModalTitle").textContent = employee ? "Edit employee" : "Add employee";
  document.getElementById("passwordRequirement").textContent = employee ? "(leave blank to keep existing password)" : "(required when creating a login; minimum 12 characters)";
  if (employee) {
    document.getElementById("employeeId").value = employee.id;
    document.getElementById("employeeName").value = employee.fullName;
    document.getElementById("employeeEmail").value = employee.email || "";
    document.getElementById("employeePhone").value = employee.phone || "";
    document.getElementById("employeePosition").value = employee.position;
    document.getElementById("employeeRole").value = employee.role || "staff";
    document.getElementById("employeeActive").value = String(Number(employee.active));
  }
  showModal("employeeModal");
}

async function saveEmployee(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    id: document.getElementById("employeeId").value,
    fullName: document.getElementById("employeeName").value.trim(),
    email: document.getElementById("employeeEmail").value.trim(),
    phone: document.getElementById("employeePhone").value.trim(),
    position: document.getElementById("employeePosition").value.trim(),
    role: document.getElementById("employeeRole").value,
    password: document.getElementById("employeePassword").value,
    active: document.getElementById("employeeActive").value
  };
  await submitForm(form, "employeeError", "employee_save", payload, () => {
    hideModal("employeeModal");
    renderPage();
    toast("Employee record saved.");
  });
}

function openExpenseForm(id = "") {
  clearForm("expenseForm", ["expenseError"]);
  const expense = id ? state.expenses.find(item => String(item.id) === String(id)) : null;
  document.getElementById("expenseModalTitle").textContent = expense ? "Edit expense" : "Add expense";
  if (expense) {
    document.getElementById("expenseId").value = expense.id;
    document.getElementById("expenseCategory").value = expense.category;
    document.getElementById("expenseDescription").value = expense.description;
    document.getElementById("expenseAmount").value = expense.amount;
    document.getElementById("expenseDate").value = expense.expenseDate;
    document.getElementById("expenseReference").value = expense.receiptReference || "";
  }
  showModal("expenseModal");
}

async function saveExpense(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    id: document.getElementById("expenseId").value,
    category: document.getElementById("expenseCategory").value.trim(),
    description: document.getElementById("expenseDescription").value.trim(),
    amount: document.getElementById("expenseAmount").value,
    expenseDate: document.getElementById("expenseDate").value,
    receiptReference: document.getElementById("expenseReference").value.trim()
  };
  await submitForm(form, "expenseError", "expense_save", payload, () => {
    hideModal("expenseModal");
    renderPage();
    toast("Expense saved.");
  });
}

function printDocument(title, content) {
  const printWindow = window.open("", "_blank", "width=800,height=700");
  if (!printWindow) {
    toast("Allow pop-ups to print this document.");
    return;
  }
  printWindow.document.write(`<!doctype html><html><head><title>${escapeHTML(title)}</title><style>body{font-family:Arial,sans-serif;max-width:760px;margin:35px auto;padding:0 20px;color:#24312b}h1{font-size:23px}h2{font-size:17px;margin-top:26px}.muted{color:#66736b;font-size:12px}.row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #ddd}.total{font-weight:bold;font-size:18px;margin-top:14px}.brand{color:#315d4c;font-weight:bold}@media print{button{display:none}}</style></head><body><p class="brand">ALING MAMENG BEACH RESORT</p>${content}<p class="muted">Printed ${escapeHTML(new Date().toLocaleString())}</p><button onclick="window.print()">Print</button></body></html>`);
  printWindow.document.close();
}

function printReceipt(id) {
  const payment = state.payments.find(item => String(item.id) === String(id));
  if (!payment) return;
  printDocument(`Receipt ${payment.receiptCode}`, `
    <h1>Payment receipt</h1><p class="muted">Receipt: ${escapeHTML(payment.receiptCode)}</p>
    <div class="row"><span>Guest</span><strong>${escapeHTML(payment.guestName)}</strong></div>
    <div class="row"><span>Stay reference</span><strong>${escapeHTML(payment.checkinCode)}</strong></div>
    <div class="row"><span>Payment date</span><strong>${dateTimeValue(payment.paidAt)}</strong></div>
    <div class="row"><span>Method</span><strong>${escapeHTML(payment.method)}</strong></div>
    <div class="row total"><span>Amount paid</span><strong>${money(payment.amount)}</strong></div>
  `);
}

function printCheckout(result, stay) {
  if (!stay) return;
  const room = roomForStay(stay) || {};
  printDocument(`Checkout statement ${stay.checkinCode}`, `
    <h1>Guest checkout statement</h1><p class="muted">Stay: ${escapeHTML(stay.checkinCode)}</p>
    <div class="row"><span>Guest</span><strong>${escapeHTML(stay.guestName)}</strong></div>
    <div class="row"><span>Room</span><strong>${escapeHTML(room.number || stay.roomNumber)} · ${escapeHTML(room.type || stay.roomType)}</strong></div>
    <div class="row"><span>Charged nights</span><strong>${result.nights}</strong></div>
    <div class="row"><span>Room charges</span><strong>${money(result.roomCharge)}</strong></div>
    <div class="row"><span>Additional charges</span><strong>${money(result.additionalCharges)}</strong></div>
    <div class="row total"><span>Total</span><strong>${money(result.total)}</strong></div>
    <div class="row"><span>Paid</span><strong>${money(result.paid)}</strong></div>
    <div class="row"><span>Balance</span><strong>${money(result.balance)}</strong></div>
  `);
}

function showGuestHistory(id) {
  const guest = state.guests.find(item => String(item.id) === String(id));
  if (!guest) return;
  const history = state.stays.filter(stay => String(stay.guestId) === String(id));
  const stayRows = history.length ? history.map(stay => {
    const room = roomForStay(stay) || {};
    return `<tr><td>${escapeHTML(stay.checkinCode)}</td><td>${escapeHTML(room.number || stay.roomNumber)}</td><td>${dateValue(stay.checkedInAt)}</td><td>${dateValue(stay.actualCheckoutAt || stay.expectedCheckout)}</td><td>${statusBadge(stay.status)}</td></tr>`;
  }).join("") : '<tr><td colspan="5" class="empty-state">No stay history for this guest.</td></tr>';
  document.getElementById("guestHistoryTitle").textContent = guest.fullName;
  document.getElementById("guestHistoryContent").innerHTML = `
    <div class="row g-2 mb-3"><div class="col-md-6"><strong>Guest ID</strong><div>${escapeHTML(guest.guestCode)}</div></div><div class="col-md-6"><strong>Contact</strong><div>${escapeHTML(guest.phone)}</div></div>
    <div class="col-md-6"><strong>Email</strong><div>${escapeHTML(guest.email || "—")}</div></div><div class="col-md-6"><strong>Address</strong><div>${escapeHTML(guest.address || "—")}</div></div>
    <div class="col-md-6"><strong>Valid ID</strong><div>${escapeHTML(guest.idType || "—")} ${escapeHTML(guest.idNumber || "")}</div></div></div>
    <h3 class="fs-6 mt-3">Check-in / check-out history</h3><div class="table-responsive"><table class="table table-sm"><thead><tr><th>STAY</th><th>ROOM</th><th>CHECKED IN</th><th>CHECKED OUT</th><th>STATUS</th></tr></thead><tbody>${stayRows}</tbody></table></div>`;
  showModal("guestHistoryModal");
}

function openCheckoutFromPage(id) {
  openCheckoutForm(id);
}

async function deleteRecord(action, id, message, successMessage) {
  if (!confirmation(message)) return;
  try {
    await api(action, { method: "POST", body: { id } });
    await loadApplicationData();
    renderPage();
    toast(successMessage);
  } catch (error) {
    toast(error.message);
  }
}

async function markRoomReady(id) {
  try {
    await api("room_clean", { method: "POST", body: { id } });
    await loadApplicationData();
    renderPage();
    toast("Room is now available.");
  } catch (error) {
    toast(error.message);
  }
}

async function refundPayment(id) {
  if (!confirmation("Refund this payment? The financial record will remain in the system.")) return;
  try {
    await api("refund", { method: "POST", body: { id } });
    await loadApplicationData();
    renderPage();
    toast("Payment marked as refunded.");
  } catch (error) {
    toast(error.message);
  }
}

async function runReport() {
  const error = document.getElementById("reportError");
  error.classList.add("d-none");
  try {
    const result = await api("reports", {
      query: {
        type: document.getElementById("reportType").value,
        from: document.getElementById("reportFrom").value,
        to: document.getElementById("reportTo").value
      }
    });
    state.report = result.report;
    renderReportResult(result.report);
    document.getElementById("exportReportButton").disabled = false;
  } catch (requestError) {
    showApiError("reportError", requestError);
  }
}

function renderReportResult(report) {
  const rows = report.rows || [];
  if (!rows.length) {
    document.getElementById("reportResult").innerHTML = '<section class="content-card empty-state">No records match the selected report dates.</section>';
    return;
  }
  const columns = Object.keys(rows[0]);
  const total = report.type === "revenue" ? rows.reduce((sum, row) => sum + Number(row.total || 0), 0) : null;
  const header = columns.map(column => `<th>${escapeHTML(column.replace(/_/g, " ").toUpperCase())}</th>`).join("");
  const body = rows.map(row => `<tr>${columns.map(column => `<td>${column.toLowerCase().includes("amount") || column === "total" ? money(row[column]) : escapeHTML(row[column] ?? "—")}</td>`).join("")}</tr>`).join("");
  document.getElementById("reportResult").innerHTML = `<section class="content-card"><div class="card-heading"><div><h2>${escapeHTML(report.type[0].toUpperCase() + report.type.slice(1))} report</h2><p>${dateValue(report.from)} to ${dateValue(report.to)} · ${rows.length} row(s)</p></div>${total === null ? "" : `<strong>${money(total)}</strong>`}</div><div class="table-responsive"><table class="table table-hover"><thead><tr>${header}</tr></thead><tbody>${body}</tbody></table></div></section>`;
}

function exportReport() {
  if (!state.report || !state.report.rows.length) {
    toast("Run a report with results before exporting.");
    return;
  }
  const rows = state.report.rows;
  const columns = Object.keys(rows[0]);
  const quote = value => `"${String(value ?? "").replace(/"/g, '""')}"`;
  const csv = [columns.map(quote).join(","), ...rows.map(row => columns.map(column => quote(row[column])).join(","))].join("\r\n");
  const url = URL.createObjectURL(new Blob(["\ufeff", csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = `hotel-${state.report.type}-${state.report.from}-to-${state.report.to}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

function tableSearch(inputId, tableBodyId) {
  const input = document.getElementById(inputId);
  const body = document.getElementById(tableBodyId);
  if (!input || !body) return;
  const query = input.value.trim().toLowerCase();
  body.querySelectorAll("tr").forEach(row => row.classList.toggle("d-none", !row.textContent.toLowerCase().includes(query)));
}

function filterStays() {
  const status = document.getElementById("stayStatusFilter")?.value || "";
  document.querySelectorAll("#staysTableBody tr[data-status]").forEach(row => {
    row.classList.toggle("d-none", Boolean(status) && row.dataset.status !== status);
  });
}

function handlePageAction(event) {
  const navigation = event.target.closest("[data-page]");
  if (navigation) {
    const requestedPage = navigation.dataset.page;
    if (requestedPage === "employees" && !state.permissions.manageEmployees) {
      toast("Administrator access is required to manage employee accounts.");
      return;
    }
    state.currentPage = requestedPage;
    document.getElementById("sidebar").classList.remove("open");
    renderPage();
    return;
  }

  const actionElement = event.target.closest("[data-action]");
  if (!actionElement) return;
  const { action, id } = actionElement.dataset;
  if (action === "add-room") openRoomForm();
  if (action === "edit-room") openRoomForm(id);
  if (action === "delete-room") deleteRecord("room_delete", id, "Archive this room? Its history will be retained.", "Room archived.");
  if (action === "room-clean") markRoomReady(id);
  if (action === "add-guest") openGuestForm();
  if (action === "edit-guest") openGuestForm(id);
  if (action === "delete-guest") deleteRecord("guest_delete", id, "Archive this guest profile? Check-in and payment history will be retained.", "Guest profile archived.");
  if (action === "guest-history") showGuestHistory(id);
  if (action === "checkin") openCheckinForm();
  if (action === "checkout") openCheckoutFromPage(id);
  if (action === "add-payment") openPaymentForm();
  if (action === "collect-payment") openPaymentForm(id);
  if (action === "add-employee") openEmployeeForm();
  if (action === "edit-employee") openEmployeeForm(id);
  if (action === "delete-employee") deleteRecord("employee_delete", id, "Deactivate this employee and disable their login?", "Employee deactivated.");
  if (action === "add-expense") openExpenseForm();
  if (action === "edit-expense") openExpenseForm(id);
  if (action === "delete-expense") deleteRecord("expense_delete", id, "Archive this expense? Its audit history will be retained.", "Expense archived.");
  if (action === "run-report") runReport();
  if (action === "export-report") exportReport();
  if (action === "print-receipt") printReceipt(id);
  if (action === "refund") refundPayment(id);
}

function bindFormReset(formId, modalId, errorIds, onClear) {
  const form = document.getElementById(formId);
  form.addEventListener("reset", () => {
    form.querySelectorAll('input[type="hidden"]').forEach(input => { input.value = ""; });
    errorIds.forEach(id => document.getElementById(id).classList.add("d-none"));
    if (onClear) onClear();
  });
  document.getElementById(modalId).addEventListener("hidden.bs.modal", () => {
    form.reset();
    errorIds.forEach(id => document.getElementById(id).classList.add("d-none"));
    form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
    if (onClear) onClear();
  });
}

async function initialize() {
  document.querySelectorAll(".current-year").forEach(element => {
    element.textContent = new Date().getFullYear();
  });
  document.getElementById("todayLabel").textContent = new Date().toLocaleDateString("en-PH", {
    weekday: "short", month: "short", day: "numeric", year: "numeric"
  });
  toastInstance = bootstrap.Toast.getOrCreateInstance(document.getElementById("appToast"), { delay: 3000 });
  [
    "guestModal", "roomModal", "checkinModal", "checkoutModal",
    "paymentModal", "employeeModal", "expenseModal", "guestHistoryModal"
  ].forEach(id => { modalInstances[id] = bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); });

  bindFormReset("guestForm", "guestModal", ["guestError"], () => {
    document.getElementById("guestModalTitle").textContent = "Register guest";
  });
  bindFormReset("roomForm", "roomModal", ["roomError"], () => {
    document.getElementById("roomModalTitle").textContent = "Add room";
    fillRoomTypeSelect();
  });
  bindFormReset("checkinForm", "checkinModal", ["checkinError"]);
  bindFormReset("checkoutForm", "checkoutModal", ["checkoutError"], () => {
    document.getElementById("checkoutDetails").textContent = "Select a guest to review room and charges.";
  });
  bindFormReset("paymentForm", "paymentModal", ["paymentError"]);
  bindFormReset("employeeForm", "employeeModal", ["employeeError"], () => {
    document.getElementById("employeeModalTitle").textContent = "Add employee";
    document.getElementById("passwordRequirement").textContent = "(required when creating a login; minimum 12 characters)";
  });
  bindFormReset("expenseForm", "expenseModal", ["expenseError"], () => {
    document.getElementById("expenseModalTitle").textContent = "Add expense";
  });

  document.getElementById("loginForm").addEventListener("submit", async event => {
    event.preventDefault();
    if (loginPending) return;
    loginPending = true;
    const form = event.currentTarget;
    setSubmitting(form, true);
    document.getElementById("loginError").textContent = "";
    try {
      const result = await api("login", {
        method: "POST",
        body: {
          email: document.getElementById("loginEmail").value.trim(),
          password: document.getElementById("loginPassword").value
        }
      });
      state.user = result.user;
      state.csrfToken = result.csrfToken;
      form.reset();
      await loadApplicationData();
      document.getElementById("authScreen").classList.add("d-none");
      document.getElementById("dashboard").classList.remove("d-none");
      state.currentPage = "dashboard";
      renderPage();
    } catch (error) {
      document.getElementById("loginError").textContent = error.message;
    } finally {
      loginPending = false;
      setSubmitting(form, false);
    }
  });

  document.getElementById("setupForm").addEventListener("submit", async event => {
    event.preventDefault();
    const form = event.currentTarget;
    setSubmitting(form, true);
    document.getElementById("setupError").classList.add("d-none");
    try {
      const result = await api("setup", {
        method: "POST",
        body: {
          name: document.getElementById("setupName").value.trim(),
          email: document.getElementById("setupEmail").value.trim(),
          password: document.getElementById("setupPassword").value
        }
      });
      state.user = result.user;
      state.csrfToken = result.csrfToken;
      await loadApplicationData();
      document.getElementById("authScreen").classList.add("d-none");
      document.getElementById("dashboard").classList.remove("d-none");
      renderPage();
    } catch (error) {
      showApiError("setupError", error);
    } finally {
      setSubmitting(form, false);
    }
  });

  document.getElementById("signupButton").addEventListener("click", showSetup);
  document.getElementById("backToLoginButton").addEventListener("click", showLogin);

  document.getElementById("togglePassword").addEventListener("click", event => {
    const password = document.getElementById("loginPassword");
    password.type = password.type === "password" ? "text" : "password";
    event.currentTarget.textContent = password.type === "password" ? "Show" : "Hide";
  });
  document.getElementById("logoutButton").addEventListener("click", async () => {
    try {
      await api("logout", { method: "POST", body: {} });
      state.user = null;
      setAuthScreen();
      showLogin();
    } catch (error) {
      toast(error.message);
    }
  });

  document.getElementById("roomForm").addEventListener("submit", saveRoom);
  document.getElementById("guestForm").addEventListener("submit", saveGuest);
  document.getElementById("checkinForm").addEventListener("submit", saveCheckin);
  document.getElementById("checkoutForm").addEventListener("submit", saveCheckout);
  document.getElementById("paymentForm").addEventListener("submit", savePayment);
  document.getElementById("employeeForm").addEventListener("submit", saveEmployee);
  document.getElementById("expenseForm").addEventListener("submit", saveExpense);
  document.getElementById("menuToggle").addEventListener("click", () => document.getElementById("sidebar").classList.toggle("open"));
  document.addEventListener("click", handlePageAction);
  document.getElementById("pageContent").addEventListener("input", event => {
    if (event.target.id === "guestSearch") tableSearch("guestSearch", "guestsTableBody");
    if (event.target.id === "roomSearch") tableSearch("roomSearch", "roomsTableBody");
    if (event.target.id === "paymentSearch") tableSearch("paymentSearch", "paymentsTableBody");
  });
  document.getElementById("pageContent").addEventListener("change", event => {
    if (event.target.id === "stayStatusFilter") filterStays();
  });

  document.getElementById("checkinGuest").addEventListener("change", updateCheckinGuestFields);
  ["checkinRoom", "checkinDateOnly", "expectedCheckout"].forEach(id => {
    const input = document.getElementById(id);
    if (input) input.addEventListener("input", updateCheckinEstimate);
  });
  document.getElementById("checkedInAt").addEventListener("input", () => {
    document.getElementById("checkinDateOnly").value = document.getElementById("checkedInAt").value.slice(0, 10);
    updateCheckinEstimate();
  });
  document.getElementById("checkoutStay").addEventListener("change", updateCheckoutSummary);
  document.getElementById("additionalCharges").addEventListener("input", updateCheckoutSummary);

  try {
    const initial = await api("bootstrap");
    state.csrfToken = initial.csrfToken;
    if (initial.setupRequired) {
      document.getElementById("signupButton").classList.remove("d-none");
      showLogin();
    } else if (initial.data) {
      Object.assign(state, initial.data);
      state.csrfToken = initial.csrfToken;
      await loadApplicationData();
      document.getElementById("authScreen").classList.add("d-none");
      document.getElementById("dashboard").classList.remove("d-none");
      renderPage();
    } else {
      showLogin();
    }
  } catch (error) {
    document.getElementById("loginError").textContent = error.message;
  }
}

document.addEventListener("DOMContentLoaded", initialize);
