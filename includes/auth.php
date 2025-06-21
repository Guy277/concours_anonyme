<?php
function authenticateUser($email, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM utilisateurs WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role']; // 'admin', 'candidat' ou 'correcteur'
        return true;
    }
    return false;
}

function generateAnonymousId() {
    return uniqid('copy_', true);
}
?>