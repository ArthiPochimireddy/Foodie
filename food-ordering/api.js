/**
 * api.js — Frontend-Backend Integration Layer
 * Handles all AJAX requests to the PHP backend APIs
 * Base URL: Must be served from XAMPP (http://localhost/food-ordering/)
 */

const API_BASE = 'http://localhost/food-ordering/backend/api';

// ============================================================
// Utility: Universal fetch wrapper with JSON error handling
// ============================================================
async function apiFetch(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            ...options
        });
        const data = await response.json();
        return { ok: response.ok, status: response.status, data };
    } catch (err) {
        console.error('API Error:', err);
        return { ok: false, data: { status: 'error', message: 'Network error. Is XAMPP running?' } };
    }
}

// ============================================================
// Toast Notification System
// ============================================================
function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
        document.body.appendChild(toastContainer);
    }

    const bg = type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#ffc107';
    const toast = document.createElement('div');
    toast.style.cssText = `background:${bg};color:white;padding:14px 20px;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.2);font-family:Poppins,sans-serif;font-size:0.9rem;min-width:260px;max-width:380px;animation:slideInRight 0.4s ease;`;
    toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-circle'} me-2"></i>${message}`;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// ============================================================
// AUTH: Register
// ============================================================
async function registerUser(formData) {
    const btn = document.getElementById('registerBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';
    btn.disabled = true;

    // Client-side validation
    if (formData.password !== formData.confirm_password) {
        showToast('Passwords do not match!', 'error');
        btn.innerHTML = 'Create Account';
        btn.disabled = false;
        return;
    }
    if (formData.password.length < 6) {
        showToast('Password must be at least 6 characters.', 'error');
        btn.innerHTML = 'Create Account';
        btn.disabled = false;
        return;
    }

    const result = await apiFetch(`${API_BASE}/register.php`, {
        method: 'POST',
        body: JSON.stringify(formData)
    });

    btn.innerHTML = 'Create Account';
    btn.disabled = false;

    if (result.ok && result.data.status === 'success') {
        showToast(result.data.message, 'success');
        document.getElementById('registerForm').reset();
        // Switch to login tab after a short delay
        setTimeout(() => {
            const loginTab = document.getElementById('loginTabBtn');
            if (loginTab) loginTab.click();
        }, 1500);
    } else {
        showToast(result.data.message || 'Registration failed.', 'error');
    }
}

// ============================================================
// AUTH: Login
// ============================================================
async function loginUser(formData) {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
    btn.disabled = true;

    const result = await apiFetch(`${API_BASE}/login.php`, {
        method: 'POST',
        body: JSON.stringify(formData)
    });

    btn.innerHTML = 'Sign In';
    btn.disabled = false;

    if (result.ok && result.data.status === 'success') {
        showToast(`Welcome back, ${result.data.user?.name || ''}!`, 'success');
        // Save user session state locally
        sessionStorage.setItem('user', JSON.stringify(result.data.user));
        // Close modal and update navbar
        const modal = bootstrap.Modal.getInstance(document.getElementById('authModal'));
        if (modal) modal.hide();
        updateNavbarForLoggedInUser(result.data.user);
    } else {
        showToast(result.data.message || 'Login failed.', 'error');
    }
}

// ============================================================
// UI: Update Navbar after login
// ============================================================
function updateNavbarForLoggedInUser(user) {
    const loginBtn = document.getElementById('navLoginBtn');
    const registerBtn = document.getElementById('navRegisterBtn');
    const userDropdown = document.getElementById('navUserDropdown');

    if (loginBtn) loginBtn.classList.add('d-none');
    if (registerBtn) registerBtn.classList.add('d-none');
    if (userDropdown) {
        userDropdown.classList.remove('d-none');
        const nameEl = document.getElementById('navUserName');
        if (nameEl) nameEl.textContent = user?.name || 'Account';
    }
}

// ============================================================
// FOOD MENU: Fetch & Render Dynamically from Database
// ============================================================
async function loadFoodMenu() {
    const grid = document.getElementById('foodGrid');
    if (!grid) return;

    grid.innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-danger" role="status"></div>
            <p class="mt-3 text-muted">Loading menu...</p>
        </div>`;

    const result = await apiFetch(`${API_BASE}/admin/food_api.php`);

    if (result.ok && result.data.data?.length > 0) {
        grid.innerHTML = result.data.data.map(food => `
            <div class="col-lg-4 col-md-6 food-item" 
                 data-category="${food.category}" 
                 data-price="${food.price}" 
                 data-name="${food.title}">
                <div class="card h-100 border-0 shadow-sm food-card">
                    <img src="${food.image_name 
                        ? `http://localhost/food-ordering/backend/uploads/foods/${food.image_name}` 
                        : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800&q=80'}" 
                         class="card-img-top food-img" 
                         alt="${food.title}"
                         onerror="this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800&q=80'">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title fw-bold mb-0">${food.title}</h5>
                            <span class="badge bg-warning text-dark"><i class="fas fa-star text-danger"></i> 4.5</span>
                        </div>
                        <p class="card-text text-muted flex-grow-1">${food.description || 'Freshly prepared just for you.'}</p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="fs-4 fw-bold text-danger">$${parseFloat(food.price).toFixed(2)}</span>
                            <button class="btn btn-danger rounded-pill px-4 btn-hover-scale"
                                    onclick="addToCart(${food.food_id}, '${food.title}')">
                                <i class="fas fa-shopping-cart me-2"></i>Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        // Re-initialize filter logic after dynamic render
        initFilterLogic();
    } else {
        // Fallback to static HTML cards if API is unavailable (no XAMPP)
        console.warn('Could not load food items from database. Showing static menu.');
    }
}

// ============================================================
// CART: Add to Cart
// ============================================================
async function addToCart(food_id, food_name) {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user) {
        showToast('Please log in to add items to your cart.', 'error');
        const authModal = new bootstrap.Modal(document.getElementById('authModal'));
        authModal.show();
        return;
    }

    const result = await apiFetch(`${API_BASE}/cart_api.php`, {
        method: 'POST',
        body: JSON.stringify({ action: 'add', food_id, quantity: 1 })
    });

    if (result.ok && result.data.status === 'success') {
        showToast(`"${food_name}" added to cart!`, 'success');
        updateCartBadge();
    } else {
        showToast(result.data.message || 'Failed to add item.', 'error');
    }
}

// ============================================================
// CART: Update Cart Badge Count in Navbar
// ============================================================
async function updateCartBadge() {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (!user) return;

    const result = await apiFetch(`${API_BASE}/cart_api.php`);

    if (result.ok && result.data.data) {
        const count = result.data.data.items.length;
        const badges = document.querySelectorAll('.cart-icon .badge');
        badges.forEach(b => {
            b.textContent = count;
            b.style.display = count > 0 ? '' : 'none';
        });
    }
}

// ============================================================
// Filter: Re-initialize after dynamic render
// ============================================================
function initFilterLogic() {
    const searchInput = document.getElementById('searchInput');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const priceRange = document.getElementById('priceRange');
    const priceValue = document.getElementById('priceValue');
    const foodItems = document.querySelectorAll('.food-item');

    let currentCategory = 'all', currentSearch = '', currentMaxPrice = 25;

    foodItems.forEach(item => {
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    });

    function filterFood() {
        foodItems.forEach(item => {
            const cat = item.dataset.category;
            const price = parseFloat(item.dataset.price);
            const name = item.dataset.name.toLowerCase();
            const match = (currentCategory === 'all' || cat === currentCategory)
                       && name.includes(currentSearch)
                       && price <= currentMaxPrice;
            if (match) {
                item.classList.remove('d-none');
                item.style.opacity = '1';
                item.style.transform = 'scale(1)';
            } else {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                setTimeout(() => { if(!((currentCategory === 'all' || cat === currentCategory) && name.includes(currentSearch) && price <= currentMaxPrice)) item.classList.add('d-none'); }, 300);
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', e => { currentSearch = e.target.value.toLowerCase(); filterFood(); });
    if (priceRange) priceRange.addEventListener('input', e => { currentMaxPrice = parseFloat(e.target.value); if (priceValue) priceValue.textContent = currentMaxPrice; filterFood(); });
    filterBtns.forEach(btn => btn.addEventListener('click', () => {
        filterBtns.forEach(b => { b.classList.remove('btn-danger', 'active'); b.classList.add('btn-outline-danger'); });
        btn.classList.remove('btn-outline-danger');
        btn.classList.add('btn-danger', 'active');
        currentCategory = btn.dataset.filter;
        filterFood();
    }));
}

// ============================================================
// FORM EVENT LISTENERS (Setup on DOMContentLoaded)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Load dynamic food menu (only if XAMPP is available, else static HTML stays)
    loadFoodMenu();

    // Restore login state from sessionStorage
    const savedUser = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (savedUser) updateNavbarForLoggedInUser(savedUser);

    // Update cart badge count
    updateCartBadge();

    // Register Form Submission
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            registerUser({
                full_name: document.getElementById('regName').value,
                email: document.getElementById('regEmail').value,
                phone: document.getElementById('regPhone').value,
                password: document.getElementById('regPassword').value,
                confirm_password: document.getElementById('regConfirmPassword').value
            });
        });
    }

    // Login Form Submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            loginUser({
                email: document.getElementById('loginEmail').value,
                password: document.getElementById('loginPassword').value
            });
        });
    }
});

// CSS for toast slide-in animation
const style = document.createElement('style');
style.textContent = `@keyframes slideInRight { from { opacity:0; transform:translateX(100%); } to { opacity:1; transform:translateX(0); } }`;
document.head.appendChild(style);
