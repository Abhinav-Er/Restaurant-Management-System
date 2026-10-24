/* ===========================================================
   Shared JavaScript - load on every customer page, after the footer:
     <script src="js/main.js"></script>

   Contains:
   1. Toast messages
   2. Cart helpers (stored in the browser for now; the real cart
      moves to PHP sessions / MySQL in the backend stage)
   3. Navbar behaviour (mobile menu, notification dropdown)
   4. Form validation helpers
   =========================================================== */

/* ---------- 1. Toast ---------- */
function showToast(message) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(showToast._timer);
  showToast._timer = setTimeout(() => toast.classList.remove('show'), 2200);
}

/* ---------- 2. Cart ---------- */
const Cart = {
  KEY: 'pawan_cart',

  get() {
    try {
      return JSON.parse(localStorage.getItem(this.KEY)) || [];
    } catch (e) {
      return [];
    }
  },

  save(items) {
    try {
      localStorage.setItem(this.KEY, JSON.stringify(items));
    } catch (e) {
      /* storage unavailable: cart will not persist between pages */
    }
    this.updateBadge();
  },

  // item = { id, name, price }
  add(item) {
    const items = this.get();
    const existing = items.find(i => i.id === item.id);
    if (existing) {
      existing.qty += 1;
    } else {
      items.push({ id: item.id, name: item.name, price: Number(item.price), qty: 1 });
    }
    this.save(items);
    showToast(item.name + ' added to cart');
  },

  setQty(id, qty) {
    let items = this.get();
    if (qty <= 0) {
      items = items.filter(i => i.id !== id);
    } else {
      const found = items.find(i => i.id === id);
      if (found) found.qty = qty;
    }
    this.save(items);
  },

  remove(id) {
    this.save(this.get().filter(i => i.id !== id));
  },

  clear() {
    this.save([]);
  },

  count() {
    return this.get().reduce((sum, i) => sum + i.qty, 0);
  },

  total() {
    return this.get().reduce((sum, i) => sum + i.price * i.qty, 0);
  },

  updateBadge() {
    const badge = document.getElementById('cartCount');
    if (!badge) return;
    const n = this.count();
    badge.textContent = n;
    badge.hidden = n === 0;
  }
};

function formatPrice(amount) {
  return '\u20B9' + Number(amount).toLocaleString('en-IN');
}

/* ---------- 3. Navbar behaviour ---------- */
document.addEventListener('DOMContentLoaded', () => {
  Cart.updateBadge();

  // Mobile menu
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      const open = navLinks.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', open);
    });
  }

  // Notification dropdown
  const notifBtn = document.getElementById('notifBtn');
  const notifMenu = document.getElementById('notifMenu');
  if (notifBtn && notifMenu) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = notifMenu.classList.toggle('open');
      notifBtn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', (e) => {
      if (!notifMenu.contains(e.target)) {
        notifMenu.classList.remove('open');
        notifBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
});

/* ---------- 4. Form validation helpers ---------- */
const Validate = {
  email(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
  },

  // Indian mobile number: 10 digits, starting 6-9
  phone(value) {
    return /^[6-9]\d{9}$/.test(value.trim());
  },

  password(value) {
    return value.length >= 8;
  },

  required(value) {
    return value.trim().length > 0;
  },

  // Marks a .field wrapper valid/invalid and shows its .error-text
  setState(inputEl, isValid, message) {
    const field = inputEl.closest('.field');
    if (!field) return isValid;
    field.classList.toggle('invalid', !isValid);
    const err = field.querySelector('.error-text');
    if (err && message) err.textContent = message;
    return isValid;
  }
};