// assets/js/index.js
// JavaScript for form validation and result message handling
// Function to display result messages
function setResultMessage(message, type = 'error') {
    const resultEl = document.getElementById('result');
    if (!resultEl) return;

    resultEl.textContent = message;
    resultEl.classList.remove('is-error', 'is-success');
    resultEl.classList.add('is-visible', type === 'success' ? 'is-success' : 'is-error');
    resultEl.setAttribute('aria-hidden', 'false');
}
// Function to clear result messages
function clearResultMessage() {
    const resultEl = document.getElementById('result');
    if (!resultEl) return;

    resultEl.textContent = '';
    resultEl.classList.remove('is-visible', 'is-error', 'is-success');
    resultEl.setAttribute('aria-hidden', 'true');
}
// Validation function for registration form
function validation(){
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const email = form.Email ? form.Email.value.trim() : '';
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const usernamePattern = /^[A-Za-z0-9_]+$/;
    const emailPattern = /\S+@\S+\.\S+/;
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    if (!username) {// Check if username is empty
        setResultMessage('Please enter your username.');
        return false;
    }
    if (username.length < 6) {// Check if username is at least 6 characters
        setResultMessage('Username must be at least 6 characters.');
        return false;
    }
    if (!usernamePattern.test(username)) {// Check if username contains only allowed characters
        setResultMessage('Username can contain only letters, numbers, and underscores.');
        return false;
    }
    if (!email) {// Check if email is empty
        setResultMessage('Please enter your email.');
        return false;
    }
    if (!emailPattern.test(email)) {// Check if email is in valid format
        setResultMessage('Please enter a valid email address.');
        return false;
    }
    if (!password) {// Check if password is empty
        setResultMessage('Please enter your password.');
        return false;
    }
    if (!passwordPattern.test(password)) {// Check if password meets complexity requirements
        setResultMessage('Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.');
        return false;
    }
    if (!cpassword) {// Check if confirm password is empty
        setResultMessage('Please confirm your password.');
        return false;
    }
    if (cpassword !== password) {// Check if password and confirm password match
        setResultMessage("Password doesn't match");
        return false;
    }
    clearResultMessage();
    return true;
}
// Validation function for password reset form
function resetValidation(){
    const form = document.forms['Formfill'];
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    if (!password) {// Check if password is empty
        setResultMessage('Please enter a new password.');
        return false;
    }
    if (!passwordPattern.test(password)) {// Check if password meets complexity requirements
        setResultMessage('Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.');
        return false;
    }
    if (!cpassword) {// Check if confirm password is empty
        setResultMessage('Please confirm your password.');
        return false;
    }
    if (cpassword !== password) {// Check if password and confirm password match
        setResultMessage("Password doesn't match");
        return false;
    }
    clearResultMessage();
    return true;
}
// Validation function for password reset request form
function requestResetValidation(){
    const form = document.forms['Formfill'];
    const email = form.email ? form.email.value.trim() : '';

    if (!email) {// Check if email is empty
        setResultMessage('Please enter your email.');
        return false;
    }
    const re = /\S+@\S+\.\S+/;
    if (!re.test(email)) {// Check if email is in valid format
        setResultMessage('Please enter a valid email address.');
        return false;
    }
    clearResultMessage();
    return true;
}
// Validation function for login form
function loginValidation(){
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const password = form.Password ? form.Password.value : '';

    if (!username) {// Check if username is empty
        setResultMessage('Please enter your username.');
        return false;
    }
    if (!password) {// Check if password is empty
        setResultMessage('Please enter your password.');
        return false;
    }
    clearResultMessage();
    return true;
}
// Event listener to show result message if it exists on page load
document.addEventListener('DOMContentLoaded', () => {
    const resultEl = document.getElementById('result');

    if (resultEl && resultEl.textContent.trim() !== '') {
        resultEl.classList.add('is-visible');
        resultEl.setAttribute('aria-hidden', 'false');
    }

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const passwordInput = document.getElementById(toggle.dataset.passwordToggle);
            if (!passwordInput) return;

            const isVisible = passwordInput.type === 'text';
            passwordInput.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(!isVisible));
            toggle.setAttribute('aria-label', isVisible ? 'Mostra password' : 'Nascondi password');
            const icon = toggle.querySelector('i');
            if (icon) icon.className = isVisible ? 'bx bx-show' : 'bx bx-hide';
        });
    });
});