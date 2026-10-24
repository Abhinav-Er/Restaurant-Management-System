<?php
// Starts the session. Include this as the very first line of every page.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}