<?php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['login']) && !empty($_SESSION['login'])) {
    echo json_encode([
        'loggedIn' => true,
        'username' => $_SESSION['login']
    ]);
} else {
    echo json_encode([
        'loggedIn' => false
    ]);
}
?>
