<?php
// Şifre hashleme
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
// Şifre doğrulama
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}
// SQL injection'a karşı input temizleme (prepared statement önerilir)
function sanitize_input($conn, $input) {
    return mysqli_real_escape_string($conn, $input);
} 