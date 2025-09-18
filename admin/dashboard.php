<?php
require __DIR__ . '/inc/auth.php';
$config = require __DIR__ . '/../config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'],$db['user'],$db['pass'],$db['name']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Admin</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>.admin-box{max-width:1100px;margin:30px auto;padding:20px;background:#fff;border-radius:12px}</style>
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
    $res = $mysqli->query("SELECT C.Id,C.Nombre,C.Email,C.Telefono,C.FechaSolicitada,C.Estado,S.Titulo AS Servicio
                           FROM Citas C LEFT JOIN Servicios S ON C.ServicioId = S.Id
                           ORDER BY C.CreatedAt DESC LIMIT 10");
    if ($res && $res->num_rows) {
      echo "<table style='width:100%;border-collapse:collapse'><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Tel</th><th>Servicio</th><th>Fecha</th><th>Estado</th></tr>";
      while($r = $res->fetch_assoc()){
        echo "<tr style='border-top:1px solid #eee'><td>{$r['Id']}</td><td>".htmlspecialchars($r['Nombre'])."</td><td>".htmlspecialchars($r['Email'])."</td><td>".htmlspecialchars($r['Telefono'])."</td><td>".htmlspecialchars($r['Servicio'])."</td><td>".htmlspecialchars($r['FechaSolicitada'])."</td><td>".htmlspecialchars($r['Estado'])."</td></tr>";
      }
      echo "</table>";
    } else echo "<p>No hay citas recientes.</p>";
    $mysqli->close();
    ?>
  </div>
</body>
</html>