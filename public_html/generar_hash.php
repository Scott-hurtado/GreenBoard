<?php
$password = 'Sonora2027';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Contraseña: " . $password . "<br>";
echo "Hash: " . $hash . "<br>";

// Verificación
if (password_verify($password, $hash)) {
    echo "✅ Hash verificado correctamente";
} else {
    echo "❌ Error en verificación";
}
?>