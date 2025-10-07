<?php
$config = require __DIR__ . '/../config.php';
$db = $config['db'] ?? null;
$err = '';
session_start();
if (isset($_SESSION['admin_id'])) {
  header('Location: dashboard.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['username'] ?? '');
  $pass = $_POST['password'] ?? '';
  if ($user === '' || $pass === '') $err = 'Usuario y contraseña requeridos.';
  else {
    $mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
    if ($mysqli->connect_error) die('DB error');
    $stmt = $mysqli->prepare("SELECT Id, PasswordHash FROM adminusers WHERE Username = ? LIMIT 1");
    $stmt->bind_param("s",$user);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      if (password_verify($pass, $row['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['Id'];
        header('Location: dashboard.php'); exit;
      } else $err = 'Credenciales inválidas.';
    } else $err = 'Credenciales inválidas.';
    $stmt->close();
    $mysqli->close();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    /* pequeño estilo para el login */
    .admin-login { max-width:420px; margin:80px auto; padding:28px; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.08); }
    .admin-login h2 { margin:0 0 12px; }
    .admin-login input { width:100%; padding:10px 12px; margin:8px 0; border-radius:8px; border:1px solid #ddd; }
    .admin-login button { padding:10px 16px; border-radius:10px; background:var(--color-acento); color:#fff; border:none; font-weight:700; cursor:pointer; }
    .admin-login .error { color:#b00020; margin-bottom:8px; }
  </style>
</head>
<body>
  <main>
    <div class="admin-login">
      <h2>Iniciar sesión - Admin</h2>
      <?php if ($err): ?><div class="error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
      <form method="post" action="login.php">
        <input name="username" placeholder="Usuario" required>
        <input name="password" type="password" placeholder="Contraseña" required>
        <div style="text-align:right;"><button type="submit">Entrar</button></div>
      </form>
    </div>
  </main>
</body>
</html>