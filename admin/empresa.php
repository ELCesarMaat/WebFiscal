<?php
require __DIR__ . '/inc\auth.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
$err=''; $msg='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_csrf($_POST['csrf'] ?? '')) { $err = 'Token CSRF inválido.'; }
  else {
    $nombre = trim($_POST['Nombre'] ?? '');
    $telefono = trim($_POST['Telefono'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $direccion = trim($_POST['Direccion'] ?? '');
    $horario = trim($_POST['Horario'] ?? '');
    $detalle = trim($_POST['HorarioDetalle'] ?? '');
    $redes = json_encode($_POST['Redes'] ?? []);
    $logo = $_FILES['Logo']['name'] ? basename($_FILES['Logo']['name']) : null;
    if ($logo && move_uploaded_file($_FILES['Logo']['tmp_name'], __DIR__ . '/../img/'.$logo)) { /* ok */ }

    // upsert: si existe fila con Activo=1 actualiza, sino inserta
    $res = $mysqli->query("SELECT Id FROM Empresa WHERE Activo = 1 LIMIT 1");
    if ($res && $res->num_rows) {
      $row = $res->fetch_assoc();
      $stmt = $mysqli->prepare("UPDATE Empresa SET Nombre=?, Telefono=?, Email=?, Direccion=?, Horario=?, HorarioDetalle=?, Redes=?, Logo=? WHERE Id=?");
      $stmt->bind_param("sssssssi", $nombre,$telefono,$email,$direccion,$horario,$detalle,$redes,$logo,$row['Id']);
    } else {
      $stmt = $mysqli->prepare("INSERT INTO Empresa (Nombre, Telefono, Email, Direccion, Horario, HorarioDetalle, Redes, Logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssssss", $nombre,$telefono,$email,$direccion,$horario,$detalle,$redes,$logo);
    }
    if ($stmt->execute()) $msg = 'Información actualizada.'; else $err = $mysqli->error;
    $stmt->close();
  }
}

// leer valores actuales
$empresa = $mysqli->query("SELECT * FROM Empresa WHERE Activo = 1 LIMIT 1");
$empresa = $empresa ? $empresa->fetch_assoc() : [];
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Empresa</title><link rel="stylesheet" href="../css/styles.css"></head>
<body>
  <div style="max-width:900px;margin:24px auto;background:#fff;padding:18px;border-radius:12px">
    <h1>Datos de la empresa</h1>
    <?php if ($err) echo "<div style='color:#b00020'>$err</div>"; ?>
    <?php if ($msg) echo "<div style='color:green'>$msg</div>"; ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
      <label>Nombre</label><input name="Nombre" value="<?php echo htmlspecialchars($empresa['Nombre'] ?? '')?>" style="width:100%">
      <label>Teléfono</label><input name="Telefono" value="<?php echo htmlspecialchars($empresa['Telefono'] ?? '')?>">
      <label>Email</label><input name="Email" value="<?php echo htmlspecialchars($empresa['Email'] ?? '')?>">
      <label>Dirección</label><textarea name="Direccion"><?php echo htmlspecialchars($empresa['Direccion'] ?? '')?></textarea>
      <label>Horario</label><input name="Horario" value="<?php echo htmlspecialchars($empresa['Horario'] ?? '')?>">
      <label>Detalle Horario</label><textarea name="HorarioDetalle"><?php echo htmlspecialchars($empresa['HorarioDetalle'] ?? '')?></textarea>
      <label>Redes (objecto input name="Redes[key]")</label>
      <input name="Redes[facebook]" placeholder="Facebook" value="<?php echo htmlspecialchars($empresa['Redes'] ? json_decode($empresa['Redes'],true)['facebook'] ?? '' : '')?>">
      <label>Logo</label><input type="file" name="Logo" accept="image/*">
      <div style="margin-top:12px"><button class="btn btn-orange" type="submit">Guardar</button></div>
    </form>
  </div>
</body>
</html>
<?php $mysqli->close(); ?>