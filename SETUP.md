# Calendar Tag Scheduler - Setup Guide

## Quick Start

### 1. Create a User Account

```bash
php setup-user.php <username> <password>
```

Example:
```bash
php setup-user.php admin mypassword123
```

This will:
- Create an entry in `.htpasswd` with encrypted password
- Generate a user-specific directory in `users/<hash>/`
- Display the user directory hash

### 2. Access Web Interface

Navigate to `login.php` in your web browser:
```
http://localhost/calendar-tags/login.php
```

Or if using PHP built-in server:
```bash
php -S localhost:8000
```
Then visit: `http://localhost:8000/login.php`

### 3. Login and Configure

1. Enter your username and password
2. You'll be redirected to the scheduler page
3. If no config exists, you'll be redirected to the config editor
4. Configure your tags, slots, and priorities
5. Save and view your schedule

## Architecture

### Authentication

- **Login**: `login.php` - Simple login form with session management
- **Logout**: `logout.php` - Destroys session and redirects to login
- **Auth Class**: `auth.php` - Handles authentication, user management, and directory hashing

### User Isolation

Each user gets:
- A unique directory: `users/<8-char-hash>/`
- User-specific configuration: `users/<hash>/config.php`
- User-specific ICS file: `users/<hash>/calendar_tags.ics`

The hash is generated from the username using MD5 (first 8 characters).

### File Structure

```
calendar-tags/
├── .htpasswd              # User credentials (bcrypt hashed)
├── users/                 # User-specific data
│   └── <hash>/           # Each user's directory
│       ├── config.php    # User's configuration
│       └── calendar_tags.ics
├── auth.php              # Authentication helper class
├── login.php             # Login page
├── logout.php            # Logout handler
├── setup-user.php        # CLI user management
├── scheduler.php         # Main scheduler (web + CLI)
├── config-editor.php     # Web config editor
└── config/               # Default config (for CLI)
    ├── config.php        # Used by CLI mode only
    └── config.example.php
```

### Web vs CLI Mode

**Web Mode** (authenticated):
- Uses user-specific config: `users/<hash>/config.php`
- Generates ICS in user directory: `users/<hash>/calendar_tags.ics`
- Requires login via `login.php`

**CLI Mode** (no authentication):
- Uses default config: `config/config.php`
- Generates ICS in root: `calendar_tags.ics`
- Run directly: `php scheduler.php`

## Security

### Password Storage

- Passwords are hashed using bcrypt (PASSWORD_BCRYPT)
- Support for Apache MD5 (apr1) and plain MD5 (legacy)
- Never stores plain text passwords

### Session Management

- PHP sessions with server-side storage
- Session destroyed on logout
- Authentication required for all web pages

### User Directory Permissions

Recommended permissions:
```bash
chmod 755 users/
chmod 755 users/*/
chmod 644 users/*/*.php
chmod 644 users/*/*.ics
chmod 600 .htpasswd
```

## User Management

### Add/Update User

```bash
php setup-user.php username password
```

### Remove User

1. Remove user line from `.htpasswd`
2. Delete user directory: `users/<hash>/`

### List Users

```bash
cat .htpasswd | cut -d: -f1
```

### Find User Directory

```bash
php -r "require 'auth.php'; \$auth = new Auth(); echo \$auth->getUserHash('username') . PHP_EOL;"
```

## Troubleshooting

### "Invalid username or password"

- Verify user exists in `.htpasswd`
- Check password is correct
- Ensure `.htpasswd` is readable by web server

### "Configuration file not found"

- User hasn't created config yet
- Click "Edit Configuration" to create one
- Or manually copy `config/config.example.php` to `users/<hash>/config.php`

### "Failed to write configuration"

- Check directory permissions
- Ensure `users/<hash>/` is writable
- Verify PHP has write access

### CLI mode not working

- Ensure `config/config.php` exists for CLI mode
- Web and CLI use separate configs
- Copy `config/config.example.php` to `config/config.php`

## Multi-User Example

```bash
# Create multiple users
php setup-user.php alice password123
php setup-user.php bob password456
php setup-user.php charlie password789

# Each user gets isolated directory
users/
├── 6384e2b2/  # alice's directory
├── 9dd4e461/  # bob's directory
└── bc2db5d3/  # charlie's directory
```

Each user:
- Has independent configuration
- Cannot see other users' data
- Gets separate ICS calendar files
- Can customize tags, slots, priorities independently

## Production Deployment

### Apache

```apache
<Directory /path/to/calendar-tags>
    Options -Indexes
    AllowOverride None
    Require all granted

    # Protect sensitive files
    <FilesMatch "^\.ht">
        Require all denied
    </FilesMatch>
</Directory>
```

### Nginx

```nginx
location ~ /\.ht {
    deny all;
}

location ~ /users/.*/.*\.php$ {
    deny all;
}
```

### Environment

- PHP 7.4+ recommended
- Enable `session` support
- Optional: `mbstring` for better UTF-8 support

## Backup

Important files to backup:
```bash
.htpasswd           # User credentials
users/              # All user data
config/config.php   # CLI configuration (if used)
```

Backup command:
```bash
tar -czf backup-$(date +%Y%m%d).tar.gz .htpasswd users/ config/config.php
```

Restore:
```bash
tar -xzf backup-20251109.tar.gz
```
