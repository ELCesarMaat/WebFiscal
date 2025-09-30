<?php
require __DIR__ . '/inc/auth.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);

// Procesar cambio de estado de cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
  if (!check_csrf($_POST['csrf'] ?? '')) {
    // token inválido -> redirigir evitando cambios
    header('Location: dashboard.php');
    exit;
  }
  $id = intval($_POST['id'] ?? 0);
  $newStatus = $_POST['status'] ?? '';
  $allowed = ['pendiente', 'realizado'];
  if ($id > 0 && in_array($newStatus, $allowed, true)) {
    $stmt = $mysqli->prepare("UPDATE citas SET Estado = ? WHERE Id = ?");
    if ($stmt) {
      $stmt->bind_param("si", $newStatus, $id);
      $stmt->execute();
      $stmt->close();
    }
  }
  // evitar reenvío del formulario
  header('Location: dashboard.php');
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Admin</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .admin-box{max-width:1100px;margin:30px auto;padding:20px;background:#fff;border-radius:12px}
    .btn-inline{display:inline-block;margin-right:6px;padding:6px 10px;border-radius:8px;border:1px solid #e6e9eb;background:#fff;cursor:pointer}
    .btn-done{background:#2ecc71;color:#fff;border:none}
    .btn-pend{background:#f0ad4e;color:#fff;border:none}
    table th, table td{padding:8px 10px;text-align:left}
    /* Badges de estado */
    .badge-status{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:0.85rem;letter-spacing:.2px}
    .badge-pendiente{background:#fff3cd;border:1px solid #ffeeba;color:#856404}
    .badge-realizada{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
    /* Botones deshabilitados */
    .btn-inline[disabled]{opacity:.6;cursor:not-allowed}
  </style>
</head>
<body>
  <div class="admin-box">
    <h1>Panel de administración</h1>
    <p>
      <a class="btn" href="services.php">Gestionar servicios</a>
      <a class="btn" href="empresa.php">Editar información de la empresa</a>
      <a class="btn" href="logout.php">Cerrar sesión</a>
    </p>
    <hr>
    <h3>Últimas citas</h3>
    <?php
    $res = $mysqli->query("SELECT C.Id,C.Nombre,C.Email,C.Telefono,C.Estado,S.Titulo AS Servicio
                           FROM citas C LEFT JOIN servicios S ON C.ServicioId = S.Id
                           ORDER BY C.estado, C.CreatedAt DESC");
    if ($res && $res->num_rows) {
      echo "<table style='width:100%;border-collapse:collapse'><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Tel</th><th>Servicio</th><th>Estado</th><th>Acciones</th></tr>";
      while($r = $res->fetch_assoc()){
        echo "<tr style='border-top:1px solid #eee'>";
        echo "<td>".htmlspecialchars($r['Id'])."</td>";
        echo "<td>".htmlspecialchars($r['Nombre'])."</td>";
        echo "<td>".htmlspecialchars($r['Email'])."</td>";
        echo "<td>".htmlspecialchars($r['Telefono'])."</td>";
        echo "<td>".htmlspecialchars($r['Servicio'])."</td>";
        $estado = strtolower($r['Estado'] ?? '');
        if ($estado === 'pendiente') {
          $badge = "<span class='badge-status badge-pendiente'>PENDIENTE</span>";
        } elseif ($estado === 'realizado') {
          $badge = "<span class='badge-status badge-realizada'>REALIZADA</span>";
        } else {
          $badge = htmlspecialchars($r['Estado'] ?? '');
        }
        echo "<td>".$badge."</td>";
        // acciones: dos formularios inline para marcar pendiente o realizado
        echo "<td>";
        // marcar pendiente
        echo '<form method="post" style="display:inline;margin-right:6px">';
        echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token']).'">';
        echo '<input type="hidden" name="action" value="update_status">';
        echo '<input type="hidden" name="id" value="'.htmlspecialchars($r['Id']).'">';
        echo '<input type="hidden" name="status" value="pendiente">';
        $disabledPend = ($estado === 'pendiente') ? ' disabled' : '';
        echo '<button class="btn-inline btn-pend" type="submit"'.$disabledPend.'>Pendiente</button>';
        echo '</form>';
        // marcar realizado
        echo '<form method="post" style="display:inline">';
        echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf_token']).'">';
        echo '<input type="hidden" name="action" value="update_status">';
        echo '<input type="hidden" name="id" value="'.htmlspecialchars($r['Id']).'">';
        echo '<input type="hidden" name="status" value="realizado">';
        $disabledDone = ($estado === 'realizado') ? ' disabled' : '';
        echo '<button class="btn-inline btn-done" type="submit"'.$disabledDone.'>Realizado</button>';
        echo '</form>';

        echo "</td></tr>";
      }
      echo "</table>";
    } else echo "<p>No hay citas recientes.</p>";
    $mysqli->close();
    ?>
  </div>
</body>
</html>