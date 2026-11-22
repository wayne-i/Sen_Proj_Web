
<?php
// =====================================================
// 1. DATABASE CONFIGURATION AND SESSION INITIALIZATION
// =====================================================

require_once 'config.php';


// =====================================================
// 2. CLEAR SESSION DATA
// =====================================================

$_SESSION = [];


// =====================================================
// 3. REMOVE SESSION COOKIE
// =====================================================

if (ini_get('session.use_cookies')) {

    $cookieParams = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $cookieParams['path'],
            'domain'   => $cookieParams['domain'],
            'secure'   => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]
    );
}


// =====================================================
// 4. DESTROY SESSION
// =====================================================

session_destroy();


// =====================================================
// 5. REDIRECT TO HOMEPAGE
// =====================================================

header('Location: index.php');
exit;

?>
