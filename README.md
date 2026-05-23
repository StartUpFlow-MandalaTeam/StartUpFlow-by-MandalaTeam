
# Login module

Use `database/schema.sql` to create the MySQL schema in Workbench.

Import steps:

1. Open MySQL Workbench.
2. Connect to your server.
3. Open `database/schema.sql`.
4. Run the script.

## Testing password reset (MailHog)

For local development we use MailHog to safely capture outgoing emails.

1. Start MailHog (Docker):

```bash
docker run --rm -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

1. Start the PHP built-in server from the project root:

```bash
cd '/media/gianluca/WD 500 GB1/Login'
php -S localhost:8000
```

1. Register or use an existing account via the web UI:

- <http://localhost:8000/auth/register.php>

1. Request a password reset:

- <http://localhost:8000/auth/reset-password.php> — enter the account email and submit.

1. Open MailHog UI in your browser to find the reset email:

- <http://localhost:8025>

1. Click the reset link in MailHog message to open the reset form (it will be served from your running PHP server on port 8000). Set a new password and then login.

## Notes

- The project uses a file-based fallback for emails if no SMTP transport is configured; the fallback writes reset links to `storage/reset_emails.log`.
- In production set `APP_ENV='prod'` in `includes/config.php` and configure a real SMTP provider. Do not expose `debug_link` in production.
