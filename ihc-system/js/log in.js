document.addEventListener('DOMContentLoaded', () => {

  /* ==========================================
     MOCK USERS
  ========================================== */
  const MOCK_USERS = [
    { id: 1, name: 'Jeremy Cantalejo', email: 'admin@ihc.com',  password: 'admin123', role: 'admin', avatar: 'JC' },
    { id: 2, name: 'Ana Reyes',        email: 'ana@ihc.com',    password: 'clerk123', role: 'clerk', avatar: 'AR', officerId: 2 },
    { id: 3, name: 'Mark Cruz',        email: 'mark@ihc.com',   password: 'clerk123', role: 'clerk', avatar: 'MC', officerId: 3 },
    { id: 4, name: 'Jessica Lim',      email: 'jessica@ihc.com', password: 'clerk123', role: 'clerk', avatar: 'JL', officerId: 4 }
  ];

  /* ==========================================
     SESSION CHECK — skip login if already authed
  ========================================== */
  const existingUser = localStorage.getItem('IHC_USER');
  if (existingUser) {
    try {
      const user = JSON.parse(existingUser);
      if (user && user.role) {
        redirectByRole(user.role);
        return;
      }
    } catch {}
  }

  /* ==========================================
     DOM ELEMENTS
  ========================================== */
  const loginForm     = document.getElementById('loginForm');
  const emailInput    = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const togglePassword = document.getElementById('togglePassword');
  const errorMessage  = document.getElementById('errorMessage');
  const loginParams   = new URLSearchParams(window.location.search);
  const requestedRedirect = loginParams.get('redirect');
  const emailFromLink = loginParams.get('email');

  if (emailFromLink) emailInput.value = emailFromLink;

  /* ==========================================
     PASSWORD TOGGLE
  ========================================== */
  togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.getAttribute('type') === 'password';
    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
    togglePassword.classList.toggle('fa-eye');
    togglePassword.classList.toggle('fa-eye-slash');
  });

  /* ==========================================
     FORM SUBMISSION
  ========================================== */
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email    = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!email || !password) {
      showError('Please fill in all required fields.');
      return;
    }

    const submitBtn = loginForm.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Signing in\u2026';
    }

    try {
      // 1) Admin / clerk mock users (staff portal)
      const user = MOCK_USERS.find(
        u => u.email.toLowerCase() === email.toLowerCase() && u.password === password
      );

      if (user) {
        const { password: _, ...safeUser } = user;
        localStorage.setItem('IHC_USER', JSON.stringify(safeUser));
        errorMessage.classList.add('hidden');
        redirectByRole(user.role);
        return;
      }

      // 2) Client portal accounts (front-end store, database-backed later)
      const client = window.portalStore && portalStore.findClientByLogin(email, password);

      if (client) {
        const initials = client.name.split(' ').map(w => w.charAt(0)).join('').slice(0, 2).toUpperCase();
        localStorage.setItem('IHC_USER', JSON.stringify({
          role: portalStore.CLIENT_ROLE,
          name: client.name,
          email: client.email,
          contractId: Number(client.contractId),
          avatar: initials
        }));
        errorMessage.classList.add('hidden');
        redirectByRole(portalStore.CLIENT_ROLE);
        return;
      }

      showError('Invalid email or password. Please try again.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In';
      }
    }
  });

  /* ==========================================
     ROLE-BASED REDIRECT
  ========================================== */
  function redirectByRole(role) {
    if (role === 'client' && requestedRedirect === 'client-dashboard') {
      window.location.href = 'html/client-dashboard.html';
      return;
    }

    switch (role) {
      case 'admin':
        window.location.href = 'html/admin-dashboard.html';
        break;
      case 'clerk':
        window.location.href = 'html/clerk-dashboard.html';
        break;
      case 'client':
        window.location.href = 'html/client-dashboard.html';
        break;
      default:
        window.location.href = 'html/admin-dashboard.html';
    }
  }

  /* ==========================================
     ERROR DISPLAY
  ========================================== */
  function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.remove('hidden');
  }
});
