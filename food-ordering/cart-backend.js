/**
 * cart-backend.js
 * Handles fetching the cart from the PHP backend API and
 * dynamically rendering/updating the cart page.
 * Falls back to static cart.js if user is not logged in or XAMPP is unavailable.
 */

const API_BASE = 'http://localhost/food-ordering/backend/api';

// Load cart data from backend on page load
document.addEventListener('DOMContentLoaded', () => {
    const user = JSON.parse(sessionStorage.getItem('user') || 'null');
    if (user) {
        loadCartFromBackend();
    }
    // If not logged in, static cart.js handles the UI
});

async function loadCartFromBackend() {
    const cartContainer = document.querySelector('.col-lg-8 .bg-white');
    if (!cartContainer) return;

    // Show spinner
    const itemsSection = cartContainer.querySelector('.border-bottom');
    if (itemsSection) {
        const spinnerRow = document.createElement('div');
        spinnerRow.id = 'cart-loading';
        spinnerRow.className = 'text-center py-3';
        spinnerRow.innerHTML = '<div class="spinner-border text-danger spinner-border-sm"></div><span class="ms-2 text-muted">Syncing your cart...</span>';
        itemsSection.after(spinnerRow);
    }

    try {
        const response = await fetch(`${API_BASE}/cart_api.php`, {
            credentials: 'include'
        });
        const result = await response.json();

        // Remove spinner
        const spinner = document.getElementById('cart-loading');
        if (spinner) spinner.remove();

        if (result.status === 'success' && result.data.items.length > 0) {
            renderBackendCart(result.data);
        } else if (result.status === 'success' && result.data.items.length === 0) {
            showEmptyCartState(cartContainer);
        }
        // If API fails (XAMPP off), static HTML cart remains visible
    } catch (e) {
        const spinner = document.getElementById('cart-loading');
        if (spinner) spinner.remove();
        console.warn('Cart backend unavailable. Using static cart.');
    }
}

function renderBackendCart(cartData) {
    const existingItems = document.querySelectorAll('.cart-item');
    existingItems.forEach(item => item.remove());

    const itemsWrapper = document.querySelector('.col-lg-8 .bg-white');
    const border = itemsWrapper.querySelector('.border-bottom');

    cartData.items.forEach((item, index) => {
        const isLast = index === cartData.items.length - 1;
        const row = document.createElement('div');
        row.className = `row align-items-center cart-item${isLast ? '' : ' border-bottom pb-3 mb-3'}`;
        row.dataset.cartId = item.cart_id;
        row.dataset.price = item.price;

        const imageSrc = item.image_name
            ? `http://localhost/food-ordering/backend/uploads/foods/${item.image_name}`
            : 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=200&q=80';

        row.innerHTML = `
            <div class="col-3 col-md-2">
                <img src="${imageSrc}" class="img-fluid rounded-3 shadow-sm" alt="${item.title}"
                     onerror="this.src='https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=200&q=80'">
            </div>
            <div class="col-5 col-md-4">
                <h6 class="fw-bold mb-1">${item.title}</h6>
                <p class="text-muted small mb-0">$${parseFloat(item.price).toFixed(2)} each</p>
            </div>
            <div class="col-4 col-md-3 d-flex align-items-center justify-content-center">
                <div class="input-group input-group-sm w-75 border rounded">
                    <button class="btn btn-light border-0 px-2 qty-btn minus" type="button" onclick="updateCartQty(${item.cart_id}, ${item.quantity}, -1, this)"><i class="fas fa-minus text-muted"></i></button>
                    <input type="text" class="form-control text-center border-0 px-0 qty-input fw-semibold bg-white" value="${item.quantity}" readonly data-price="${item.price}">
                    <button class="btn btn-light border-0 px-2 qty-btn plus" type="button" onclick="updateCartQty(${item.cart_id}, ${item.quantity}, 1, this)"><i class="fas fa-plus text-muted"></i></button>
                </div>
            </div>
            <div class="col-12 col-md-3 mt-3 mt-md-0 d-flex justify-content-between align-items-center justify-content-md-end gap-3">
                <span class="fw-bold text-danger item-total">$${parseFloat(item.item_total).toFixed(2)}</span>
                <button class="btn btn-sm btn-outline-danger rounded-circle remove-btn shadow-sm" onclick="removeCartItem(${item.cart_id}, this)"><i class="fas fa-trash"></i></button>
            </div>
        `;
        border.after(row);
        border.parentNode.insertBefore(row, border.nextSibling);
    });

    // Update summary
    updateSummaryUI(cartData.summary);
    // Update item count
    const countEl = document.getElementById('cartCount');
    if (countEl) countEl.textContent = cartData.items.length;
}

function updateSummaryUI(summary) {
    const ids = { cartSubtotal: summary.subtotal, cartDelivery: summary.delivery_fee, cartTax: summary.tax, cartTotal: summary.total };
    Object.entries(ids).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = `$${val}`;
    });
}

async function updateCartQty(cartId, currentQty, delta, btn) {
    const newQty = currentQty + delta;
    if (newQty < 1) return;

    btn.disabled = true;
    try {
        const response = await fetch(`${API_BASE}/cart_api.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_qty', cart_id: cartId, quantity: newQty })
        });
        const result = await response.json();
        if (result.status === 'success') {
            await loadCartFromBackend();
        } else {
            typeof showToast === 'function' && showToast(result.message, 'error');
        }
    } catch (e) {
        console.error(e);
    }
    btn.disabled = false;
}

async function removeCartItem(cartId, btn) {
    const row = btn.closest('.cart-item');
    row.style.transition = 'all 0.35s ease';
    row.style.opacity = '0';
    row.style.transform = 'translateX(40px)';

    try {
        const response = await fetch(`${API_BASE}/cart_api.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove', cart_id: cartId })
        });
        const result = await response.json();
        setTimeout(async () => {
            row.remove();
            if (result.status === 'success') {
                await loadCartFromBackend();
                typeof showToast === 'function' && showToast('Item removed from cart.', 'success');
            }
        }, 350);
    } catch (e) {
        row.style.opacity = '1';
        row.style.transform = 'none';
    }
}

function showEmptyCartState(container) {
    const items = container.querySelectorAll('.cart-item');
    items.forEach(i => i.remove());
    container.innerHTML += `
        <div class="text-center py-5">
            <i class="fas fa-shopping-basket fa-4x text-muted opacity-50 mb-4"></i>
            <h4 class="fw-bold mb-3">Your cart is empty</h4>
            <p class="text-muted mb-4">Looks like you haven't added anything yet.</p>
            <a href="index.html#menu" class="btn btn-danger rounded-pill px-5 py-2 btn-hover-scale">Start Shopping</a>
        </div>
    `;
    updateSummaryUI({ subtotal: '0.00', delivery_fee: '0.00', tax: '0.00', total: '0.00' });
}
