/**
 * admin.js — Admin Dashboard JavaScript
 * Connects the admin UI to the PHP backend APIs
 */

const ADMIN_API = 'http://localhost/food-ordering/backend/api/admin/dashboard_api.php';
const FOOD_API  = 'http://localhost/food-ordering/backend/api/admin/food_api.php';

// ── Toast Notification ────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const colors = { success: '#28a745', error: '#dc3545', info: '#0d6efd' };
    const icons  = { success: 'check-circle', error: 'times-circle', info: 'info-circle' };
    const el = document.createElement('div');
    el.style.cssText = `background:${colors[type]};color:#fff;padding:13px 18px;border-radius:12px;
        box-shadow:0 5px 20px rgba(0,0,0,.2);margin-top:8px;min-width:240px;
        animation:slideIn .35s ease;font-size:.88rem;`;
    el.innerHTML = `<i class="fas fa-${icons[type]} me-2"></i>${msg}`;
    document.getElementById('adminToast').appendChild(el);
    setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(() => el.remove(), 300); }, 3000);
}

// ── Universal fetch wrapper ───────────────────────────────────────────────────
async function apiFetch(url, opts = {}) {
    try {
        const res  = await fetch(url, { credentials: 'include', ...opts });
        const data = await res.json();
        return { ok: res.ok, data };
    } catch (e) {
        return { ok: false, data: { status: 'error', message: 'Network error — is XAMPP running?' } };
    }
}

// ── Status badge helper ───────────────────────────────────────────────────────
function statusBadge(status) {
    const map = {
        pending:          'badge-pending',
        processing:       'badge-processing',
        out_for_delivery: 'badge-out_for_delivery',
        completed:        'badge-completed',
        cancelled:        'badge-cancelled',
        failed:           'bg-danger text-white',
    };
    const cls = map[status] || 'bg-secondary text-white';
    return `<span class="badge px-3 py-2 rounded-pill ${cls}">${status.replace(/_/g,' ')}</span>`;
}

// ── Date formatter ────────────────────────────────────────────────────────────
function fmtDate(dt) {
    return dt ? new Date(dt).toLocaleDateString('en-US', { day:'2-digit', month:'short', year:'numeric' }) : '—';
}

// ══════════════════════════════════════════════════════════════════════════════
// SIDEBAR NAVIGATION
// ══════════════════════════════════════════════════════════════════════════════
document.querySelectorAll('#sidebar .nav-link[data-section]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const target = link.dataset.section;
        document.querySelectorAll('#sidebar .nav-link').forEach(l => l.classList.remove('active'));
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        link.classList.add('active');
        document.getElementById(`section-${target}`).classList.add('active');
        document.getElementById('pageTitle').textContent = link.textContent.trim();
        loaders[target]?.();
    });
});

// ══════════════════════════════════════════════════════════════════════════════
// 1. DASHBOARD STATS
// ══════════════════════════════════════════════════════════════════════════════
async function loadDashboard() {
    const { ok, data } = await apiFetch(`${ADMIN_API}?action=stats`);
    if (!ok) { toast(data.message || 'Failed to load stats', 'error'); return; }

    const s = data.data;
    document.getElementById('stat-users').textContent   = s.total_users ?? '—';
    document.getElementById('stat-foods').textContent   = s.total_foods ?? '—';
    document.getElementById('stat-orders').textContent  = s.total_orders ?? '—';
    document.getElementById('stat-revenue').textContent = `$${s.total_revenue ?? '0.00'}`;

    const tbody = document.getElementById('recentOrdersBody');
    if (!s.recent_orders?.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>';
        return;
    }
    tbody.innerHTML = s.recent_orders.map(o => `
        <tr>
            <td><strong>#${o.order_id}</strong></td>
            <td>${o.full_name}</td>
            <td class="text-danger fw-semibold">$${parseFloat(o.total_amount).toFixed(2)}</td>
            <td>${statusBadge(o.order_status)}</td>
            <td class="text-muted small">${fmtDate(o.order_date)}</td>
            <td>
                <select class="form-select form-select-sm rounded-3 shadow-none" style="width:150px"
                        onchange="updateOrderStatus(${o.order_id}, this.value, this)">
                    ${['pending','processing','out_for_delivery','completed','cancelled'].map(s =>
                        `<option value="${s}" ${o.order_status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`
                    ).join('')}
                </select>
            </td>
        </tr>`).join('');
}

// ══════════════════════════════════════════════════════════════════════════════
// 2. ALL ORDERS
// ══════════════════════════════════════════════════════════════════════════════
async function loadOrders() {
    const tbody = document.getElementById('ordersBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';

    const { ok, data } = await apiFetch(`${ADMIN_API}?action=get_orders`);
    if (!ok || !data.data) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">${data.message}</td></tr>`;
        return;
    }
    tbody.innerHTML = data.data.length === 0
        ? '<tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr>'
        : data.data.map(o => `
        <tr>
            <td><strong>#${o.order_id}</strong></td>
            <td>
                <div class="fw-semibold">${o.full_name}</div>
                <div class="text-muted small">${o.email}</div>
            </td>
            <td class="text-danger fw-semibold">$${parseFloat(o.total_amount).toFixed(2)}</td>
            <td>${statusBadge(o.order_status)}</td>
            <td class="text-muted small">${fmtDate(o.order_date)}</td>
            <td class="d-flex gap-2">
                <select class="form-select form-select-sm rounded-3 shadow-none" style="width:150px"
                        onchange="updateOrderStatus(${o.order_id}, this.value, this)">
                    ${['pending','processing','out_for_delivery','completed','cancelled'].map(s =>
                        `<option value="${s}" ${o.order_status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`
                    ).join('')}
                </select>
                <button class="btn btn-sm btn-outline-danger rounded-circle" onclick="deleteOrder(${o.order_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`).join('');
}

async function updateOrderStatus(orderId, newStatus, selectEl) {
    selectEl.disabled = true;
    const { ok, data } = await apiFetch(ADMIN_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_order_status', order_id: orderId, order_status: newStatus })
    });
    selectEl.disabled = false;
    toast(data.message, ok ? 'success' : 'error');
    if (ok) {
        // Refresh dashboard recent orders if visible
        if (document.getElementById('section-dashboard').classList.contains('active')) loadDashboard();
    }
}

async function deleteOrder(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
    document.getElementById('confirmDeleteBtn').onclick = async () => {
        modal.hide();
        const { ok, data } = await apiFetch(ADMIN_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_order', order_id: orderId })
        });
        toast(data.message, ok ? 'success' : 'error');
        if (ok) loadOrders();
    };
}

// ══════════════════════════════════════════════════════════════════════════════
// 3. FOOD ITEMS
// ══════════════════════════════════════════════════════════════════════════════
async function loadFoods() {
    const tbody = document.getElementById('foodsBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';

    const { ok, data } = await apiFetch(FOOD_API);
    if (!ok || !data.data) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">${data.message}</td></tr>`;
        return;
    }
    tbody.innerHTML = data.data.length === 0
        ? '<tr><td colspan="6" class="text-center text-muted py-4">No food items yet.</td></tr>'
        : data.data.map(f => {
            const img = f.image_name
                ? `http://localhost/food-ordering/backend/uploads/foods/${f.image_name}`
                : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=60&q=80';
            return `<tr>
                <td><img src="${img}" width="52" height="52" class="rounded-3 object-fit-cover shadow-sm" onerror="this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=60&q=80'"></td>
                <td class="fw-semibold">${f.title}</td>
                <td><span class="badge bg-light text-dark rounded-pill px-3">${f.category}</span></td>
                <td class="text-danger fw-semibold">$${parseFloat(f.price).toFixed(2)}</td>
                <td>${f.is_active == 1
                    ? '<span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">Hidden</span>'}</td>
                <td class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary rounded-3" onclick='editFood(${JSON.stringify(f)})'><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger rounded-3" onclick="deleteFood(${f.food_id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
        }).join('');
}

function showAddFoodModal() {
    document.getElementById('foodModalTitle').textContent = 'Add Food Item';
    document.getElementById('foodForm').reset();
    document.getElementById('foodAction').value = 'add';
    document.getElementById('foodId').value = '';
    new bootstrap.Modal(document.getElementById('foodModal')).show();
}

function editFood(food) {
    document.getElementById('foodModalTitle').textContent = 'Edit Food Item';
    document.getElementById('foodAction').value  = 'update';
    document.getElementById('foodId').value      = food.food_id;
    document.getElementById('foodTitle').value   = food.title;
    document.getElementById('foodCategory').value= food.category;
    document.getElementById('foodDesc').value    = food.description ?? '';
    document.getElementById('foodPrice').value   = food.price;
    new bootstrap.Modal(document.getElementById('foodModal')).show();
}

document.getElementById('foodForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('foodSubmitBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    btn.disabled = true;

    const formData = new FormData(document.getElementById('foodForm'));
    const { ok, data } = await apiFetch(FOOD_API, { method: 'POST', body: formData, headers: {} });

    btn.innerHTML = 'Save Food Item';
    btn.disabled = false;
    toast(data.message, ok ? 'success' : 'error');
    if (ok) {
        bootstrap.Modal.getInstance(document.getElementById('foodModal'))?.hide();
        loadFoods();
    }
});

async function deleteFood(foodId) {
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
    document.getElementById('confirmDeleteBtn').onclick = async () => {
        modal.hide();
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('food_id', foodId);
        const { ok, data } = await apiFetch(FOOD_API, { method: 'POST', body: formData, headers: {} });
        toast(data.message, ok ? 'success' : 'error');
        if (ok) loadFoods();
    };
}

// ══════════════════════════════════════════════════════════════════════════════
// 4. USERS
// ══════════════════════════════════════════════════════════════════════════════
async function loadUsers() {
    const tbody = document.getElementById('usersBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';

    const { ok, data } = await apiFetch(`${ADMIN_API}?action=get_users`);
    if (!ok || !data.data) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">${data.message}</td></tr>`;
        return;
    }
    tbody.innerHTML = data.data.length === 0
        ? '<tr><td colspan="5" class="text-center text-muted py-4">No users yet.</td></tr>'
        : data.data.map((u, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px;height:34px;font-size:.8rem;">
                        ${u.full_name.charAt(0).toUpperCase()}
                    </div>
                    ${u.full_name}
                </div>
            </td>
            <td class="text-muted">${u.email}</td>
            <td class="text-muted">${u.phone || '—'}</td>
            <td class="text-muted small">${fmtDate(u.created_at)}</td>
        </tr>`).join('');
}

// ══════════════════════════════════════════════════════════════════════════════
// 5. PAYMENTS
// ══════════════════════════════════════════════════════════════════════════════
async function loadPayments() {
    const tbody = document.getElementById('paymentsBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';

    const { ok, data } = await apiFetch(`${ADMIN_API}?action=get_payments`);
    if (!ok || !data.data) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${data.message}</td></tr>`;
        return;
    }
    tbody.innerHTML = data.data.length === 0
        ? '<tr><td colspan="7" class="text-center text-muted py-4">No payments yet.</td></tr>'
        : data.data.map(p => `
        <tr>
            <td><code class="small">${p.transaction_id || '—'}</code></td>
            <td>
                <div class="fw-semibold">${p.full_name}</div>
                <div class="text-muted small">${p.email}</div>
            </td>
            <td>#${p.order_id}</td>
            <td class="text-danger fw-semibold">$${parseFloat(p.amount).toFixed(2)}</td>
            <td><span class="badge bg-light text-dark rounded-pill px-3">${p.payment_method?.replace(/_/g,' ')}</span></td>
            <td>${statusBadge(p.payment_status)}</td>
            <td class="text-muted small">${fmtDate(p.payment_date)}</td>
        </tr>`).join('');
}

// ══════════════════════════════════════════════════════════════════════════════
// LOGOUT
// ══════════════════════════════════════════════════════════════════════════════
async function adminLogout() {
    await apiFetch(ADMIN_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
    });
    window.location.href = 'index.html';
}

// ── Section loaders map ───────────────────────────────────────────────────────
const loaders = {
    dashboard: loadDashboard,
    orders:    loadOrders,
    foods:     loadFoods,
    users:     loadUsers,
    payments:  loadPayments,
};

// CSS for toast animation
const s = document.createElement('style');
s.textContent = '@keyframes slideIn{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}';
document.head.appendChild(s);

// Load dashboard on page open
loadDashboard();
