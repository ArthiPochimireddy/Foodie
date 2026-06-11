document.addEventListener("DOMContentLoaded", function() {
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                // Adjust for fixed navbar height
                const navHeight = navbar.offsetHeight;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - navHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Simple Intersection Observer for section titles animation
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px"
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.section-title').forEach(section => {
        observer.observe(section);
    });

    // --- Filter Logic ---
    const searchInput = document.getElementById('searchInput');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const priceRange = document.getElementById('priceRange');
    const priceValue = document.getElementById('priceValue');
    const foodItems = document.querySelectorAll('.food-item');

    let currentCategory = 'all';
    let currentSearch = '';
    let currentMaxPrice = 25;

    function filterFood() {
        foodItems.forEach(item => {
            const category = item.getAttribute('data-category');
            const price = parseFloat(item.getAttribute('data-price'));
            const name = item.getAttribute('data-name').toLowerCase();

            const matchCategory = currentCategory === 'all' || category === currentCategory;
            const matchSearch = name.includes(currentSearch);
            const matchPrice = price <= currentMaxPrice;

            if (matchCategory && matchSearch && matchPrice) {
                item.classList.remove('d-none');
                // Trigger reflow for animation
                void item.offsetWidth;
                item.style.opacity = '1';
                item.style.transform = 'scale(1)';
            } else {
                item.style.opacity = '0';
                item.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    // double check condition to avoid race conditions
                    if (!(matchCategory && matchSearch && matchPrice)) {
                        item.classList.add('d-none');
                    }
                }, 300);
            }
        });
    }

    // Category Filter
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => {
                b.classList.remove('btn-danger', 'active');
                b.classList.add('btn-outline-danger');
            });
            btn.classList.remove('btn-outline-danger');
            btn.classList.add('btn-danger', 'active');
            
            currentCategory = btn.getAttribute('data-filter');
            filterFood();
        });
    });

    // Search Filter
    searchInput.addEventListener('input', (e) => {
        currentSearch = e.target.value.toLowerCase();
        filterFood();
    });

    // Price Filter
    priceRange.addEventListener('input', (e) => {
        currentMaxPrice = parseFloat(e.target.value);
        priceValue.textContent = currentMaxPrice;
        filterFood();
    });

    // Initialize animation states
    foodItems.forEach(item => {
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    });
});
