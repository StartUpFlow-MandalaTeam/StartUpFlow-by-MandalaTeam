function validation(){
    // registration validation
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const email = form.Email ? form.Email.value.trim() : '';
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const resultEl = document.getElementById('result');

    if (!username) {
        resultEl.innerHTML = 'Enter Username*';
        return false;
    }
    if (username.length < 6) {
        resultEl.innerHTML = 'At least six letters*';
        return false;
    }
    if (!email) {
        resultEl.innerHTML = 'Enter your Email*';
        return false;
    }
    if (!password) {
        resultEl.innerHTML = 'Enter your Password*';
        return false;
    }
    if (password.length < 6) {
        resultEl.innerHTML = 'Password must be at least 6 characters';
        return false;
    }
    if (!cpassword) {
        resultEl.innerHTML = 'Enter Confirm Password';
        return false;
    }
    if (cpassword !== password) {
        resultEl.innerHTML = "Password doesn't match";
        return false;
    }
    return true;
}

function resetValidation(){
    const form = document.forms['Formfill'];
    const password = form.Password ? form.Password.value : '';
    const cpassword = form.CPassword ? form.CPassword.value : '';
    const resultEl = document.getElementById('result');

    if (!password) {
        resultEl.innerHTML = 'Enter new Password*';
        return false;
    }
    if (password.length < 6) {
        resultEl.innerHTML = 'Password must be at least 6 characters';
        return false;
    }
    if (!cpassword) {
        resultEl.innerHTML = 'Enter Confirm Password';
        return false;
    }
    if (cpassword !== password) {
        resultEl.innerHTML = "Password doesn't match";
        return false;
    }
    return true;
}

function requestResetValidation(){
    const form = document.forms['Formfill'];
    const email = form.email ? form.email.value.trim() : '';
    const resultEl = document.getElementById('result');

    if (!email) {
        if (resultEl) resultEl.innerHTML = 'Enter your email*';
        return false;
    }
    const re = /\S+@\S+\.\S+/;
    if (!re.test(email)) {
        if (resultEl) resultEl.innerHTML = 'Enter a valid email address';
        return false;
    }
    return true;
}

function loginValidation(){
    const form = document.forms['Formfill'];
    const username = form.Username ? form.Username.value.trim() : '';
    const password = form.Password ? form.Password.value : '';
    const resultEl = document.getElementById('result');

    if (!username) {
        resultEl.innerHTML = 'Enter Username*';
        return false;
    }
    if (!password) {
        resultEl.innerHTML = 'Enter your Password*';
        return false;
    }
    // login will be handled by backend later
    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    // find popup element when DOM is ready
    const popup = document.getElementById('popup');
    // close popup when any .popup-ok button is clicked
    document.querySelectorAll('.popup .popup-ok').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (popup) popup.classList.remove('open-slide');
        });
    });
});