
# Login Module

This project is a small PHP/MySQL authentication module with three connected forms:

- registration
- login
- password reset

The user can move between the pages through the UI links, and each form has both client-side and server-side validation.

## Project Structure

- `index.php` - protected landing page shown after login
- `auth/login.php` - login form
- `auth/register.php` - registration form
- `auth/reset-password.php` - password reset form
- `auth/process-login.php` - login handler
- `auth/process-register.php` - registration handler
- `auth/process-reset.php` - password reset handler
- `auth/verify-email.php` - email verification handler
- `assets/css/style.css` - shared styling for all forms
- `assets/js/index.js` - shared form validation and UI behavior
- `database/schema.sql` - database schema
- `includes/mail.php` - SMTP helper for outgoing emails
- `includes/auth.php` - session, CSRF, and auth helpers
- `includes/db.php` - database connection
- `includes/config.example.php` - configuration template
- `includes/config.php` - local configuration file

## Database Setup

Use `database/schema.sql` to create the MySQL schema.

The schema includes the tables for users, password resets, and email verification tokens.

Import steps:

1. Open MySQL Workbench.
2. Connect to your server.
3. Open `database/schema.sql`.
4. Run the script.

## Configuration

Copy `includes/config.example.php` to `includes/config.php` and set your local database and application values there.

The real `config.php` file is ignored by Git and should remain local.

## Authentication Flow

The three forms are linked together:

- `register.php` lets a new user create an account.
- `login.php` lets an existing user sign in.
- `reset-password.php` lets a user request a reset email and set a new password.
- New accounts must verify their email address before they can log in.

The root page `index.php` is protected and redirects unauthenticated users to the registration page.

## Form Validations

### Register

Client-side and server-side checks are applied to:

- username is required
- username must be at least 6 characters
- username can contain only letters, numbers, and underscores
- email is required
- email must have a valid format
- password is required
- password must be at least 8 characters
- password must include at least one uppercase letter, one lowercase letter, one number, and one symbol
- confirm password is required
- confirm password must match password

### Login

Client-side and server-side checks are applied to:

- username or email is required
- password is required
- account status is checked on the server
- unverified accounts are rejected until the verification link is used
- invalid credentials are rejected
- repeated failed attempts can temporarily lock the account

The login form also supports persistent login with a `Remember me` checkbox:

- stores a secure selector/validator token pair in `remember_me_tokens`
- keeps only a hashed validator in the database
- restores the session automatically when the cookie is still valid
- clears the token on logout and when the cookie is not used

### Email Verification

Client-side and server-side checks are applied to:

- a verification token is generated during registration
- the verification link must be valid, unused, and not expired
- verified accounts are activated on the server before login

Registration keeps new accounts inactive and sends a verification email. The account is activated only after the verification link is opened, so unverified users cannot log in.

### Password Reset Request

Client-side and server-side checks are applied to:

- email is required
- email must have a valid format
- if the email exists, a reset token is generated and sent
- the response remains neutral so the page does not reveal whether the account exists

### Password Reset

Client-side and server-side checks are applied to:

- reset token must be present
- new password is required
- new password must satisfy the strong-password policy
- confirm password is required
- confirm password must match the new password
- token must be valid, unused, and not expired

## Database Checks

The server also checks data in the database before writing anything:

- duplicate username or email during registration
- stored password hash during login
- failed login attempts and lockout time
- account activation state
- password reset token hash
- password reset token expiration and usage

## Security Notes

- CSRF tokens are used on all forms.
- Passwords are hashed with `password_hash()` and verified with `password_verify()`.
- Login regenerates the session ID after a successful authentication.
- New registrations stay inactive until the email verification link is opened.
- Password reset links are token-based and time-limited.
- The reset flow keeps the response neutral when requesting a reset email.

## Testing Email Flows (MailHog)

For local development we use MailHog to safely capture outgoing emails for both verification and password reset.

1. Start MailHog (Docker):

    ```bash
    docker run --rm -p 1025:1025 -p 8025:8025 mailhog/mailhog
    ```

2. Start the PHP built-in server from the project root:

    ```bash
    cd '/media/gianluca/WD 500 GB1/Login'
    php -S localhost:8000
    ```

3. Register or use an existing account via the web UI:

   - <http://localhost:8000/auth/register.php>

4. Complete registration and open the verification email in MailHog:

   - <http://localhost:8025>

5. Click the verification link to activate the account.

6. Request a password reset:

   - <http://localhost:8000/auth/reset-password.php> - enter the account email and submit.

7. Open MailHog UI in your browser to find the reset email:

   - <http://localhost:8025>

8. Click the reset link in the MailHog message to open the reset form. Set a new password and then log in.

## Auth Smoke Test

A small HTTP smoke test is available to check the main auth pages and redirect flows without needing test data.

```bash
bash tests/auth-smoke.sh
```

It verifies the login, register, and reset pages, then checks a few key redirects for validation and verification handling.

## Notes

- In production, set `APP_ENV='prod'` in `includes/config.php` and configure a real SMTP provider.
- Make sure the SMTP values in `includes/config.php` are valid for your environment.
