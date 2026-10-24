<?php
// Logs staff out. Customer login (if any) in the same browser is left alone.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
session_regenerate_id(true);

header('Location: login.php?success=loggedout');
exit;