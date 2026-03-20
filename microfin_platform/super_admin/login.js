// super_admin/login.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const loader = document.getElementById('loader');

    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            loader.classList.add('active');
        });
    }
});
