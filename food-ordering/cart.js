document.addEventListener("DOMContentLoaded", function() {
    const qtyBtns = document.querySelectorAll('.qty-btn');
    const removeBtns = document.querySelectorAll('.remove-btn');
    
    function updateCartTotals() {
        let subtotal = 0;
        let itemCount = 0;
        const cartItems = document.querySelectorAll('.cart-item');
        
        cartItems.forEach(item => {
            const qtyInput = item.querySelector('.qty-input');
            const price = parseFloat(qtyInput.getAttribute('data-price'));
            const qty = parseInt(qtyInput.value);
            
            itemCount += qty;
            const itemTotal = price * qty;
            item.querySelector('.item-total').textContent = '$' + itemTotal.toFixed(2);
            subtotal += itemTotal;
        });

        const deliveryFee = subtotal > 0 ? 5.00 : 0;
        const tax = subtotal * 0.10;
        const total = subtotal + deliveryFee + tax;

        // Update DOM
        if(document.getElementById('cartSubtotal')) {
            document.getElementById('cartSubtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('cartDelivery').textContent = '$' + deliveryFee.toFixed(2);
            document.getElementById('cartTax').textContent = '$' + tax.toFixed(2);
            document.getElementById('cartTotal').textContent = '$' + total.toFixed(2);
            document.getElementById('cartCount').textContent = cartItems.length;
            
            // update navbar badge
            const badges = document.querySelectorAll('.cart-icon .badge');
            badges.forEach(b => b.textContent = itemCount);
        }
    }

    qtyBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.qty-input');
            let qty = parseInt(input.value);
            
            if (this.classList.contains('plus')) {
                qty++;
            } else if (this.classList.contains('minus')) {
                if (qty > 1) qty--;
            }
            
            input.value = qty;
            updateCartTotals();
        });
    });

    removeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const item = this.closest('.cart-item');
            item.style.transition = 'all 0.4s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(50px)';
            
            setTimeout(() => {
                // To keep the layout clean, if there's a border-bottom on the previous item, it handles itself
                item.remove();
                
                // If cart is empty
                const remainingItems = document.querySelectorAll('.cart-item');
                if (remainingItems.length === 0) {
                    const container = document.querySelector('.col-lg-8 .bg-white');
                    container.innerHTML = `
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-shopping-basket fa-4x text-muted opacity-50"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Your cart is empty</h4>
                            <p class="text-muted mb-4">Looks like you haven't added anything to your cart yet.</p>
                            <a href="index.html#menu" class="btn btn-danger rounded-pill px-5 py-2 btn-hover-scale">Start Shopping</a>
                        </div>
                    `;
                } else {
                    // Remove border-bottom from the last item
                    remainingItems[remainingItems.length - 1].classList.remove('border-bottom', 'pb-3', 'mb-3');
                }
                
                updateCartTotals();
            }, 400);
        });
    });
});
