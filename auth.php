<?php
/**
 * Authentication Helper Class
 *
 * Manages user authentication using .htpasswd file
 * and provides user-specific directory hashing
 */
class Auth
{
    private $htpasswdFile = '.htpasswd';
    private $usersDir = 'users';

    /**
     * Start session if not already started
     */
    public function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     *
     * @return bool True if authenticated
     */
    public function isAuthenticated()
    {
        $this->startSession();
        return isset($_SESSION['authenticated']) &&
               $_SESSION['authenticated'] === true &&
               isset($_SESSION['username']);
    }

    /**
     * Get current username
     *
     * @return string|null Username or null if not authenticated
     */
    public function getUsername()
    {
        $this->startSession();
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get user hash for directory naming
     *
     * @param string|null $username Username (uses current user if null)
     * @return string|null Short hash or null if no user
     */
    public function getUserHash($username = null)
    {
        if ($username === null) {
            $username = $this->getUsername();
        }

        if ($username === null) {
            return null;
        }

        // Generate short hash (8 characters) from username
        return substr(md5($username), 0, 8);
    }

    /**
     * Get user-specific directory path
     *
     * @param string|null $username Username (uses current user if null)
     * @return string|null Directory path or null if no user
     */
    public function getUserDir($username = null)
    {
        $hash = $this->getUserHash($username);
        if ($hash === null) {
            return null;
        }

        $dir = $this->usersDir . '/' . $hash;

        // Create directory if it doesn't exist
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Verify user credentials
     *
     * @param string $username Username
     * @param string $password Password
     * @return bool True if credentials are valid
     */
    public function verify($username, $password)
    {
        if (!file_exists($this->htpasswdFile)) {
            return false;
        }

        $lines = file($this->htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            list($storedUser, $storedHash) = $parts;

            if ($storedUser === $username) {
                // Support bcrypt, apr1 (Apache MD5), and plain MD5
                if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
                    // bcrypt
                    return password_verify($password, $storedHash);
                } elseif (strpos($storedHash, '$apr1$') === 0) {
                    // Apache MD5 (apr1)
                    return $this->verifyApr1($password, $storedHash);
                } else {
                    // Plain MD5 (less secure, but common in .htpasswd)
                    return md5($password) === $storedHash;
                }
            }
        }

        return false;
    }

    /**
     * Verify Apache MD5 (apr1) password
     *
     * @param string $password Plain password
     * @param string $hash apr1 hash
     * @return bool True if password matches
     */
    private function verifyApr1($password, $hash)
    {
        // Extract salt from hash
        $parts = explode('$', $hash);
        if (count($parts) < 3) {
            return false;
        }

        $salt = $parts[2];

        // Generate hash with same salt
        $generatedHash = $this->apr1Hash($password, $salt);

        return $generatedHash === $hash;
    }

    /**
     * Generate Apache MD5 (apr1) hash
     *
     * @param string $password Plain password
     * @param string $salt Salt
     * @return string apr1 hash
     */
    private function apr1Hash($password, $salt)
    {
        $salt = substr($salt, 0, 8);
        $len = strlen($password);
        $text = $password . '$apr1$' . $salt;
        $bin = pack("H32", md5($password . $salt . $password));

        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }

        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $password[0];
        }

        $bin = pack("H32", md5($text));

        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $password : $bin;

            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $password;
            }

            $new .= ($i & 1) ? $bin : $password;
            $bin = pack("H32", md5($new));
        }

        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) {
                $j = 5;
            }
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(
            strrev(substr(base64_encode($tmp), 2)),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
            "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
        );

        return '$apr1$' . $salt . '$' . $tmp;
    }

    /**
     * Login user and create session
     *
     * @param string $username Username
     * @return void
     */
    public function login($username)
    {
        $this->startSession();
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;

        // Create user directory
        $this->getUserDir($username);
    }

    /**
     * Logout user and destroy session
     *
     * @return void
     */
    public function logout()
    {
        $this->startSession();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Add or update user in .htpasswd
     *
     * @param string $username Username
     * @param string $password Plain password
     * @return bool True on success
     */
    public function addUser($username, $password)
    {
        // Use bcrypt for new users
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $lines = [];
        $userExists = false;

        if (file_exists($this->htpasswdFile)) {
            $lines = file($this->htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Update existing user
            foreach ($lines as $i => $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2 && $parts[0] === $username) {
                    $lines[$i] = "$username:$hash";
                    $userExists = true;
                    break;
                }
            }
        }

        // Add new user
        if (!$userExists) {
            $lines[] = "$username:$hash";
        }

        return file_put_contents($this->htpasswdFile, implode("\n", $lines) . "\n") !== false;
    }

    /**
     * Require authentication - redirect to login if not authenticated
     *
     * @return void
     */
    public function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
    }
}
