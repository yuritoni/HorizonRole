(function () {
  "use strict";

  const STORAGE_KEY = "horizon-erp-data-v1";
  const currency = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0
  });
  const number = new Intl.NumberFormat("id-ID");
  const dateFormat = new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric"
  });

  const modules = [
    { id: "dashboard", label: "Dashboard", icon: "dashboard" },
    { id: "sales", label: "Penjualan", icon: "sales" },
    { id: "procurement", label: "Pembelian", icon: "cart" },
    { id: "inventory", label: "Inventori", icon: "box" },
    { id: "finance", label: "Keuangan", icon: "wallet" },
    { id: "crm", label: "CRM", icon: "handshake" },
    { id: "manufacturing", label: "Produksi", icon: "factory" },
    { id: "projects", label: "Proyek", icon: "briefcase" },
    { id: "hr", label: "SDM", icon: "users" },
    { id: "assets", label: "Aset", icon: "landmark" },
    { id: "reports", label: "Laporan", icon: "chart" },
    { id: "settings", label: "Pengaturan", icon: "settings" }
  ];

  const recordConfigs = {
    sales: {
      title: "Penjualan",
      group: "Order to Cash",
      subtitle: "Quote, sales order, invoice, pembayaran, dan performa kanal penjualan.",
      entity: "order penjualan",
      prefix: "SO",
      valueField: "amount",
      statusField: "status",
      displayField: "customer",
      subField: "channel",
      statuses: ["Draft", "Confirmed", "Invoiced", "Paid"],
      fields: [
        { id: "id", label: "Nomor Order", type: "text", readonly: true },
        { id: "customer", label: "Pelanggan", type: "text", required: true },
        { id: "channel", label: "Kanal", type: "select", options: ["B2B", "Retail", "Project", "Marketplace"] },
        { id: "amount", label: "Nilai", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "owner", label: "Owner", type: "text" },
        { id: "due", label: "Jatuh Tempo", type: "date" },
        { id: "notes", label: "Catatan", type: "textarea", full: true }
      ],
      columns: [
        { id: "id", label: "Order", type: "id" },
        { id: "customer", label: "Pelanggan" },
        { id: "channel", label: "Kanal" },
        { id: "amount", label: "Nilai", type: "currency" },
        { id: "status", label: "Status", type: "status" },
        { id: "owner", label: "Owner" },
        { id: "due", label: "Tempo", type: "date" }
      ]
    },
    procurement: {
      title: "Pembelian",
      group: "Procure to Pay",
      subtitle: "Permintaan pembelian, purchase order, vendor, penerimaan barang, dan tagihan.",
      entity: "purchase order",
      prefix: "PO",
      valueField: "amount",
      statusField: "status",
      displayField: "vendor",
      subField: "category",
      statuses: ["Draft", "Waiting Approval", "Ordered", "Received", "Closed"],
      fields: [
        { id: "id", label: "Nomor PO", type: "text", readonly: true },
        { id: "vendor", label: "Vendor", type: "text", required: true },
        { id: "category", label: "Kategori", type: "select", options: ["Raw Material", "Spare Part", "Service", "IT", "Office"] },
        { id: "amount", label: "Nilai", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "buyer", label: "Buyer", type: "text" },
        { id: "due", label: "Estimasi Terima", type: "date" },
        { id: "notes", label: "Catatan", type: "textarea", full: true }
      ],
      columns: [
        { id: "id", label: "PO", type: "id" },
        { id: "vendor", label: "Vendor" },
        { id: "category", label: "Kategori" },
        { id: "amount", label: "Nilai", type: "currency" },
        { id: "status", label: "Status", type: "status" },
        { id: "buyer", label: "Buyer" },
        { id: "due", label: "ETA", type: "date" }
      ]
    },
    inventory: {
      title: "Inventori",
      group: "Warehouse",
      subtitle: "Stok, minimum stock, lokasi gudang, valuasi, dan status material.",
      entity: "item inventori",
      prefix: "SKU",
      valueField: "value",
      statusField: "status",
      displayField: "item",
      subField: "warehouse",
      statuses: ["Available", "Low Stock", "Reserved", "Blocked"],
      fields: [
        { id: "id", label: "SKU", type: "text", readonly: true },
        { id: "item", label: "Nama Item", type: "text", required: true },
        { id: "category", label: "Kategori", type: "select", options: ["Finished Goods", "Raw Material", "Packaging", "MRO"] },
        { id: "warehouse", label: "Gudang", type: "select", options: ["Jakarta", "Surabaya", "Bandung", "Medan"] },
        { id: "onHand", label: "Stok", type: "number", required: true },
        { id: "minStock", label: "Minimum", type: "number", required: true },
        { id: "value", label: "Nilai Stok", type: "number", required: true },
        { id: "status", label: "Status", type: "select" }
      ],
      columns: [
        { id: "id", label: "SKU", type: "id" },
        { id: "item", label: "Item" },
        { id: "warehouse", label: "Gudang" },
        { id: "onHand", label: "Stok", type: "number" },
        { id: "minStock", label: "Min", type: "number" },
        { id: "value", label: "Nilai", type: "currency" },
        { id: "status", label: "Status", type: "status" }
      ]
    },
    finance: {
      title: "Keuangan",
      group: "Record to Report",
      subtitle: "Piutang, utang, jurnal, budget control, arus kas, dan rekonsiliasi.",
      entity: "transaksi keuangan",
      prefix: "FIN",
      valueField: "amount",
      statusField: "status",
      displayField: "account",
      subField: "type",
      statuses: ["Open", "Approved", "Posted", "Paid", "Overdue"],
      fields: [
        { id: "id", label: "Nomor", type: "text", readonly: true },
        { id: "type", label: "Tipe", type: "select", options: ["AR Invoice", "AP Bill", "Journal", "Budget"] },
        { id: "account", label: "Akun", type: "text", required: true },
        { id: "partner", label: "Partner", type: "text" },
        { id: "amount", label: "Nilai", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "due", label: "Tanggal", type: "date" },
        { id: "notes", label: "Memo", type: "textarea", full: true }
      ],
      columns: [
        { id: "id", label: "Nomor", type: "id" },
        { id: "type", label: "Tipe" },
        { id: "account", label: "Akun" },
        { id: "partner", label: "Partner" },
        { id: "amount", label: "Nilai", type: "currency" },
        { id: "status", label: "Status", type: "status" },
        { id: "due", label: "Tanggal", type: "date" }
      ]
    },
    crm: {
      title: "CRM",
      group: "Customer Growth",
      subtitle: "Lead, opportunity, estimasi deal, aktivitas sales, dan kepemilikan akun.",
      entity: "opportunity",
      prefix: "CRM",
      valueField: "value",
      statusField: "stage",
      displayField: "lead",
      subField: "company",
      statuses: ["New", "Qualified", "Proposal", "Negotiation", "Won", "Lost"],
      fields: [
        { id: "id", label: "Nomor Lead", type: "text", readonly: true },
        { id: "lead", label: "Kontak", type: "text", required: true },
        { id: "company", label: "Perusahaan", type: "text", required: true },
        { id: "value", label: "Potensi", type: "number", required: true },
        { id: "stage", label: "Tahap", type: "select" },
        { id: "owner", label: "Owner", type: "text" },
        { id: "nextAction", label: "Aktivitas Berikutnya", type: "date" },
        { id: "notes", label: "Catatan", type: "textarea", full: true }
      ],
      columns: [
        { id: "id", label: "Lead", type: "id" },
        { id: "lead", label: "Kontak" },
        { id: "company", label: "Perusahaan" },
        { id: "value", label: "Potensi", type: "currency" },
        { id: "stage", label: "Tahap", type: "status" },
        { id: "owner", label: "Owner" },
        { id: "nextAction", label: "Next", type: "date" }
      ]
    },
    manufacturing: {
      title: "Produksi",
      group: "Plan to Produce",
      subtitle: "Work order, rencana kapasitas, progress produksi, line, dan target selesai.",
      entity: "work order",
      prefix: "WO",
      valueField: "quantity",
      statusField: "status",
      displayField: "product",
      subField: "line",
      statuses: ["Planned", "Released", "Running", "Inspection", "Completed", "Delayed"],
      fields: [
        { id: "id", label: "Work Order", type: "text", readonly: true },
        { id: "product", label: "Produk", type: "text", required: true },
        { id: "quantity", label: "Qty", type: "number", required: true },
        { id: "line", label: "Line", type: "select", options: ["Line A", "Line B", "Line C", "Outsource"] },
        { id: "progress", label: "Progress %", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "due", label: "Target Selesai", type: "date" },
        { id: "notes", label: "Catatan", type: "textarea", full: true }
      ],
      columns: [
        { id: "id", label: "WO", type: "id" },
        { id: "product", label: "Produk" },
        { id: "quantity", label: "Qty", type: "number" },
        { id: "line", label: "Line" },
        { id: "progress", label: "Progress", type: "percent" },
        { id: "status", label: "Status", type: "status" },
        { id: "due", label: "Target", type: "date" }
      ]
    },
    projects: {
      title: "Proyek",
      group: "Delivery",
      subtitle: "Project charter, budget, milestone, progress, owner, dan risiko keterlambatan.",
      entity: "proyek",
      prefix: "PRJ",
      valueField: "budget",
      statusField: "status",
      displayField: "project",
      subField: "customer",
      statuses: ["Planning", "On Track", "At Risk", "Delayed", "Completed"],
      fields: [
        { id: "id", label: "Kode Proyek", type: "text", readonly: true },
        { id: "project", label: "Nama Proyek", type: "text", required: true },
        { id: "customer", label: "Pelanggan", type: "text" },
        { id: "manager", label: "Manajer", type: "text" },
        { id: "budget", label: "Budget", type: "number", required: true },
        { id: "progress", label: "Progress %", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "due", label: "Target", type: "date" }
      ],
      columns: [
        { id: "id", label: "Kode", type: "id" },
        { id: "project", label: "Proyek" },
        { id: "customer", label: "Pelanggan" },
        { id: "manager", label: "Manajer" },
        { id: "budget", label: "Budget", type: "currency" },
        { id: "progress", label: "Progress", type: "percent" },
        { id: "status", label: "Status", type: "status" }
      ]
    },
    hr: {
      title: "SDM",
      group: "Hire to Retire",
      subtitle: "Data pegawai, payroll, departemen, kehadiran, kontrak, dan status karyawan.",
      entity: "pegawai",
      prefix: "EMP",
      valueField: "salary",
      statusField: "status",
      displayField: "employee",
      subField: "department",
      statuses: ["Active", "On Leave", "Recruiting", "Probation", "Inactive"],
      fields: [
        { id: "id", label: "NIK", type: "text", readonly: true },
        { id: "employee", label: "Nama", type: "text", required: true },
        { id: "department", label: "Departemen", type: "select", options: ["Finance", "Sales", "Operations", "HR", "IT"] },
        { id: "role", label: "Jabatan", type: "text" },
        { id: "salary", label: "Gaji", type: "number", required: true },
        { id: "attendance", label: "Kehadiran %", type: "number", required: true },
        { id: "status", label: "Status", type: "select" },
        { id: "joinDate", label: "Tanggal Masuk", type: "date" }
      ],
      columns: [
        { id: "id", label: "NIK", type: "id" },
        { id: "employee", label: "Nama" },
        { id: "department", label: "Departemen" },
        { id: "role", label: "Jabatan" },
        { id: "salary", label: "Gaji", type: "currency" },
        { id: "attendance", label: "Kehadiran", type: "percent" },
        { id: "status", label: "Status", type: "status" }
      ]
    },
    assets: {
      title: "Aset",
      group: "Asset Lifecycle",
      subtitle: "Register aset, lokasi, penanggung jawab, nilai buku, kondisi, dan jadwal service.",
      entity: "aset",
      prefix: "AST",
      valueField: "value",
      statusField: "status",
      displayField: "asset",
      subField: "location",
      statuses: ["In Use", "Maintenance", "Available", "Retired"],
      fields: [
        { id: "id", label: "Kode Aset", type: "text", readonly: true },
        { id: "asset", label: "Nama Aset", type: "text", required: true },
        { id: "location", label: "Lokasi", type: "text" },
        { id: "owner", label: "PIC", type: "text" },
        { id: "value", label: "Nilai Buku", type: "number", required: true },
        { id: "condition", label: "Kondisi", type: "select", options: ["Excellent", "Good", "Fair", "Poor"] },
        { id: "status", label: "Status", type: "select" },
        { id: "nextService", label: "Service Berikutnya", type: "date" }
      ],
      columns: [
        { id: "id", label: "Kode", type: "id" },
        { id: "asset", label: "Aset" },
        { id: "location", label: "Lokasi" },
        { id: "owner", label: "PIC" },
        { id: "value", label: "Nilai", type: "currency" },
        { id: "condition", label: "Kondisi" },
        { id: "status", label: "Status", type: "status" }
      ]
    }
  };

  const state = {
    activeModule: "dashboard",
    query: "",
    statusFilter: "all",
    data: loadData()
  };

  const els = {
    nav: document.getElementById("moduleNav"),
    content: document.getElementById("content"),
    search: document.getElementById("globalSearch"),
    sidebar: document.getElementById("sidebar"),
    sidebarToggle: document.getElementById("sidebarToggle"),
    newRecord: document.getElementById("newRecordBtn"),
    saveData: document.getElementById("saveDataBtn"),
    exportData: document.getElementById("exportDataBtn"),
    period: document.getElementById("periodSelect"),
    modalRoot: document.getElementById("modalRoot"),
    toast: document.getElementById("toast"),
    searchIcon: document.getElementById("searchIcon")
  };

  function init() {
    els.searchIcon.innerHTML = icon("search");
    els.newRecord.innerHTML = icon("plus") + "<span>Baru</span>";
    els.saveData.innerHTML = icon("save") + "<span>Simpan</span>";
    els.exportData.innerHTML = icon("download") + "<span>Export</span>";
    bindEvents();
    render();
  }

  function bindEvents() {
    els.nav.addEventListener("click", function (event) {
      const button = event.target.closest("[data-module]");
      if (!button) return;
      state.activeModule = button.dataset.module;
      state.statusFilter = "all";
      els.sidebar.classList.remove("is-open");
      render();
    });

    els.search.addEventListener("input", function (event) {
      state.query = event.target.value.trim().toLowerCase();
      render();
    });

    els.sidebarToggle.addEventListener("click", function () {
      els.sidebar.classList.toggle("is-open");
    });

    els.newRecord.addEventListener("click", function () {
      openRecordModal(recordConfigs[state.activeModule] ? state.activeModule : "sales");
    });

    els.saveData.addEventListener("click", function () {
      persist();
      showToast("Data ERP berhasil disimpan di browser.");
    });

    els.exportData.addEventListener("click", exportActiveData);

    els.period.addEventListener("change", function () {
      showToast("Periode tampilan diperbarui ke " + els.period.options[els.period.selectedIndex].text + ".");
    });

    document.addEventListener("click", handleAction);
    document.addEventListener("change", handleChange);
    document.addEventListener("submit", handleSubmit);
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") closeModal();
    });
  }

  function handleAction(event) {
    const target = event.target.closest("[data-action]");
    if (!target) return;
    const action = target.dataset.action;
    const moduleId = target.dataset.module;
    const recordId = target.dataset.id;

    if (action === "filter") {
      state.statusFilter = target.dataset.value || "all";
      render();
    }

    if (action === "edit") {
      openRecordModal(moduleId, recordId);
    }

    if (action === "delete") {
      deleteRecord(moduleId, recordId);
    }

    if (action === "advance") {
      advanceRecord(moduleId, recordId);
    }

    if (action === "close-modal") {
      closeModal();
    }

    if (action === "approve" || action === "reject") {
      updateApproval(target.dataset.approval, action);
    }

    if (action === "reset-data") {
      if (window.confirm("Reset seluruh data demo ERP?")) {
        state.data = createSeedData();
        persist();
        render();
        showToast("Data demo ERP sudah direset.");
      }
    }
  }

  function handleChange(event) {
    const target = event.target;
    const action = target.dataset.action;
    if (action === "toggle-permission") {
      const role = target.dataset.role;
      const moduleId = target.dataset.module;
      state.data.settings.permissions[role][moduleId] = target.checked;
      persist();
      showToast("Hak akses diperbarui.");
    }

    if (action === "toggle-integration") {
      const id = target.dataset.integration;
      const integration = state.data.settings.integrations.find(function (item) {
        return item.id === id;
      });
      if (integration) {
        integration.enabled = target.checked;
        persist();
        showToast("Status integrasi diperbarui.");
      }
    }
  }

  function handleSubmit(event) {
    if (event.target.id !== "recordForm") return;
    event.preventDefault();
    const form = event.target;
    const moduleId = form.dataset.module;
    const editId = form.dataset.editId;
    const cfg = recordConfigs[moduleId];
    const formData = new FormData(form);
    const record = {};

    cfg.fields.forEach(function (field) {
      let value = formData.get(field.id);
      if (field.type === "number") {
        value = Number(value || 0);
      } else {
        value = String(value || "").trim();
      }
      record[field.id] = value;
    });

    if (!record.id) {
      record.id = generateId(moduleId);
    }

    normalizeRecord(moduleId, record);

    const records = state.data[moduleId];
    const existingIndex = records.findIndex(function (item) {
      return item.id === editId;
    });

    if (existingIndex >= 0) {
      records[existingIndex] = record;
    } else {
      records.unshift(record);
    }

    persist();
    closeModal();
    render();
    showToast(capitalize(cfg.entity) + " berhasil disimpan.");
  }

  function render() {
    renderNavigation();
    renderTopbarState();

    if (state.activeModule === "dashboard") {
      renderDashboard();
    } else if (state.activeModule === "reports") {
      renderReports();
    } else if (state.activeModule === "settings") {
      renderSettings();
    } else {
      renderRecordModule(state.activeModule);
    }

    window.requestAnimationFrame(drawVisibleCharts);
  }

  function renderTopbarState() {
    const active = modules.find(function (item) {
      return item.id === state.activeModule;
    });
    const createLabel = recordConfigs[state.activeModule] ? "Baru" : "Order";
    els.newRecord.innerHTML = icon("plus") + "<span>" + createLabel + "</span>";
    els.exportData.setAttribute("title", "Export " + (active ? active.label : "data"));
  }

  function renderNavigation() {
    els.nav.innerHTML = modules
      .map(function (item) {
        const active = item.id === state.activeModule ? " is-active" : "";
        return (
          '<button class="nav-item' +
          active +
          '" data-module="' +
          item.id +
          '" type="button">' +
          icon(item.icon) +
          "<span>" +
          escapeHtml(item.label) +
          '</span><span class="nav-count">' +
          getModuleCount(item.id) +
          "</span></button>"
        );
      })
      .join("");
  }

  function renderDashboard() {
    const metrics = getDashboardMetrics();
    const approvals = state.data.approvals.filter(function (item) {
      return item.status === "Pending";
    });

    els.content.innerHTML =
      renderModuleHeader({
        title: "Dashboard Operasional",
        group: "Executive Cockpit",
        subtitle: "Ringkasan lintas departemen untuk pendapatan, kas, stok, approval, dan kapasitas kerja.",
        stats: [
          { label: "Approval", value: approvals.length + " pending" },
          { label: "Modul Aktif", value: Object.keys(recordConfigs).length + " proses" }
        ]
      }) +
      '<div class="metrics-grid">' +
      metrics.map(renderMetricCard).join("") +
      "</div>" +
      '<div class="dashboard-grid">' +
      '<section class="panel"><div class="panel-header"><h2>Revenue dan Cashflow</h2><span class="muted">Q2 2026</span></div><div class="panel-body"><canvas class="chart" id="revenueChart"></canvas></div></section>' +
      '<section class="panel"><div class="panel-header"><h2>Approval Aktif</h2><span class="muted">' +
      approvals.length +
      ' tiket</span></div><div class="panel-body">' +
      renderApprovalList(approvals) +
      "</div></section>" +
      '<section class="panel"><div class="panel-header"><h2>Arus Kas</h2><span class="muted">Inflow vs outflow</span></div><div class="panel-body"><canvas class="chart short" id="cashflowChart"></canvas></div></section>' +
      '<section class="panel"><div class="panel-header"><h2>Kesehatan Modul</h2><span class="muted">Signal operasional</span></div><div class="panel-body">' +
      renderHealthList() +
      "</div></section>" +
      "</div>";
  }

  function renderRecordModule(moduleId) {
    const cfg = recordConfigs[moduleId];
    const records = getFilteredRecords(moduleId);
    const allRecords = state.data[moduleId];
    const totalValue = sum(allRecords, cfg.valueField);
    const statusField = cfg.statusField;

    els.content.innerHTML =
      renderModuleHeader({
        title: cfg.title,
        group: cfg.group,
        subtitle: cfg.subtitle,
        stats: [
          { label: "Total Record", value: number.format(allRecords.length) },
          { label: "Nilai", value: formatMetricValue(moduleId, totalValue) }
        ]
      }) +
      '<div class="metrics-grid">' +
      getModuleMetrics(moduleId).map(renderMetricCard).join("") +
      "</div>" +
      renderFilterRow(cfg) +
      '<div class="module-grid">' +
      '<section class="panel"><div class="panel-header"><h2>' +
      escapeHtml(cfg.title) +
      '</h2><span class="muted">' +
      number.format(records.length) +
      " dari " +
      number.format(allRecords.length) +
      ' record</span></div><div class="table-wrap">' +
      renderTable(cfg, records, moduleId) +
      "</div></section>" +
      '<section class="panel"><div class="panel-header"><h2>Workflow</h2><span class="muted">' +
      escapeHtml(state.statusFilter === "all" ? "Semua status" : state.statusFilter) +
      '</span></div><div class="panel-body">' +
      renderModuleInsight(moduleId, statusField) +
      "</div></section>" +
      "</div>";
  }

  function renderReports() {
    const reportTiles = [
      { title: "Profitabilitas Produk", value: "27,4% margin", note: "Gross margin gabungan" },
      { title: "Aging Piutang", value: "IDR 93,4 jt", note: "Lewat tempo lebih dari 30 hari" },
      { title: "Perputaran Stok", value: "5,8x", note: "Rolling 90 hari" },
      { title: "Efisiensi Produksi", value: "84%", note: "Output aktual terhadap kapasitas" },
      { title: "Utilisasi SDM", value: "91%", note: "Kehadiran dan beban kerja" },
      { title: "Budget Variance", value: "3,2%", note: "Selisih terhadap rencana" }
    ];

    els.content.innerHTML =
      renderModuleHeader({
        title: "Laporan Manajemen",
        group: "Analytics",
        subtitle: "Laporan lintas modul untuk closing, audit, budgeting, dan evaluasi performa.",
        stats: [
          { label: "Template", value: "18 laporan" },
          { label: "Refresh", value: "Realtime" }
        ]
      }) +
      '<div class="report-grid">' +
      reportTiles
        .map(function (tile) {
          return (
            '<article class="report-tile"><strong>' +
            escapeHtml(tile.title) +
            '</strong><div class="metric-value">' +
            escapeHtml(tile.value) +
            '</div><p class="muted">' +
            escapeHtml(tile.note) +
            "</p></article>"
          );
        })
        .join("") +
      "</div>" +
      '<div class="dashboard-grid" style="margin-top:16px">' +
      '<section class="panel"><div class="panel-header"><h2>Margin per Lini</h2><span class="muted">Produk dan jasa</span></div><div class="panel-body"><canvas class="chart" id="marginChart"></canvas></div></section>' +
      '<section class="panel"><div class="panel-header"><h2>Working Capital</h2><span class="muted">AR, AP, inventory</span></div><div class="panel-body"><canvas class="chart" id="workingCapitalChart"></canvas></div></section>' +
      "</div>";
  }

  function renderSettings() {
    const roles = Object.keys(state.data.settings.permissions);
    const moduleIds = ["sales", "procurement", "inventory", "finance", "crm", "manufacturing", "projects", "hr", "assets"];
    const integrations = state.data.settings.integrations;

    els.content.innerHTML =
      renderModuleHeader({
        title: "Pengaturan",
        group: "Admin",
        subtitle: "Hak akses, approval matrix, integrasi, preferensi perusahaan, dan kontrol data aplikasi.",
        stats: [
          { label: "Role", value: roles.length + " role" },
          { label: "Integrasi", value: integrations.filter(function (item) { return item.enabled; }).length + " aktif" }
        ]
      }) +
      '<div class="settings-grid">' +
      integrations
        .map(function (item) {
          return (
            '<article class="setting-tile"><strong>' +
            escapeHtml(item.name) +
            '</strong><p class="muted">' +
            escapeHtml(item.description) +
            '</p><label class="switch" title="Aktifkan integrasi"><input type="checkbox" data-action="toggle-integration" data-integration="' +
            item.id +
            '"' +
            (item.enabled ? " checked" : "") +
            '><span></span></label></article>'
          );
        })
        .join("") +
      "</div>" +
      '<div class="module-grid" style="margin-top:16px">' +
      '<section class="panel"><div class="panel-header"><h2>Role Permission</h2><span class="muted">Matrix modul</span></div><div class="table-wrap">' +
      renderPermissionTable(roles, moduleIds) +
      "</div></section>" +
      '<section class="panel"><div class="panel-header"><h2>Kontrol Data</h2><span class="muted">Local workspace</span></div><div class="panel-body"><div class="mini-list">' +
      '<div class="mini-item"><div><strong>Auto-save browser</strong><span>Perubahan tersimpan di localStorage setelah aksi penting.</span></div>' +
      statusPill("Active") +
      '</div><div class="mini-item"><div><strong>Approval threshold</strong><span>PO di atas IDR 75 jt masuk approval CFO.</span></div>' +
      statusPill("Waiting Approval") +
      '</div><button class="ghost-button" type="button" data-action="reset-data">' +
      icon("refresh") +
      "<span>Reset Data Demo</span></button></div></div></section>" +
      "</div>";
  }

  function renderModuleHeader(options) {
    return (
      '<header class="module-header"><div><p class="eyebrow">' +
      escapeHtml(options.group) +
      "</p><h1>" +
      escapeHtml(options.title) +
      "</h1><p>" +
      escapeHtml(options.subtitle) +
      '</p></div><div class="header-stat">' +
      options.stats
        .map(function (item) {
          return (
            '<div class="stat-pill"><span>' +
            escapeHtml(item.label) +
            "</span><strong>" +
            escapeHtml(item.value) +
            "</strong></div>"
          );
        })
        .join("") +
      "</div></header>"
    );
  }

  function renderMetricCard(metric) {
    const progress = Math.max(4, Math.min(100, Number(metric.progress || 0)));
    return (
      '<article class="metric-card" style="--metric-color:' +
      metric.color +
      ";--metric-bg:" +
      metric.bg +
      ';--bar-color:' +
      metric.color +
      '"><div class="metric-top"><span>' +
      escapeHtml(metric.label) +
      '</span><span class="metric-icon">' +
      icon(metric.icon || "chart") +
      '</span></div><div class="metric-value">' +
      escapeHtml(metric.value) +
      '</div><div class="progress"><span style="width:' +
      progress +
      '%"></span></div><p class="metric-note">' +
      escapeHtml(metric.note) +
      "</p></article>"
    );
  }

  function renderFilterRow(cfg) {
    const chips = ["all"].concat(cfg.statuses);
    return (
      '<div class="filter-row">' +
      chips
        .map(function (status) {
          const label = status === "all" ? "Semua" : status;
          const active = state.statusFilter === status ? " is-active" : "";
          return (
            '<button class="chip' +
            active +
            '" type="button" data-action="filter" data-value="' +
            escapeAttribute(status) +
            '">' +
            escapeHtml(label) +
            "</button>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderTable(cfg, records, moduleId) {
    if (!records.length) {
      return '<div class="empty-state">Tidak ada data yang cocok.</div>';
    }

    return (
      "<table><thead><tr>" +
      cfg.columns
        .map(function (column) {
          return "<th>" + escapeHtml(column.label) + "</th>";
        })
        .join("") +
      '<th style="text-align:right">Aksi</th></tr></thead><tbody>' +
      records
        .map(function (record) {
          return (
            "<tr>" +
            cfg.columns
              .map(function (column) {
                return "<td>" + formatCell(record[column.id], column, record) + "</td>";
              })
              .join("") +
            '<td><div class="row-actions">' +
            '<button type="button" title="Edit" aria-label="Edit" data-action="edit" data-module="' +
            moduleId +
            '" data-id="' +
            escapeAttribute(record.id) +
            '">' +
            icon("edit") +
            '</button><button type="button" title="Status berikutnya" aria-label="Status berikutnya" data-action="advance" data-module="' +
            moduleId +
            '" data-id="' +
            escapeAttribute(record.id) +
            '">' +
            icon("arrowRight") +
            '</button><button type="button" title="Hapus" aria-label="Hapus" data-action="delete" data-module="' +
            moduleId +
            '" data-id="' +
            escapeAttribute(record.id) +
            '">' +
            icon("trash") +
            "</button></div></td></tr>"
          );
        })
        .join("") +
      "</tbody></table>"
    );
  }

  function renderModuleInsight(moduleId, statusField) {
    if (moduleId === "inventory") return renderInventoryInsight();
    if (moduleId === "finance") return renderFinanceInsight();
    if (moduleId === "hr") return renderHrInsight();

    const cfg = recordConfigs[moduleId];
    const records = state.data[moduleId];
    return (
      '<div class="pipeline-board">' +
      cfg.statuses
        .map(function (status) {
          const items = records.filter(function (record) {
            return record[statusField] === status;
          });
          return (
            '<div class="pipeline-column"><div class="pipeline-title"><span>' +
            escapeHtml(status) +
            "</span><strong>" +
            number.format(items.length) +
            "</strong></div>" +
            items
              .slice(0, 2)
              .map(function (record) {
                return (
                  '<div class="pipeline-card"><strong>' +
                  escapeHtml(record[cfg.displayField] || record.id) +
                  '</strong><span class="muted">' +
                  escapeHtml(record[cfg.subField] || record.id) +
                  "</span></div>"
                );
              })
              .join("") +
            "</div>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderInventoryInsight() {
    const lows = state.data.inventory
      .filter(function (item) {
        return Number(item.onHand) <= Number(item.minStock);
      })
      .slice(0, 5);
    return (
      '<div class="mini-list">' +
      lows
        .map(function (item) {
          const ratio = Number(item.minStock) ? Math.min(100, (Number(item.onHand) / Number(item.minStock)) * 100) : 0;
          return (
            '<div class="mini-item"><div><strong>' +
            escapeHtml(item.item) +
            '</strong><span>' +
            escapeHtml(item.warehouse) +
            " - " +
            number.format(item.onHand) +
            " / min " +
            number.format(item.minStock) +
            '</span><div class="progress" style="margin-top:8px"><span style="width:' +
            ratio +
            '%;--bar-color:var(--amber)"></span></div></div>' +
            statusPill(item.status) +
            "</div>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderFinanceInsight() {
    const ar = state.data.finance.filter(function (item) {
      return item.type === "AR Invoice";
    });
    const ap = state.data.finance.filter(function (item) {
      return item.type === "AP Bill";
    });
    const open = state.data.finance.filter(function (item) {
      return item.status === "Open" || item.status === "Overdue";
    });
    return (
      '<div class="mini-list"><div class="mini-item"><div><strong>Piutang</strong><span>' +
      currency.format(sum(ar, "amount")) +
      '</span></div>' +
      statusPill("Open") +
      '</div><div class="mini-item"><div><strong>Utang</strong><span>' +
      currency.format(sum(ap, "amount")) +
      '</span></div>' +
      statusPill("Approved") +
      '</div><div class="mini-item"><div><strong>Perlu Follow Up</strong><span>' +
      number.format(open.length) +
      ' transaksi</span></div>' +
      statusPill(open.length ? "Overdue" : "Posted") +
      "</div></div>"
    );
  }

  function renderHrInsight() {
    const departments = groupBy(state.data.hr, "department");
    return (
      '<div class="mini-list">' +
      Object.keys(departments)
        .map(function (department) {
          const items = departments[department];
          const avg = average(items, "attendance");
          return (
            '<div class="mini-item"><div><strong>' +
            escapeHtml(department) +
            '</strong><span>' +
            number.format(items.length) +
            " pegawai - kehadiran " +
            Math.round(avg) +
            '%</span></div>' +
            statusPill(avg >= 90 ? "Active" : "At Risk") +
            "</div>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderApprovalList(approvals) {
    if (!approvals.length) {
      return '<div class="empty-state">Tidak ada approval pending.</div>';
    }

    return (
      '<div class="mini-list">' +
      approvals
        .map(function (approval) {
          return (
            '<div class="mini-item"><div><strong>' +
            escapeHtml(approval.title) +
            '</strong><span>' +
            escapeHtml(approval.requester) +
            " - " +
            currency.format(approval.amount) +
            '</span></div><div class="approval-actions"><button class="icon-button" type="button" title="Setujui" data-action="approve" data-approval="' +
            approval.id +
            '">' +
            icon("check") +
            '</button><button class="icon-button" type="button" title="Tolak" data-action="reject" data-approval="' +
            approval.id +
            '">' +
            icon("x") +
            "</button></div></div>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderHealthList() {
    const health = [
      { name: "Cash conversion", note: "DSO turun 4 hari", status: "On Track" },
      { name: "Low stock", note: getLowStockCount() + " SKU perlu reorder", status: getLowStockCount() > 2 ? "At Risk" : "Active" },
      { name: "Produksi", note: average(state.data.manufacturing, "progress").toFixed(0) + "% progress rata-rata", status: "Running" },
      { name: "People", note: average(state.data.hr, "attendance").toFixed(0) + "% kehadiran", status: "Active" }
    ];

    return (
      '<div class="mini-list">' +
      health
        .map(function (item) {
          return (
            '<div class="mini-item"><div><strong>' +
            escapeHtml(item.name) +
            '</strong><span>' +
            escapeHtml(item.note) +
            "</span></div>" +
            statusPill(item.status) +
            "</div>"
          );
        })
        .join("") +
      "</div>"
    );
  }

  function renderPermissionTable(roles, moduleIds) {
    return (
      '<table class="permission-table"><thead><tr><th>Modul</th>' +
      roles
        .map(function (role) {
          return "<th>" + escapeHtml(role) + "</th>";
        })
        .join("") +
      "</tr></thead><tbody>" +
      moduleIds
        .map(function (moduleId) {
          const label = modules.find(function (item) {
            return item.id === moduleId;
          }).label;
          return (
            "<tr><td><strong>" +
            escapeHtml(label) +
            "</strong></td>" +
            roles
              .map(function (role) {
                const checked = state.data.settings.permissions[role][moduleId];
                return (
                  '<td><label class="switch" title="Hak akses"><input type="checkbox" data-action="toggle-permission" data-role="' +
                  role +
                  '" data-module="' +
                  moduleId +
                  '"' +
                  (checked ? " checked" : "") +
                  "><span></span></label></td>"
                );
              })
              .join("") +
            "</tr>"
          );
        })
        .join("") +
      "</tbody></table>"
    );
  }

  function openRecordModal(moduleId, recordId) {
    const cfg = recordConfigs[moduleId];
    const record =
      state.data[moduleId].find(function (item) {
        return item.id === recordId;
      }) || {};
    const isEdit = Boolean(recordId);
    const modalTitle = (isEdit ? "Edit " : "Tambah ") + cfg.entity;
    const draftRecord = Object.assign({ id: generateId(moduleId), status: cfg.statuses[0] }, record);

    els.modalRoot.innerHTML =
      '<div class="modal-backdrop" role="presentation"><div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">' +
      '<div class="modal-header"><h2 id="modalTitle">' +
      escapeHtml(modalTitle) +
      '</h2><button class="icon-button" type="button" title="Tutup" data-action="close-modal">' +
      icon("x") +
      '</button></div><form id="recordForm" data-module="' +
      moduleId +
      '" data-edit-id="' +
      escapeAttribute(recordId || "") +
      '"><div class="form-grid">' +
      cfg.fields
        .map(function (field) {
          return renderFormField(field, cfg, draftRecord[field.id]);
        })
        .join("") +
      '</div><div class="modal-footer"><button class="ghost-button" type="button" data-action="close-modal">' +
      icon("x") +
      '<span>Batal</span></button><button class="primary-button" type="submit">' +
      icon("save") +
      "<span>Simpan</span></button></div></form></div></div>";
  }

  function renderFormField(field, cfg, value) {
    const required = field.required ? " required" : "";
    const readonly = field.readonly ? " readonly" : "";
    const full = field.full ? " full" : "";
    const safeValue = value == null ? "" : value;
    let input = "";

    if (field.type === "select") {
      const options = field.options || (field.id === cfg.statusField ? cfg.statuses : []);
      input =
        '<select id="field-' +
        field.id +
        '" name="' +
        field.id +
        '"' +
        required +
        ">" +
        options
          .map(function (option) {
            return (
              '<option value="' +
              escapeAttribute(option) +
              '"' +
              (option === safeValue ? " selected" : "") +
              ">" +
              escapeHtml(option) +
              "</option>"
            );
          })
          .join("") +
        "</select>";
    } else if (field.type === "textarea") {
      input =
        '<textarea id="field-' +
        field.id +
        '" name="' +
        field.id +
        '"' +
        required +
        ">" +
        escapeHtml(safeValue) +
        "</textarea>";
    } else {
      input =
        '<input id="field-' +
        field.id +
        '" name="' +
        field.id +
        '" type="' +
        field.type +
        '" value="' +
        escapeAttribute(safeValue) +
        '"' +
        required +
        readonly +
        ">";
    }

    return (
      '<div class="form-field' +
      full +
      '"><label for="field-' +
      field.id +
      '">' +
      escapeHtml(field.label) +
      "</label>" +
      input +
      "</div>"
    );
  }

  function closeModal() {
    els.modalRoot.innerHTML = "";
  }

  function getFilteredRecords(moduleId) {
    const cfg = recordConfigs[moduleId];
    const query = state.query;
    return state.data[moduleId].filter(function (record) {
      const statusMatch = state.statusFilter === "all" || record[cfg.statusField] === state.statusFilter;
      const queryMatch =
        !query ||
        Object.values(record)
          .join(" ")
          .toLowerCase()
          .includes(query);
      return statusMatch && queryMatch;
    });
  }

  function getDashboardMetrics() {
    const salesRevenue = sum(
      state.data.sales.filter(function (item) {
        return item.status !== "Draft";
      }),
      "amount"
    );
    const openAr = sum(
      state.data.finance.filter(function (item) {
        return item.type === "AR Invoice" && item.status !== "Paid";
      }),
      "amount"
    );
    const inventoryValue = sum(state.data.inventory, "value");
    const payroll = sum(state.data.hr, "salary");

    return [
      {
        label: "Revenue",
        value: currency.format(salesRevenue),
        note: "Order confirmed sampai paid",
        icon: "sales",
        color: "#2563eb",
        bg: "#dbeafe",
        progress: 82
      },
      {
        label: "Piutang Terbuka",
        value: currency.format(openAr),
        note: "AR invoice belum lunas",
        icon: "wallet",
        color: "#d97706",
        bg: "#fef3c7",
        progress: 48
      },
      {
        label: "Nilai Stok",
        value: currency.format(inventoryValue),
        note: getLowStockCount() + " SKU low stock",
        icon: "box",
        color: "#059669",
        bg: "#d1fae5",
        progress: 68
      },
      {
        label: "Payroll",
        value: currency.format(payroll),
        note: "Estimasi payroll bulanan",
        icon: "users",
        color: "#7c3aed",
        bg: "#ede9fe",
        progress: 74
      }
    ];
  }

  function getModuleMetrics(moduleId) {
    const cfg = recordConfigs[moduleId];
    const records = state.data[moduleId];
    const value = sum(records, cfg.valueField);
    const statusCounts = countBy(records, cfg.statusField);
    const attention = getAttentionCount(moduleId);
    const avgProgress = average(records, "progress");

    if (moduleId === "inventory") {
      return [
        metric("SKU Aktif", number.format(records.length), "Item lintas gudang", "box", "#2563eb", "#dbeafe", 78),
        metric("Nilai Stok", currency.format(value), "Valuasi persediaan", "wallet", "#059669", "#d1fae5", 66),
        metric("Low Stock", number.format(attention), "Di bawah minimum", "alert", "#d97706", "#fef3c7", attention ? 42 : 88),
        metric("Gudang", number.format(Object.keys(groupBy(records, "warehouse")).length), "Lokasi aktif", "landmark", "#0891b2", "#cffafe", 70)
      ];
    }

    if (moduleId === "manufacturing" || moduleId === "projects") {
      return [
        metric("Total", number.format(records.length), "Record aktif", "chart", "#2563eb", "#dbeafe", 72),
        metric("Progress", Math.round(avgProgress) + "%", "Rata-rata penyelesaian", "check", "#059669", "#d1fae5", avgProgress),
        metric("Atensi", number.format(attention), "Perlu tindakan", "alert", "#d97706", "#fef3c7", attention ? 38 : 88),
        metric("Nilai", formatMetricValue(moduleId, value), "Nilai proses", "wallet", "#7c3aed", "#ede9fe", 64)
      ];
    }

    if (moduleId === "hr") {
      return [
        metric("Pegawai", number.format(records.length), "Headcount aktif", "users", "#2563eb", "#dbeafe", 80),
        metric("Payroll", currency.format(value), "Total kompensasi", "wallet", "#7c3aed", "#ede9fe", 74),
        metric("Kehadiran", Math.round(average(records, "attendance")) + "%", "Rata-rata periode", "check", "#059669", "#d1fae5", average(records, "attendance")),
        metric("On Leave", number.format(statusCounts["On Leave"] || 0), "Absensi terjadwal", "calendar", "#d97706", "#fef3c7", 36)
      ];
    }

    return [
      metric("Total", number.format(records.length), "Record dalam modul", "chart", "#2563eb", "#dbeafe", 78),
      metric("Nilai", formatMetricValue(moduleId, value), "Total nilai transaksi", "wallet", "#059669", "#d1fae5", 70),
      metric("Atensi", number.format(attention), "Status perlu follow up", "alert", "#d97706", "#fef3c7", attention ? 42 : 88),
      metric("Closed", number.format(getClosedCount(moduleId)), "Selesai atau paid", "check", "#0891b2", "#cffafe", 58)
    ];
  }

  function metric(label, value, note, iconName, color, bg, progress) {
    return { label: label, value: value, note: note, icon: iconName, color: color, bg: bg, progress: progress };
  }

  function getAttentionCount(moduleId) {
    const records = state.data[moduleId];
    if (moduleId === "inventory") return getLowStockCount();
    const cfg = recordConfigs[moduleId];
    const riskStatuses = ["Overdue", "Delayed", "At Risk", "Low Stock", "Waiting Approval", "Blocked", "Lost"];
    return records.filter(function (record) {
      return riskStatuses.includes(record[cfg.statusField]);
    }).length;
  }

  function getClosedCount(moduleId) {
    const records = state.data[moduleId];
    const cfg = recordConfigs[moduleId];
    const closedStatuses = ["Paid", "Closed", "Completed", "Won", "Posted", "Received"];
    return records.filter(function (record) {
      return closedStatuses.includes(record[cfg.statusField]);
    }).length;
  }

  function getLowStockCount() {
    return state.data.inventory.filter(function (item) {
      return Number(item.onHand) <= Number(item.minStock);
    }).length;
  }

  function updateApproval(id, action) {
    const approval = state.data.approvals.find(function (item) {
      return item.id === id;
    });
    if (!approval) return;
    approval.status = action === "approve" ? "Approved" : "Rejected";
    persist();
    render();
    showToast("Approval " + approval.title + " menjadi " + approval.status + ".");
  }

  function deleteRecord(moduleId, recordId) {
    const cfg = recordConfigs[moduleId];
    const record = state.data[moduleId].find(function (item) {
      return item.id === recordId;
    });
    if (!record) return;
    if (!window.confirm("Hapus " + cfg.entity + " " + record.id + "?")) return;
    state.data[moduleId] = state.data[moduleId].filter(function (item) {
      return item.id !== recordId;
    });
    persist();
    render();
    showToast(capitalize(cfg.entity) + " berhasil dihapus.");
  }

  function advanceRecord(moduleId, recordId) {
    const cfg = recordConfigs[moduleId];
    const record = state.data[moduleId].find(function (item) {
      return item.id === recordId;
    });
    if (!record) return;
    const current = record[cfg.statusField];
    const index = cfg.statuses.indexOf(current);
    if (index < 0 || index === cfg.statuses.length - 1) {
      showToast("Status " + record.id + " sudah berada di tahap akhir.");
      return;
    }
    record[cfg.statusField] = cfg.statuses[index + 1];
    persist();
    render();
    showToast(record.id + " dipindahkan ke " + record[cfg.statusField] + ".");
  }

  function normalizeRecord(moduleId, record) {
    if (moduleId === "inventory" && record.status !== "Blocked" && record.status !== "Reserved") {
      record.status = Number(record.onHand) <= Number(record.minStock) ? "Low Stock" : "Available";
    }
    if (moduleId === "manufacturing" && Number(record.progress) >= 100) {
      record.status = "Completed";
    }
    if (moduleId === "projects" && Number(record.progress) >= 100) {
      record.status = "Completed";
    }
  }

  function generateId(moduleId) {
    const cfg = recordConfigs[moduleId];
    const date = new Date();
    const yy = String(date.getFullYear()).slice(-2);
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    let sequence = state.data[moduleId].length + 1;
    let id = cfg.prefix + "-" + yy + mm + "-" + String(sequence).padStart(3, "0");
    while (
      state.data[moduleId].some(function (item) {
        return item.id === id;
      })
    ) {
      sequence += 1;
      id = cfg.prefix + "-" + yy + mm + "-" + String(sequence).padStart(3, "0");
    }
    return id;
  }

  function exportActiveData() {
    const moduleId = state.activeModule;
    let filename = "horizon-erp-" + moduleId + ".json";
    let mime = "application/json";
    let output = "";

    if (recordConfigs[moduleId]) {
      const rows = getFilteredRecords(moduleId);
      filename = "horizon-erp-" + moduleId + ".csv";
      mime = "text/csv";
      output = toCsv(rows, recordConfigs[moduleId].columns);
    } else {
      output = JSON.stringify(moduleId === "dashboard" ? state.data : state.data[moduleId] || state.data, null, 2);
    }

    const blob = new Blob([output], { type: mime });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showToast("Export " + filename + " selesai.");
  }

  function toCsv(rows, columns) {
    const header = columns.map(function (column) {
      return csvEscape(column.label);
    });
    const body = rows.map(function (row) {
      return columns
        .map(function (column) {
          return csvEscape(row[column.id]);
        })
        .join(",");
    });
    return [header.join(",")].concat(body).join("\n");
  }

  function csvEscape(value) {
    const text = value == null ? "" : String(value);
    return '"' + text.replace(/"/g, '""') + '"';
  }

  function drawVisibleCharts() {
    drawRevenueChart(document.getElementById("revenueChart"));
    drawCashflowChart(document.getElementById("cashflowChart"));
    drawMarginChart(document.getElementById("marginChart"));
    drawWorkingCapitalChart(document.getElementById("workingCapitalChart"));
  }

  function drawRevenueChart(canvas) {
    if (!canvas) return;
    drawComboChart(canvas, {
      labels: ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun"],
      bars: [190, 230, 248, 275, 326, 360],
      line: [120, 144, 168, 195, 236, 266],
      barLabel: "Revenue",
      lineLabel: "Gross Profit"
    });
  }

  function drawCashflowChart(canvas) {
    if (!canvas) return;
    drawGroupedBars(canvas, {
      labels: ["Apr", "Mei", "Jun"],
      a: [260, 312, 345],
      b: [198, 224, 242],
      aLabel: "Inflow",
      bLabel: "Outflow"
    });
  }

  function drawMarginChart(canvas) {
    if (!canvas) return;
    drawHorizontalBars(canvas, [
      { label: "Produk A", value: 34, color: "#2563eb" },
      { label: "Produk B", value: 26, color: "#059669" },
      { label: "Service", value: 41, color: "#7c3aed" },
      { label: "Project", value: 22, color: "#d97706" }
    ]);
  }

  function drawWorkingCapitalChart(canvas) {
    if (!canvas) return;
    drawDonut(canvas, [
      { label: "AR", value: 42, color: "#2563eb" },
      { label: "Inventory", value: 36, color: "#059669" },
      { label: "AP", value: 22, color: "#d97706" }
    ]);
  }

  function prepareCanvas(canvas) {
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(260, rect.width);
    const height = Math.max(160, rect.height);
    const ratio = window.devicePixelRatio || 1;
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    const ctx = canvas.getContext("2d");
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    return { ctx: ctx, width: width, height: height };
  }

  function drawComboChart(canvas, data) {
    const drawing = prepareCanvas(canvas);
    const ctx = drawing.ctx;
    const width = drawing.width;
    const height = drawing.height;
    const max = Math.max.apply(null, data.bars.concat(data.line)) * 1.18;
    const left = 46;
    const right = 18;
    const top = 24;
    const bottom = 36;
    const chartW = width - left - right;
    const chartH = height - top - bottom;
    const step = chartW / data.labels.length;
    const barW = Math.max(18, step * 0.44);

    drawGrid(ctx, left, top, chartW, chartH);
    ctx.font = "12px Inter, sans-serif";
    ctx.fillStyle = "#667085";

    data.labels.forEach(function (label, index) {
      const x = left + step * index + step / 2;
      const barH = (data.bars[index] / max) * chartH;
      ctx.fillStyle = "#2563eb";
      roundedRect(ctx, x - barW / 2, top + chartH - barH, barW, barH, 5);
      ctx.fill();
      ctx.fillStyle = "#667085";
      ctx.textAlign = "center";
      ctx.fillText(label, x, height - 12);
    });

    ctx.beginPath();
    data.line.forEach(function (value, index) {
      const x = left + step * index + step / 2;
      const y = top + chartH - (value / max) * chartH;
      if (index === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.strokeStyle = "#059669";
    ctx.lineWidth = 3;
    ctx.stroke();
  }

  function drawGroupedBars(canvas, data) {
    const drawing = prepareCanvas(canvas);
    const ctx = drawing.ctx;
    const width = drawing.width;
    const height = drawing.height;
    const max = Math.max.apply(null, data.a.concat(data.b)) * 1.22;
    const left = 44;
    const right = 16;
    const top = 20;
    const bottom = 34;
    const chartW = width - left - right;
    const chartH = height - top - bottom;
    const step = chartW / data.labels.length;
    const barW = Math.max(18, step * 0.22);

    drawGrid(ctx, left, top, chartW, chartH);
    data.labels.forEach(function (label, index) {
      const x = left + step * index + step / 2;
      const aH = (data.a[index] / max) * chartH;
      const bH = (data.b[index] / max) * chartH;
      ctx.fillStyle = "#059669";
      roundedRect(ctx, x - barW - 3, top + chartH - aH, barW, aH, 5);
      ctx.fill();
      ctx.fillStyle = "#d97706";
      roundedRect(ctx, x + 3, top + chartH - bH, barW, bH, 5);
      ctx.fill();
      ctx.fillStyle = "#667085";
      ctx.font = "12px Inter, sans-serif";
      ctx.textAlign = "center";
      ctx.fillText(label, x, height - 10);
    });
  }

  function drawHorizontalBars(canvas, rows) {
    const drawing = prepareCanvas(canvas);
    const ctx = drawing.ctx;
    const width = drawing.width;
    const top = 28;
    const rowH = 44;
    const left = 90;
    const max = Math.max.apply(null, rows.map(function (row) { return row.value; })) * 1.12;

    ctx.font = "12px Inter, sans-serif";
    rows.forEach(function (row, index) {
      const y = top + index * rowH;
      const w = ((width - left - 28) * row.value) / max;
      ctx.fillStyle = "#667085";
      ctx.textAlign = "left";
      ctx.fillText(row.label, 14, y + 18);
      ctx.fillStyle = "#edf2f7";
      roundedRect(ctx, left, y, width - left - 28, 24, 5);
      ctx.fill();
      ctx.fillStyle = row.color;
      roundedRect(ctx, left, y, w, 24, 5);
      ctx.fill();
      ctx.fillStyle = "#172033";
      ctx.textAlign = "right";
      ctx.fillText(row.value + "%", width - 14, y + 18);
    });
  }

  function drawDonut(canvas, rows) {
    const drawing = prepareCanvas(canvas);
    const ctx = drawing.ctx;
    const width = drawing.width;
    const height = drawing.height;
    const total = rows.reduce(function (acc, row) { return acc + row.value; }, 0);
    const radius = Math.min(width, height) * 0.28;
    const cx = width * 0.34;
    const cy = height * 0.52;
    let start = -Math.PI / 2;

    rows.forEach(function (row) {
      const slice = (row.value / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(cx, cy, radius, start, start + slice);
      ctx.lineWidth = 24;
      ctx.strokeStyle = row.color;
      ctx.stroke();
      start += slice;
    });

    ctx.fillStyle = "#172033";
    ctx.font = "800 22px Inter, sans-serif";
    ctx.textAlign = "center";
    ctx.fillText(total + "%", cx, cy + 6);

    ctx.font = "13px Inter, sans-serif";
    rows.forEach(function (row, index) {
      const x = width * 0.66;
      const y = height * 0.34 + index * 34;
      ctx.fillStyle = row.color;
      roundedRect(ctx, x, y - 12, 14, 14, 4);
      ctx.fill();
      ctx.fillStyle = "#344054";
      ctx.textAlign = "left";
      ctx.fillText(row.label + " " + row.value + "%", x + 22, y);
    });
  }

  function drawGrid(ctx, left, top, width, height) {
    ctx.strokeStyle = "#e7edf3";
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i += 1) {
      const y = top + (height / 4) * i;
      ctx.beginPath();
      ctx.moveTo(left, y);
      ctx.lineTo(left + width, y);
      ctx.stroke();
    }
  }

  function roundedRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, Math.abs(height) / 2, Math.abs(width) / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + width - r, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + r);
    ctx.lineTo(x + width, y + height - r);
    ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
    ctx.lineTo(x + r, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - r);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
  }

  function formatCell(value, column) {
    if (column.type === "id") return '<span class="cell-strong">' + escapeHtml(value) + "</span>";
    if (column.type === "currency") return currency.format(Number(value || 0));
    if (column.type === "number") return number.format(Number(value || 0));
    if (column.type === "percent") return number.format(Number(value || 0)) + "%";
    if (column.type === "date") return formatDate(value);
    if (column.type === "status") return statusPill(value);
    return escapeHtml(value == null ? "-" : value);
  }

  function statusPill(status) {
    const token = getStatusToken(status);
    return (
      '<span class="status" style="--status-color:' +
      token.color +
      ";--status-bg:" +
      token.bg +
      '">' +
      escapeHtml(status || "-") +
      "</span>"
    );
  }

  function getStatusToken(status) {
    const text = String(status || "").toLowerCase();
    if (["paid", "approved", "posted", "received", "closed", "won", "completed", "active", "available", "on track", "running", "in use"].includes(text)) {
      return { color: "#047857", bg: "#d1fae5" };
    }
    if (["draft", "waiting approval", "planned", "planning", "released", "inspection", "recruiting", "probation", "maintenance", "on leave", "open"].includes(text)) {
      return { color: "#b45309", bg: "#fef3c7" };
    }
    if (["confirmed", "invoiced", "ordered", "reserved", "qualified", "proposal", "new", "negotiation", "budget"].includes(text)) {
      return { color: "#1d4ed8", bg: "#dbeafe" };
    }
    if (["overdue", "delayed", "at risk", "low stock", "blocked", "lost", "inactive", "retired", "rejected"].includes(text)) {
      return { color: "#b91c1c", bg: "#fee2e2" };
    }
    return { color: "#344054", bg: "#eef2f7" };
  }

  function getModuleCount(moduleId) {
    if (recordConfigs[moduleId]) return state.data[moduleId].length;
    if (moduleId === "dashboard") {
      return state.data.approvals.filter(function (item) {
        return item.status === "Pending";
      }).length;
    }
    if (moduleId === "reports") return 18;
    if (moduleId === "settings") {
      return state.data.settings.integrations.filter(function (item) {
        return item.enabled;
      }).length;
    }
    return 0;
  }

  function formatMetricValue(moduleId, value) {
    if (moduleId === "manufacturing") return number.format(value) + " unit";
    return currency.format(value);
  }

  function formatDate(value) {
    if (!value) return "-";
    const date = new Date(String(value).includes("T") ? value : value + "T00:00:00");
    if (Number.isNaN(date.getTime())) return "-";
    return dateFormat.format(date);
  }

  function sum(records, field) {
    return records.reduce(function (total, item) {
      return total + Number(item[field] || 0);
    }, 0);
  }

  function average(records, field) {
    if (!records.length) return 0;
    return sum(records, field) / records.length;
  }

  function countBy(records, field) {
    return records.reduce(function (acc, item) {
      const key = item[field] || "-";
      acc[key] = (acc[key] || 0) + 1;
      return acc;
    }, {});
  }

  function groupBy(records, field) {
    return records.reduce(function (acc, item) {
      const key = item[field] || "-";
      if (!acc[key]) acc[key] = [];
      acc[key].push(item);
      return acc;
    }, {});
  }

  function persist() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state.data));
  }

  function loadData() {
    const seed = createSeedData();
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      if (!saved) return seed;
      return Object.assign(seed, saved, {
        settings: Object.assign(seed.settings, saved.settings || {})
      });
    } catch (error) {
      return seed;
    }
  }

  function showToast(message) {
    els.toast.textContent = message;
    els.toast.classList.add("is-visible");
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(function () {
      els.toast.classList.remove("is-visible");
    }, 2600);
  }

  function capitalize(value) {
    return String(value || "").charAt(0).toUpperCase() + String(value || "").slice(1);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, "&#096;");
  }

  function icon(name) {
    const icons = {
      dashboard: '<path d="M4 13h6V4H4v9z"></path><path d="M14 20h6V4h-6v16z"></path><path d="M4 20h6v-3H4v3z"></path>',
      sales: '<path d="M4 19V5"></path><path d="M4 19h16"></path><path d="m7 15 3-4 3 2 4-7"></path>',
      cart: '<path d="M6 6h15l-2 8H8L6 3H3"></path><circle cx="9" cy="20" r="1.5"></circle><circle cx="18" cy="20" r="1.5"></circle>',
      box: '<path d="m3 7 9-4 9 4-9 4-9-4z"></path><path d="M3 7v10l9 4 9-4V7"></path><path d="M12 11v10"></path>',
      wallet: '<path d="M4 7h14a3 3 0 0 1 3 3v8H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14"></path><path d="M16 13h5"></path>',
      handshake: '<path d="m7 11 3-3 4 4 2-2 3 3"></path><path d="M3 12h4l3 6 3-2 2 2 3-6h3"></path>',
      factory: '<path d="M3 21V9l6 4V9l6 4h6v8H3z"></path><path d="M14 21v-4h3v4"></path><path d="M6 17h1"></path><path d="M10 17h1"></path>',
      briefcase: '<path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"></path><path d="M4 7h16v12H4z"></path><path d="M4 12h16"></path>',
      users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
      landmark: '<path d="M3 10h18"></path><path d="M5 10v8"></path><path d="M9 10v8"></path><path d="M15 10v8"></path><path d="M19 10v8"></path><path d="M3 21h18"></path><path d="m12 3 8 4H4l8-4z"></path>',
      chart: '<path d="M4 19V5"></path><path d="M4 19h16"></path><path d="M8 16V9"></path><path d="M13 16V6"></path><path d="M18 16v-4"></path>',
      settings: '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 3-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-2-3 .1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-3 .1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 2 3-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>',
      search: '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>',
      plus: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
      save: '<path d="M5 3h12l2 2v16H5z"></path><path d="M8 3v6h8V3"></path><path d="M8 21v-7h8v7"></path>',
      download: '<path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path>',
      edit: '<path d="M4 20h4l10-10-4-4L4 16v4z"></path><path d="m13 7 4 4"></path>',
      arrowRight: '<path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path>',
      trash: '<path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 14h10l1-14"></path><path d="M9 7V4h6v3"></path>',
      check: '<path d="m5 12 4 4L19 6"></path>',
      x: '<path d="M6 6l12 12"></path><path d="M18 6 6 18"></path>',
      alert: '<path d="M12 3 2 21h20L12 3z"></path><path d="M12 9v5"></path><path d="M12 18h.01"></path>',
      calendar: '<path d="M4 5h16v16H4z"></path><path d="M8 3v4"></path><path d="M16 3v4"></path><path d="M4 10h16"></path>',
      refresh: '<path d="M20 12a8 8 0 0 1-14 5"></path><path d="M4 17h4v-4"></path><path d="M4 12a8 8 0 0 1 14-5"></path><path d="M20 7h-4v4"></path>'
    };
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (icons[name] || icons.chart) + "</svg>";
  }

  function createSeedData() {
    return {
      sales: [
        { id: "SO-2605-001", customer: "PT Sinar Utama", channel: "B2B", amount: 128000000, status: "Confirmed", owner: "Nadia", due: "2026-05-28", notes: "Kontrak tahunan" },
        { id: "SO-2605-002", customer: "CV Karya Logistik", channel: "Project", amount: 94000000, status: "Invoiced", owner: "Raka", due: "2026-06-04", notes: "Termin 2" },
        { id: "SO-2605-003", customer: "Retail Nusantara", channel: "Retail", amount: 36500000, status: "Paid", owner: "Maya", due: "2026-05-18", notes: "Repeat order" },
        { id: "SO-2605-004", customer: "PT Prima Energi", channel: "B2B", amount: 184000000, status: "Draft", owner: "Dimas", due: "2026-06-12", notes: "Menunggu approval harga" }
      ],
      procurement: [
        { id: "PO-2605-001", vendor: "PT Baja Sentosa", category: "Raw Material", amount: 87500000, status: "Waiting Approval", buyer: "Tari", due: "2026-05-27", notes: "Harga kontrak Q2" },
        { id: "PO-2605-002", vendor: "Global IT Supply", category: "IT", amount: 42500000, status: "Ordered", buyer: "Eka", due: "2026-06-02", notes: "Laptop sales team" },
        { id: "PO-2605-003", vendor: "Multi Teknik", category: "Spare Part", amount: 18500000, status: "Received", buyer: "Fadil", due: "2026-05-20", notes: "Emergency stock" },
        { id: "PO-2605-004", vendor: "Bersih Abadi", category: "Service", amount: 9600000, status: "Closed", buyer: "Tari", due: "2026-05-16", notes: "Facility service" }
      ],
      inventory: [
        { id: "SKU-2605-001", item: "Finished Product A", category: "Finished Goods", warehouse: "Jakarta", onHand: 1280, minStock: 700, value: 256000000, status: "Available" },
        { id: "SKU-2605-002", item: "Raw Material Steel", category: "Raw Material", warehouse: "Surabaya", onHand: 310, minStock: 500, value: 93000000, status: "Low Stock" },
        { id: "SKU-2605-003", item: "Carton Premium", category: "Packaging", warehouse: "Bandung", onHand: 820, minStock: 600, value: 24600000, status: "Available" },
        { id: "SKU-2605-004", item: "Bearing MRO", category: "MRO", warehouse: "Medan", onHand: 42, minStock: 90, value: 16800000, status: "Low Stock" },
        { id: "SKU-2605-005", item: "Finished Product B", category: "Finished Goods", warehouse: "Jakarta", onHand: 220, minStock: 200, value: 99000000, status: "Reserved" }
      ],
      finance: [
        { id: "FIN-2605-001", type: "AR Invoice", account: "1102 Piutang Usaha", partner: "PT Sinar Utama", amount: 128000000, status: "Open", due: "2026-06-11", notes: "Invoice SO-2605-001" },
        { id: "FIN-2605-002", type: "AP Bill", account: "2101 Utang Vendor", partner: "PT Baja Sentosa", amount: 87500000, status: "Approved", due: "2026-06-03", notes: "PO raw material" },
        { id: "FIN-2605-003", type: "Journal", account: "5101 COGS", partner: "Internal", amount: 68400000, status: "Posted", due: "2026-05-21", notes: "Posting produksi" },
        { id: "FIN-2605-004", type: "AR Invoice", account: "1102 Piutang Usaha", partner: "CV Karya Logistik", amount: 93400000, status: "Overdue", due: "2026-05-10", notes: "Follow up collection" }
      ],
      crm: [
        { id: "CRM-2605-001", lead: "Ayu Lestari", company: "PT Atlas Retail", value: 158000000, stage: "Qualified", owner: "Nadia", nextAction: "2026-05-25", notes: "Demo onsite" },
        { id: "CRM-2605-002", lead: "Bima Hartono", company: "Cipta Properti", value: 310000000, stage: "Proposal", owner: "Raka", nextAction: "2026-05-29", notes: "Proposal teknis" },
        { id: "CRM-2605-003", lead: "Citra Dewi", company: "Makmur Distribusi", value: 74000000, stage: "Negotiation", owner: "Maya", nextAction: "2026-06-01", notes: "Negosiasi termin" },
        { id: "CRM-2605-004", lead: "Doni Saputra", company: "Nusantara Farma", value: 42000000, stage: "New", owner: "Dimas", nextAction: "2026-05-24", notes: "Inbound website" }
      ],
      manufacturing: [
        { id: "WO-2605-001", product: "Finished Product A", quantity: 900, line: "Line A", progress: 82, status: "Running", due: "2026-05-26", notes: "Shift tambahan" },
        { id: "WO-2605-002", product: "Finished Product B", quantity: 420, line: "Line B", progress: 45, status: "Released", due: "2026-06-02", notes: "Material lengkap" },
        { id: "WO-2605-003", product: "Custom Project C", quantity: 120, line: "Outsource", progress: 66, status: "Inspection", due: "2026-05-30", notes: "QC batch 2" },
        { id: "WO-2605-004", product: "Finished Product D", quantity: 680, line: "Line C", progress: 28, status: "Delayed", due: "2026-05-24", notes: "Menunggu spare part" }
      ],
      projects: [
        { id: "PRJ-2605-001", project: "Implementasi WMS", customer: "PT Sinar Utama", manager: "Laras", budget: 420000000, progress: 72, status: "On Track", due: "2026-07-15" },
        { id: "PRJ-2605-002", project: "Integrasi E-Invoice", customer: "Internal", manager: "Arman", budget: 185000000, progress: 48, status: "At Risk", due: "2026-06-20" },
        { id: "PRJ-2605-003", project: "Rollout Cabang Medan", customer: "Internal", manager: "Mira", budget: 265000000, progress: 35, status: "Planning", due: "2026-08-01" },
        { id: "PRJ-2605-004", project: "Upgrade Mesin Line B", customer: "Operations", manager: "Bayu", budget: 145000000, progress: 100, status: "Completed", due: "2026-05-14" }
      ],
      hr: [
        { id: "EMP-2605-001", employee: "Nadia Putri", department: "Sales", role: "Sales Manager", salary: 18500000, attendance: 96, status: "Active", joinDate: "2021-03-12" },
        { id: "EMP-2605-002", employee: "Arman Pratama", department: "IT", role: "ERP Lead", salary: 21000000, attendance: 93, status: "Active", joinDate: "2020-08-17" },
        { id: "EMP-2605-003", employee: "Tari Ananda", department: "Operations", role: "Procurement Lead", salary: 16800000, attendance: 89, status: "On Leave", joinDate: "2019-11-04" },
        { id: "EMP-2605-004", employee: "Mira Kartika", department: "Finance", role: "Controller", salary: 19800000, attendance: 94, status: "Active", joinDate: "2018-02-22" },
        { id: "EMP-2605-005", employee: "Fadil Maulana", department: "Operations", role: "Warehouse Supervisor", salary: 14200000, attendance: 91, status: "Probation", joinDate: "2026-02-01" }
      ],
      assets: [
        { id: "AST-2605-001", asset: "Forklift Toyota 3T", location: "Gudang Jakarta", owner: "Fadil", value: 280000000, condition: "Good", status: "In Use", nextService: "2026-06-18" },
        { id: "AST-2605-002", asset: "Server ERP Node 01", location: "Data Center", owner: "Arman", value: 145000000, condition: "Excellent", status: "In Use", nextService: "2026-07-02" },
        { id: "AST-2605-003", asset: "Mesin Packing Line B", location: "Pabrik Surabaya", owner: "Bayu", value: 520000000, condition: "Fair", status: "Maintenance", nextService: "2026-05-26" },
        { id: "AST-2605-004", asset: "Kendaraan Operasional", location: "Kantor Pusat", owner: "Tari", value: 188000000, condition: "Good", status: "Available", nextService: "2026-06-12" }
      ],
      approvals: [
        { id: "APR-001", title: "PO Raw Material", requester: "Tari Ananda", amount: 87500000, status: "Pending", due: "2026-05-24" },
        { id: "APR-002", title: "Diskon Project Prima", requester: "Dimas Arya", amount: 184000000, status: "Pending", due: "2026-05-25" },
        { id: "APR-003", title: "Budget Integrasi E-Invoice", requester: "Arman Pratama", amount: 185000000, status: "Pending", due: "2026-05-27" }
      ],
      settings: {
        permissions: {
          Admin: { sales: true, procurement: true, inventory: true, finance: true, crm: true, manufacturing: true, projects: true, hr: true, assets: true },
          Manager: { sales: true, procurement: true, inventory: true, finance: true, crm: true, manufacturing: true, projects: true, hr: false, assets: true },
          Finance: { sales: true, procurement: true, inventory: false, finance: true, crm: false, manufacturing: false, projects: true, hr: false, assets: true },
          Staff: { sales: true, procurement: false, inventory: true, finance: false, crm: true, manufacturing: true, projects: false, hr: false, assets: false }
        },
        integrations: [
          { id: "bank", name: "Bank Statement", description: "Rekonsiliasi otomatis mutasi bank.", enabled: true },
          { id: "tax", name: "E-Faktur", description: "Sinkronisasi pajak keluaran dan masukan.", enabled: true },
          { id: "whatsapp", name: "WhatsApp Reminder", description: "Reminder invoice dan approval.", enabled: false },
          { id: "marketplace", name: "Marketplace", description: "Import order dari kanal online.", enabled: true },
          { id: "bi", name: "BI Warehouse", description: "Pipeline data untuk dashboard analitik.", enabled: false },
          { id: "hris", name: "HRIS Payroll", description: "Sinkronisasi payroll dan absensi.", enabled: true }
        ]
      }
    };
  }

  document.addEventListener("DOMContentLoaded", init);
})();
