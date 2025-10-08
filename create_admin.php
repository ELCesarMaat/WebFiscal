<!-- <?php
// Uso: ejecutar una vez para crear el primer admin. Borrar o proteger después.
die('404 not found');
$config = require __DIR__ . '/config.php';
$db = $config['db'] ?? null;
if (!$db) { die('config DB faltante'); }
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
if ($mysqli->connect_error) die($mysqli->connect_error);

// Cambia estos valores antes de ejecutar
$username = 'admin_5qbd';
$password = 'ELGvue5PGYeUjY8d'; // CAMBIA la contraseña
$email = 'admin@tu-dominio.com';
$display = 'Administrador';

// insertar
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare("INSERT INTO adminusers (Username, PasswordHash, Email, DisplayName) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss",$username,$hash,$email,$display);
if ($stmt->execute()) {
  echo "Admin creado: $username\n";
} else {
  echo "Error: " . $mysqli->error;
}
$stmt->close();
$mysqli->close();
?> -->