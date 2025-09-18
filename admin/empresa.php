<?php
require __DIR__ . '/inc/auth.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
$err=''; $msg='';

// cargar datos actuales
$res = $mysqli->query("SELECT Id, Nombre, Telefono, Email, correo_administrador, Direccion, Horario, HorarioDetalle, Redes, Logo FROM empresa WHERE Activo = 1 LIMIT 1");
$empresa = $res && $res->num_rows ? $res->fetch_assoc() : [];
// decodificar redes para uso en inputs
$redes_actuales = [];
if (!empty($empresa['Redes'])) {
  $tmp = json_decode($empresa['Redes'], true);
  if (is_array($tmp)) $redes_actuales = $tmp;
}
$csrf = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? '')) { 
    $err = 'Token CSRF inválido.'; 
  } else {
    $nombre = trim($_POST['Nombre'] ?? '');
    $telefono = trim($_POST['Telefono'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $correo_admin = trim($_POST['CorreoAdministrador'] ?? '');
    $direccion = trim($_POST['Direccion'] ?? '');
    $horario = trim($_POST['Horario'] ?? '');
    $detalle = trim($_POST['HorarioDetalle'] ?? '');
    $redes_input = $_POST['Redes'] ?? [];
    // normalizar redes (solo strings)
    $redes_clean = [];
    foreach ($redes_input as $k => $v) {
      $k = trim((string)$k);
      $v = trim((string)$v);
      if ($k !== '') $redes_clean[$k] = $v;
    }
    $redes_json = json_encode($redes_clean, JSON_UNESCAPED_UNICODE);

    // manejar logo: si se sube, guardar con nombre fijo logo.<ext>; si no, mantener actual
    $logoName = null;
    if (!empty($_FILES['Logo']['name']) && is_uploaded_file($_FILES['Logo']['tmp_name'])) {
      $tmp = $_FILES['Logo']['tmp_name'];
      // obtener y normalizar extensión
      $ext = strtolower(pathinfo($_FILES['Logo']['name'], PATHINFO_EXTENSION));
      $allowed = ['png','jpg','jpeg','svg','gif','webp'];
      if (!in_array($ext, $allowed, true)) {
        $err = $err ?: 'Formato de imagen no permitido. Use: ' . implode(', ', $allowed);
      } else {
        // nombre fijo: logo.<ext>
        $logoName = 'logo.' . $ext;
        $dest = __DIR__ . '/../img/' . $logoName;
        if (!move_uploaded_file($tmp, $dest)) {
          $err = $err ?: 'No se pudo subir el logo (ver permisos en la carpeta img).';
          $logoName = null;
        } else {
          @chmod($dest, 0644);
        }
      }
    }

    // upsert: si existe fila con Activo=1 actualiza, sino inserta
    $res2 = $mysqli->query("SELECT Id FROM empresa WHERE Activo = 1 LIMIT 1");
    if ($res2 && $res2->num_rows) {
      $row = $res2->fetch_assoc();
      if ($logoName) {
        $stmt = $mysqli->prepare("UPDATE empresa SET Nombre=?, Telefono=?, Email=?, correo_administrador=?, Direccion=?, Horario=?, HorarioDetalle=?, Redes=?, Logo=? WHERE Id=?");
        // 9 strings + 1 int => 9 's' y 1 'i'
        $stmt->bind_param("sssssssssi", $nombre, $telefono, $email, $correo_admin, $direccion, $horario, $detalle, $redes_json, $logoName, $row['Id']);
      } else {
        $stmt = $mysqli->prepare("UPDATE empresa SET Nombre=?, Telefono=?, Email=?, correo_administrador=?, Direccion=?, Horario=?, HorarioDetalle=?, Redes=? WHERE Id=?");
        // 8 strings + 1 int => 8 's' y 1 'i'
        $stmt->bind_param("ssssssssi", $nombre, $telefono, $email, $correo_admin, $direccion, $horario, $detalle, $redes_json, $row['Id']);
      }
    } else {
      // insertar nueva fila
      $stmt = $mysqli->prepare("INSERT INTO empresa (Nombre, Telefono, Email, Direccion, Horario, HorarioDetalle, Redes, Logo, Activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
      $logo_to_insert = $logoName ?? ($empresa['Logo'] ?? null);
      $stmt->bind_param("ssssssss", $nombre, $telefono, $email, $direccion, $horario, $detalle, $redes_json, $logo_to_insert);
    }

    if (isset($stmt) && $stmt) {
      if ($stmt->execute()) {
        $msg = 'Información actualizada.';
      } else {
        $err = 'Error al guardar: ' . $stmt->error;
      }
      $stmt->close();
    } elseif (!$err) {
      $err = 'Error interno al preparar la consulta.';
    }

    // recargar datos actuales después de guardar
    $res = $mysqli->query("SELECT Id, Nombre, Telefono, Email, correo_administrador, Direccion, Horario, HorarioDetalle, Redes, Logo FROM empresa WHERE Activo = 1 LIMIT 1");
    $empresa = $res && $res->num_rows ? $res->fetch_assoc() : [];
    $redes_actuales = [];
    if (!empty($empresa['Redes'])) {
      $tmp = json_decode($empresa['Redes'], true);
      if (is_array($tmp)) $redes_actuales = $tmp;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Empresa - Admin</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .wrap { max-width:980px; margin:28px auto; background:#fff; padding:20px; border-radius:12px }
    label { display:block; margin-top:12px; font-weight:700; color:var(--color-azul-oscuro) }
    input[type="text"], input[type="email"], textarea { width:100%; padding:10px; border:1px solid #e6e9eb; border-radius:8px }
    .row { display:flex; gap:12px; margin-top:8px }
    .col { flex:1 }
    .logo-preview { max-height:84px; display:block; margin-top:8px; border-radius:8px; }
    .social-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap:8px; margin-top:8px; }
    .actions { margin-top:16px; text-align:right }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Datos de la empresa</h1>
    <?php if ($err): ?><div style="color:#b00020"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <?php if ($msg): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">

      <label>Nombre</label>
      <input name="Nombre" type="text" value="<?php echo htmlspecialchars($empresa['Nombre'] ?? ''); ?>">

      <div class="row">
        <div class="col">
          <label>Teléfono</label>
          <input name="Telefono" type="text" value="<?php echo htmlspecialchars($empresa['Telefono'] ?? ''); ?>">
        </div>
        <div class="col">
          <label>Email</label>
          <input name="Email" type="email" value="<?php echo htmlspecialchars($empresa['Email'] ?? ''); ?>">
        </div>
      </div>

      <label>Correo administrador</label>
      <input name="CorreoAdministrador" type="email" value="<?php echo htmlspecialchars($empresa['correo_administrador'] ?? ''); ?>">

      <label>Dirección</label>
      <textarea name="Direccion" rows="3"><?php echo htmlspecialchars($empresa['Direccion'] ?? ''); ?></textarea>

      <div class="row">
        <div class="col">
          <label>Horario</label>
          <input name="Horario" type="text" value="<?php echo htmlspecialchars($empresa['Horario'] ?? ''); ?>">
        </div>
        <div class="col">
          <label>Detalle horario</label>
          <input name="HorarioDetalle" type="text" value="<?php echo htmlspecialchars($empresa['HorarioDetalle'] ?? ''); ?>">
        </div>
      </div>

      <label>Redes sociales</label>
      <div class="social-grid">
        <input name="Redes[facebook]" placeholder="Facebook" value="<?php echo htmlspecialchars($redes_actuales['facebook'] ?? ''); ?>">
        <input name="Redes[instagram]" placeholder="Instagram" value="<?php echo htmlspecialchars($redes_actuales['instagram'] ?? ''); ?>">
        <input name="Redes[telegram]" placeholder="Telegram" value="<?php echo htmlspecialchars($redes_actuales['telegram'] ?? ''); ?>">
        <input name="Redes[twitter]" placeholder="Twitter/X" value="<?php echo htmlspecialchars($redes_actuales['twitter'] ?? ''); ?>">
        <input name="Redes[youtube]" placeholder="YouTube" value="<?php echo htmlspecialchars($redes_actuales['youtube'] ?? ''); ?>">
        <input name="Redes[linkedin]" placeholder="LinkedIn" value="<?php echo htmlspecialchars($redes_actuales['linkedin'] ?? ''); ?>">
      </div>

      <label>Logo</label>
      <?php if (!empty($empresa['Logo'])): ?>
        <img src="../img/<?php echo htmlspecialchars($empresa['Logo']); ?>" alt="Logo" class="logo-preview">
      <?php endif; ?>
      <input type="file" name="Logo" accept="image/*">

      <div class="actions">
        <a class="btn" href="dashboard.php">← Volver</a>
        <button class="btn btn-orange" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</body>
</html>
<?php $mysqli->close(); ?>