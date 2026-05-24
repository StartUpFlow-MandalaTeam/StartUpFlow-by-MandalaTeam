function setResultMessage(message, type = 'error') {
    const resultEl = document.getElementById('result');
    if (!resultEl) return;

    resultEl.textContent = message;
    resultEl.classList.remove('is-error', 'is-success');
    resultEl.classList.add('is-visible', type === 'success' ? 'is-success' : 'is-error');
    resultEl.setAttribute('aria-hidden', 'false');
}

function clearResultMessage() {
    const resultEl = document.getElementById('result');
    if (!resultEl) return;

    resultEl.textContent = '';
    resultEl.classList.remove('is-visible', 'is-error', 'is-success');
    resultEl.setAttribute('aria-hidden', 'true');
}

function validation(){
    // registration validation
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const email = form.Email ? form.Email.value.trim() : '';
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const usernamePattern = /^[A-Za-z0-9_]+$/;
    const emailPattern = /\S+@\S+\.\S+/;
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    if (!username) {
        setResultMessage('Please enter your username.');
        return false;
    }
    if (username.length < 6) {
        setResultMessage('Username must be at least 6 characters.');
        return false;
    }
    if (!usernamePattern.test(username)) {
        setResultMessage('Username can contain only letters, numbers, and underscores.');
        return false;
    }
    if (!email) {
        setResultMessage('Please enter your email.');
        return false;
    }
    if (!emailPattern.test(email)) {
        setResultMessage('Please enter a valid email address.');
        return false;
    }
    if (!password) {
        setResultMessage('Please enter your password.');
        return false;
    }
    if (!passwordPattern.test(password)) {
        setResultMessage('Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.');
        return false;
    }
    if (!cpassword) {
        setResultMessage('Please confirm your password.');
        return false;
    }
    if (cpassword !== password) {
        setResultMessage("Password doesn't match");
        return false;
    }
    clearResultMessage();
    return true;
}

function resetValidation(){
    const form = document.forms['Formfill'];
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    if (!password) {
        setResultMessage('Please enter a new password.');
        return false;
    }
    if (!passwordPattern.test(password)) {
        setResultMessage('Password must be at least 8 characters and include uppercase, lowercase, a number, and a symbol.');
        return false;
    }
    if (!cpassword) {
        setResultMessage('Please confirm your password.');
        return false;
    }
    if (cpassword !== password) {
        setResultMessage("Password doesn't match");
        return false;
    }
    clearResultMessage();
    return true;
}

function requestResetValidation(){
    const form = document.forms['Formfill'];
    const email = form.email ? form.email.value.trim() : '';

    if (!email) {
        setResultMessage('Please enter your email.');
        return false;
    }
    const re = /\S+@\S+\.\S+/;
    if (!re.test(email)) {
        setResultMessage('Please enter a valid email address.');
        return false;
    }
    clearResultMessage();
    return true;
}

function loginValidation(){
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const password = form.Password ? form.Password.value : '';

    if (!username) {
        setResultMessage('Please enter your username.');
        return false;
    }
    if (!password) {
        setResultMessage('Please enter your password.');
        return false;
    }
    clearResultMessage();
    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    const resultEl = document.getElementById('result');

    if (resultEl && resultEl.textContent.trim() !== '') {
        resultEl.classList.add('is-visible');
        resultEl.setAttribute('aria-hidden', 'false');
    }
});