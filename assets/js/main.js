const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

function showSuccess(message) {
    Toast.fire({
        icon: 'success',
        title: message
    });
}

function showError(message) {
    Toast.fire({
        icon: 'error',
        title: message
    });
}

function showInfo(message) {
    Toast.fire({
        icon: 'info',
        title: message
    });
}

function confirmAction(title, text, confirmButtonText = 'Ya') {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#7A6A54',
        cancelButtonColor: '#dc3545',
        confirmButtonText: confirmButtonText,
        cancelButtonText: 'Batal',
        buttonsStyling: true
    });
}

window.ConfirmModal = {
    confirm: function(options) {
        const config = {
            title: options.title || 'Konfirmasi',
            html: options.html || options.text,
            icon: options.icon || 'question',
            confirmButtonColor: '#7A6A54',
            cancelButtonColor: '#dc3545',
            confirmButtonText: options.confirmText || 'Ya',
            cancelButtonText: 'Batal',
            showCancelButton: true,
            buttonsStyling: true,
            ...options
        };
        
        return Swal.fire(config);
    },
    
    confirmSubmit: function(form, message) {
        this.confirm({
            title: 'Konfirmasi',
            text: message || 'Apakah Anda yakin ingin melanjutkan?',
            icon: 'question'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    },
    
    confirmDelete: function(message, callback) {
        this.confirm({
            title: 'Hapus Item?',
            text: message || 'Item yang dihapus tidak dapat dikembalikan',
            icon: 'warning',
            confirmText: 'Ya, Hapus',
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    }
};

function formatRupiah(angka) {
    const number = parseInt(angka);
    return 'Rp ' + number.toLocaleString('id-ID');
}

function previewImage(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('border-red-500');
        } else {
            input.classList.remove('border-red-500');
        }
    });
    
    return isValid;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showLoading() {
    Swal.fire({
        title: 'Mohon tunggu...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}
/**
 * Update cart badge
 * @param {number} count 
 */
function updateCartBadge(count) {
    const cartBadges = document.querySelectorAll('.cart-badge');
    
    cartBadges.forEach(badge => {
        badge.textContent = count;
        
        if (count > 0) {
            badge.style.display = 'flex';
            
            badge.classList.add('cart-badge-update');
            setTimeout(() => {
                badge.classList.remove('cart-badge-update');
            }, 300);
        } else {
            badge.style.display = 'none';
        }
    });
}

/**
 * Fetch cart count r
 */
function fetchCartCount() {
    fetch(window.BASE_URL + 'api/cart-count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.count);
            }
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
        });
}

/**
 * Initialize cart counter 
 */
function initCartCounter() {
    const cartBadge = document.querySelector('.cart-badge');
    if (cartBadge) {
        fetchCartCount();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-auto-hide');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    });
    
    initCartCounter();
});

const scrollButton = document.createElement('button');
scrollButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
scrollButton.className = 'fixed bottom-8 right-8 bg-primary text-white w-12 h-12 rounded-full shadow-lg hidden hover:bg-accent transition z-50';
scrollButton.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
document.body.appendChild(scrollButton);

window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
        scrollButton.classList.remove('hidden');
    } else {
        scrollButton.classList.add('hidden');
    }
});