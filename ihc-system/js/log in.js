document.addEventListener('DOMContentLoaded', () => {

  /* ==========================================
     ORIGIN GUARD (safety net for the same guard in log in.html)
     Login must happen on the XAMPP origin: the lookup API, the session
     (localStorage) and every dashboard redirect assume http://localhost.
  ========================================== */
  const API_BASE = 'http://localhost/ihc-system';
  if (window.location.origin !== 'http://localhost') {
    window.location.replace(API_BASE + '/log%20in.html' + window.location.search + window.location.hash);
    return;
  }

  /* ==========================================
     AUTHENTICATION — php/api_auth.php verifies ALL roles against MySQL:
       staff  -> ihc.officers (bcrypt password_hash, migration 004)
       client -> ihc.client_accounts (bcrypt, migration 003)
     The session mechanism is unchanged: everything below shares the
     IHC_USER localStorage key on this origin.
  ========================================== */

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
      // 1) Verify the credentials server-side (single endpoint for admin,
      //    clerk and client). Absolute URL + explicit failure handling: a
      //    network/parse problem must NEVER be reported as bad credentials.
      let authResult = null; // { ok:true, user } | { ok:false, message } | { error:'unreachable' }
      try {
        const res = await fetch(API_BASE + '/php/api_auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (data && data.success && data.user) {
          authResult = { ok: true, user: data.user };
        } else {
          authResult = { ok: false, message: (data && data.message) || 'Invalid email or password. Please try again.' };
        }
      } catch (authErr) {
        console.error('Login request failed:', authErr);
        authResult = { error: 'unreachable' };
      }

      if (authResult.ok) {
        const u = authResult.user;
        const session = {
          role: u.role,
          name: u.name,
          email: u.email || email,
          avatar: u.avatar || email.charAt(0).toUpperCase()
        };
        if (u.officerId != null) session.officerId = u.officerId;
        if (u.contractId != null) session.contractId = Number(u.contractId);
        localStorage.setItem('IHC_USER', JSON.stringify(session));
        errorMessage.classList.add('hidden');
        redirectByRole(u.role);
        return;
      }

      // 2) The server did not grant access (or was unreachable): try the
      //    localStorage demo seeds so the demo client credentials printed on
      //    this page keep working offline / in demo mode.
      const demoClient = window.portalStore && portalStore.findClientByLogin(email, password);
      if (demoClient) {
        const initials = demoClient.name.split(' ').map(w => w.charAt(0)).join('').slice(0, 2).toUpperCase();
        localStorage.setItem('IHC_USER', JSON.stringify({
          role: portalStore.CLIENT_ROLE,
          name: demoClient.name,
          email: demoClient.email,
          contractId: Number(demoClient.contractId),
          avatar: initials
        }));
        errorMessage.classList.add('hidden');
        redirectByRole(portalStore.CLIENT_ROLE);
        return;
      }

      if (authResult.error === 'unreachable') {
        showError('Could not reach the login server at ' + API_BASE + '. Make sure XAMPP Apache is running, then try again.');
        return;
      }
      showError(authResult.message || 'Invalid email or password. Please try again.');
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
