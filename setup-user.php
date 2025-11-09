<?php
/**
 * User Setup Script
 *
 * Creates or updates users in .htpasswd file
 * Usage: php setup-user.php <username> <password>
 */

require_once 'auth.php';

// Must be run from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.\n");
}

// Check arguments
if ($argc < 3) {
    echo "Usage: php setup-user.php <username> <password>\n";
    echo "\nExample:\n";
    echo "  php setup-user.php admin mypassword123\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

// Validate username (alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo "ERROR: Username must contain only letters, numbers, and underscores.\n";
    exit(1);
}

// Validate password length
if (strlen($password) < 4) {
    echo "ERROR: Password must be at least 4 characters long.\n";
    exit(1);
}

$auth = new Auth();

if ($auth->addUser($username, $password)) {
    echo "✓ User '$username' has been created/updated successfully!\n";
    echo "\nUser directory: users/" . $auth->getUserHash($username) . "/\n";
    echo "\nYou can now login at: http://yourserver/login.php\n";
    exit(0);
} else {
    echo "ERROR: Failed to create/update user.\n";
    exit(1);
}
