<?php
$config = require __DIR__ . '/config.php';
$dbconf = $config['db'] ?? null;
if (!$dbconf) {
  die('Configuración de base de datos no encontrada en config.php');
}
$conexion = new mysqli($dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name']);
if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}

// --- obtener datos de la empresa desde la BD (si existe) ---
$empresa = [];
$res = $conexion->query("SELECT Nombre, Telefono, Email, correo_administrador, Direccion, Horario, HorarioDetalle, Redes, Logo FROM empresa WHERE Activo = 1 LIMIT 1");
if ($res) {
  $empresa = $res->fetch_assoc();
  $res->free();
}
// decodificar redes si vienen en JSON
$empresa_redes = [];
if (!empty($empresa['Redes'])) {
  $tmp = json_decode($empresa['Redes'], true);
  if (is_array($tmp)) $empresa_redes = $tmp;
}

// usar correo de administrador si existe, si no usar email de empresa, si no usar config
$ownerEmail = $empresa['correo_administrador'] ?? $empresa['Email'] ?? ($config['owner_email'] ?? 'contacto@medlex.mx');

// Procesamiento simple del formulario (ahora usando el Id del servicio)
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $email  = trim($_POST['email'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');
  $servicioId = trim($_POST['servicio'] ?? ''); // ahora contiene el Id
  $fecha = trim($_POST['fecha'] ?? ''); // opcional, puede venir vacío
  $mensaje = trim($_POST['mensaje'] ?? '');

  if ($nombre === '') $errors[] = 'El nombre es requerido.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo electrónico inválido.';
  if ($servicioId === '') $errors[] = 'Selecciona un servicio.';
  // Nota: ya no se exige fecha aquí (el input fue eliminado)

  if (empty($errors)) {
    // evita inyección en cabeceras
    function _safe_email($e){ $e = filter_var($e, FILTER_SANITIZE_EMAIL); return str_replace(["\r","\n","%0a","%0d"], '', $e); }

    // obtener título del servicio por Id (para el asunto y cuerpo)
    $serviceTitle = 'Servicio';
    $stmt = $conexion->prepare("SELECT Titulo FROM servicios WHERE Id = ? LIMIT 1");
    if ($stmt) {
      $sid = intval($servicioId);
      $stmt->bind_param("i", $sid);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) $serviceTitle = $row['Titulo'];
      $stmt->close();
    }

    // --- Guardar la cita en la base de datos ---
    $insertOk = false;
    $citaId = null;
    $ins = $conexion->prepare(
      "INSERT INTO Citas (ServicioId, Nombre, Email, Telefono, Mensaje, Estado)
       VALUES (?, ?, ?, ?, ?, 'pendiente')"
    );
    if ($ins) {
      $sid = intval($servicioId);
      $ins->bind_param("issss", $sid, $nombre, $email, $telefono, $mensaje);
      if ($ins->execute()) {
        $citaId = $ins->insert_id;
        $insertOk = true;
      } else {
        $errors[] = 'Error al guardar la cita. Intente nuevamente.';
        // opcional: error_log('Insert Citas error: ' . $ins->error);
      }
      $ins->close();
    } else {
      $errors[] = 'Error interno al preparar la base de datos.';
      // opcional: error_log('Prepare insert Citas failed: ' . $conexion->error);
    }

    if ($insertOk) {
      // preparar y enviar correos (owner + admin)
      $site = $_SERVER['HTTP_HOST'] ?? 'MEDLEX';

      $subject = "Nueva solicitud de cita - MEDLEX: " . $serviceTitle;
      $body  = "Nueva solicitud de cita recibida\n\n";
      $body .= "Nombre: " . $nombre . "\n";
      $body .= "Email: " . $email . "\n";
      $body .= "Teléfono: " . $telefono . "\n";
      $body .= "Servicio: " . $serviceTitle . " (ID: " . $servicioId . ")\n";
      if ($fecha) $body .= "Fecha solicitada: " . $fecha . "\n\n";
      $body .= "Mensaje:\n" . $mensaje . "\n\n";
      $body .= "ID Cita: " . $citaId . "\n";
      $body .= "Enviado desde: " . $site . " - " . date('Y-m-d H:i:s') . "\n";

      $fromAddress = 'no-reply@' . preg_replace('/^www\./','', $_SERVER['SERVER_NAME'] ?? 'medlex.mx');
      $headers  = "From: MEDLEX <" . $fromAddress . ">\r\n";
      if ($email) $headers .= "Reply-To: " . _safe_email($email) . "\r\n";
      $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

      $recipients = [
        $ownerEmail
      ];
      $to = implode(',', array_map(function($e){ return _safe_email($e); }, $recipients));
      @mail($to, $subject, $body, $headers);

      // confirmar al usuario
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sub2 = "Confirmación: solicitud de cita en MEDLEX";
        $msg2 = "Hola " . $nombre . ",\n\nHemos recibido su solicitud de cita para: " . $serviceTitle . "\n\nEn breve nos pondremos en contacto para confirmar la cita.\n\nMEDLEX Despacho Jurídico\nID de solicitud: " . $citaId;
        $headers2 = "From: MEDLEX <" . $fromAddress . ">\r\n";
        $headers2 .= "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail(_safe_email($email), $sub2, $msg2, $headers2);
      }

      $success = true;
    }
  }
}

// Conectar y obtener lista de servicios para el select (Id + Titulo + Image)
$config = require __DIR__ . '/config.php';
$dbconf = $config['db'];
$conexion = new mysqli($dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name']);
if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}
// Obtener lista de servicios para el select (Id + Titulo + Image) ordenados por Orden
$servicios = [];
$res = $conexion->query("SELECT Id, Titulo, Image FROM servicios WHERE Activo = 1 ORDER BY Orden ASC, Titulo ASC");
if ($res) {
  while($r = $res->fetch_assoc()) $servicios[] = $r;
  $res->free();
}

// Valor seleccionado (GET desde la tarjeta o POST si ya envió el formulario)
$selectedServicioId = (string)($_GET['servicio_id'] ?? $_POST['servicio'] ?? '');

// Imagen inicial (si hay servicio seleccionado)
$initialImage = '';
foreach ($servicios as $s) {
  if ((string)$s['Id'] === (string)$selectedServicioId) {
    $initialImage = $s['Image'];
    break;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Agendar | MEDLEX Despacho Jurídico</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    /* cambios específicos para animar fondo y mejorar legibilidad */
    .hero { position: relative; overflow: hidden; }

    /* capa de imágenes (cada .bg-image es una imagen que hace crossfade) */
    .hero-bg { position: absolute; inset: 0; z-index: 0; pointer-events: none; }

    .hero-bg .bg-image {
      position: absolute;
      inset: 0;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      transition: opacity 0.6s ease, transform 0.6s ease, filter 0.6s ease;
      opacity: 0;
      transform: scale(1.02);
      /* ajuste de brillo para que la imagen no compita con el texto */
      filter: brightness(0.58) contrast(0.95);
      /* sombra interna para oscurecer el área superior/inferior */
      box-shadow: inset 0 120px 140px rgba(0,0,0,0.55), inset 0 -80px 120px rgba(0,0,0,0.45);
    }
    .hero-bg .bg-image.show { opacity: 1; transform: scale(1); filter: brightness(0.6) contrast(1); }

    /* overlay extra (uniforma el oscurecimiento) */
    .hero-bg::after {
      content: "";
      position: absolute;
      inset: 0;
      z-index: 1;
      pointer-events: none;
      background: linear-gradient(180deg, rgba(0,0,0,0.48), rgba(0,0,0,0.56));
    }

    /* contenido del hero por encima del fondo */
    .hero-content { position: relative; z-index: 2; }

    /* FORZAR texto de introducción en blanco para legibilidad */
    .hero-text { color: #fff; text-shadow: 0 6px 18px rgba(0,0,0,0.6); }
    .hero-text h2.section-title,
    .hero-text h1,
    .hero-text p {
      color: #fff;
      margin-top: 0;
    }
    .hero-text p { opacity: 0.95; color: rgba(255,255,255,0.92); }

    /* Mantener la "tarjeta" del formulario blanca y legible */
    .form-card, .hero-content form {
      background: rgba(255,255,255,0.97);
      border-radius: 12px;
      box-shadow: 0 12px 30px rgba(10,20,30,0.06);
      padding: 18px;
      color: var(--color-azul-oscuro);
    }

    /* Ajustes responsivos (opcional) */
    @media (max-width: 900px) {
      .hero-bg .bg-image { box-shadow: inset 0 80px 100px rgba(0,0,0,0.55), inset 0 -40px 80px rgba(0,0,0,0.45); }
      .hero-text { text-align: left; }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-container">
      <a href="index.php"><img src="img/logo.png" alt="MEDLEX" class="logo"></a>
      <nav>
        <ul>
          <li><a href="index.php#inicio">Quienes somos</a></li>
          <li><a href="index.php#servicios">Servicios</a></li>
          <li><a href="agendar.php">Agendar</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero" style="padding-top:32px; padding-bottom:24px;">
      <!-- contenedor para imágenes de fondo (crossfade) -->
      <div class="hero-bg" aria-hidden="true">
        <?php if ($initialImage): ?>
          <div class="bg-image show" style="background-image: url('img/<?php echo htmlspecialchars($initialImage); ?>');"></div>
        <?php endif; ?>
      </div>

      <div class="hero-content" style="max-width:1100px; margin:auto; gap:28px; display:flex;">
        <div class="hero-text" style="flex:1 1 480px;">
          <h2 class="section-title">Agendar cita</h2>
          <p style="font-size:1.05rem; color:white;">
            Completa el formulario para solicitar una cita con nuestros especialistas. Te confirmaremos la fecha y hora por correo o llamada.
          </p>

          <?php if ($success): ?>
            <div class="notice success" style="margin-top:16px; padding:12px; background:#e6f7ea; color:#145a2a; border-radius:8px;">
              Gracias, su solicitud ha sido recibida. Le contactaremos pronto.
            </div>
          <?php else: ?>
            <?php if (!empty($errors)): ?>
              <div class="notice error" style="margin-top:16px; padding:12px; background:#fff1f0; color:#7a1a1a; border-radius:8px;">
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
              </div>
            <?php endif; ?>

            <form action="agendar.php" method="post" style="margin-top:18px; display:grid; gap:12px; position:relative;">
              <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <input type="text" name="nombre" placeholder="Nombre completo" value="<?php echo htmlspecialchars($_POST['nombre'] ?? '') ?>" required style="flex:1; padding:10px; border-radius:8px; border:1px solid #ddd;">
                <input type="email" name="email" placeholder="Correo electrónico" value="<?php echo htmlspecialchars($_POST['email'] ?? '') ?>" required style="flex:1; padding:10px; border-radius:8px; border:1px solid #ddd;">
              </div>

              <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <input type="text" name="telefono" placeholder="Teléfono (opcional)" value="<?php echo htmlspecialchars($_POST['telefono'] ?? '') ?>" style="flex:1; padding:10px; border-radius:8px; border:1px solid #ddd;">
                <select name="servicio" required style="flex:1; padding:10px; border-radius:8px; border:1px solid #ddd;">
                  <option value="">Seleccione un servicio</option>
                  <?php foreach($servicios as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['Id']); ?>" data-image="<?php echo htmlspecialchars($s['Image']); ?>" <?php if((string)$s['Id'] === (string)$selectedServicioId) echo 'selected'; ?>>
                      <?php echo htmlspecialchars($s['Titulo']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <textarea name="mensaje" placeholder="Mensaje adicional (opcional)" rows="4" style="padding:10px; border-radius:8px; border:1px solid #ddd;"><?php echo htmlspecialchars($_POST['mensaje'] ?? '') ?></textarea>
               <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button type="submit" class="btn btn-orange" style="padding:10px 18px; border-radius:8px; font-weight:700;">Enviar solicitud</button>
              </div>
            </form>
          <?php endif; ?>
        </div>

        <aside style="width:320px; min-width:260px;">
          <div style="background:#fff; padding:18px; border-radius:12px; box-shadow:0 6px 18px rgba(10,20,30,0.06); position:relative; z-index:2;">
            <h3 style="margin:0 0 8px 0;">Contacto</h3>
            <p style="margin:0 0 12px 0; color:#334451;">
              <strong>Email:</strong><br>
              <a href="mailto:<?php echo htmlspecialchars($empresa['Email'] ?? 'contacto@medlex.mx'); ?>"><?php echo htmlspecialchars($empresa['Email'] ?? 'contacto@medlex.mx'); ?></a><br><br>

              <strong>Teléfono:</strong><br>
              <?php if (!empty($empresa['Telefono'])): ?>
                <a href="tel:<?php echo htmlspecialchars($empresa['Telefono']); ?>"><?php echo htmlspecialchars($empresa['Telefono']); ?></a><br><br>
              <?php else: ?>
                <span>No disponible</span><br><br>
              <?php endif; ?>

              <strong>Dirección:</strong><br>
              <?php echo nl2br(htmlspecialchars($empresa['Direccion'] ?? '')); ?>
            </p>
            <hr style="border:none; border-top:1px solid #f0f0f0; margin:12px 0;">
            <p style="margin:0; font-size:0.95rem; color:#6b7a82;">
              Horario de atención:<br><?php echo htmlspecialchars($empresa['Horario'] ?? ''); ?>
              <?php if (!empty($empresa['HorarioDetalle'])): ?><br><small><?php echo htmlspecialchars($empresa['HorarioDetalle']); ?></small><?php endif; ?>
            </p>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <footer>
    <div class="footer-bg" style="padding:28px 0;">
      <div style="max-width:1100px; margin:auto; display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div style="min-width:220px;">
          <strong><?php echo htmlspecialchars($empresa['Nombre'] ?? 'MEDLEX Despacho Jurídico'); ?></strong><br>
          <?php echo nl2br(htmlspecialchars($empresa['Direccion'] ?? '')); ?>
        </div>
        <div style="text-align:center; min-width:220px;">
          <img src="img/<?php echo htmlspecialchars($empresa['Logo'] ?? 'logofull.png'); ?>" alt="Logo" style="max-width:160px; width:100%; height:auto;">
        </div>
        <div style="min-width:220px; text-align:right;">
          <strong>Contacto</strong><br>
          <a href="mailto:<?php echo htmlspecialchars($empresa['Email'] ?? 'contacto@medlex.mx'); ?>"><?php echo htmlspecialchars($empresa['Email'] ?? 'contacto@medlex.mx'); ?></a><br>
          <?php if (!empty($empresa['Telefono'])): echo htmlspecialchars($empresa['Telefono']); endif; ?>
        </div>
      </div>
      <hr style="margin:18px auto 12px; max-width:1100px; border:none; border-top:1px solid #eee;">
      <div style="max-width:1100px; margin:auto; display:flex; justify-content:space-between; flex-wrap:wrap; font-size:0.95rem;">
        <span>Copyright © <?php echo date('Y'); ?> MEDLEX Despacho Jurídico. Todos los derechos reservados.</span>
        <span>
          <a href="#" style="margin-right:18px;">Políticas de Asesoría Personalizada</a>
          <a href="#">Aviso de Privacidad</a>
        </span>
      </div>
    </div>
  </footer>

  <script>
    // mapa id -> image (desde PHP)
    const imageMap = <?php
      $map = [];
      foreach ($servicios as $s) $map[$s['Id']] = $s['Image'];
      echo json_encode($map, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
    ?>;

    const heroBg = document.querySelector('.hero-bg');
    const select = document.querySelector('select[name="servicio"]');

    function changeHeroBgById(id) {
      const img = imageMap[id] || '';
      if (!img) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'bg-image';
      wrapper.style.backgroundImage = `url('img/${img}')`;
      heroBg.appendChild(wrapper);
      // forzar reflow y mostrar
      requestAnimationFrame(() => wrapper.classList.add('show'));
      // limpiar imágenes antiguas después de animar
      setTimeout(() => {
        const imgs = heroBg.querySelectorAll('.bg-image');
        // dejar solo la última (la visible)
        imgs.forEach((el, idx) => {
          if (idx < imgs.length - 1) el.remove();
        });
      }, 700);
    }

    if (select) {
      // inicial: si no existe fondo y hay un seleccionado, setearlo
      const initial = '<?php echo htmlspecialchars($selectedServicioId, ENT_QUOTES); ?>';
      if (initial && (!heroBg.querySelector('.bg-image') || heroBg.querySelectorAll('.bg-image').length === 0)) {
        changeHeroBgById(initial);
      }

      select.addEventListener('change', function() {
        changeHeroBgById(this.value);
      });
    }
  </script>


</html></body>
  <script src="js/script.js"></script>
</body>
</html>
<?php
$conexion->close();
?>