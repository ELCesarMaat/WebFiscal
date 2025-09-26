<?php
// Iniciar sesión para manejar tokens y mensajes flash (PRG)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
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
$res = $conexion->query("SELECT Nombre, Telefono, Email, correo_administrador, Direccion, Horario, HorarioDetalle, Redes, Logo, logo_full FROM empresa WHERE Activo = 1 LIMIT 1");
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

// Restaurar estado de éxito vía PRG (Post/Redirect/Get)
$success = false;
$emailWarnings = [];
// Datos adicionales para mostrar en el mensaje
$flash = [];
if (isset($_GET['ok']) && !empty($_SESSION['agendar_flash']['success'])) {
  $success = true;
  $flash = $_SESSION['agendar_flash'];
  $emailWarnings = $flash['warnings'] ?? [];
  // opcional: $citaId = $_SESSION['agendar_flash']['citaId'] ?? null;
  unset($_SESSION['agendar_flash']);
}

// Procesamiento simple del formulario (ahora usando el Id del servicio)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validar token CSRF de un solo uso
  $postedToken = $_POST['csrf_token'] ?? '';
  $sessionToken = $_SESSION['agendar_token'] ?? '';
  if (!$postedToken || !$sessionToken || !hash_equals($sessionToken, $postedToken)) {
    $errors[] = 'Sesión expirada o formulario inválido. Por favor recargue la página e intente de nuevo.';
  }

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
      // --- Preparar correos profesionales (multipart alternative) ---
      $site = $_SERVER['HTTP_HOST'] ?? 'MEDLEX';
      $empresaNombre = $empresa['Nombre'] ?? 'MEDLEX Despacho Jurídico';
      $logoFullFile = $empresa['logo_full'] ?? ($empresa['Logo'] ?? 'logofull.png');
      $logoFullUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($site) . '/img/' . rawurlencode($logoFullFile);
      $fechaEnvio = date('Y-m-d H:i:s');

      // Incluir mailer de abstracción
      require_once __DIR__ . '/mailer.php';

      // Datos comunes
      $plainAdmin = "Nueva solicitud de cita\n\n".
        "Nombre: {$nombre}\n".
        "Correo: {$email}\n".
        "Teléfono: {$telefono}\n".
        "Servicio: {$serviceTitle} (ID: {$servicioId})\n".
        ($fecha ? "Fecha solicitada: {$fecha}\n" : '').
        ($mensaje ? "Mensaje: \n{$mensaje}\n\n" : "") .
        "ID Cita: {$citaId}\nGenerado: {$fechaEnvio} ({$site})\n";

      $cid = 'logoCID';
      $htmlAdmin = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Nueva Cita</title>' .
        '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f6f8;color:#2b3540;margin:0;padding:0} .card{background:#ffffff;margin:20px auto;padding:24px;max-width:640px;border-radius:14px;box-shadow:0 6px 18px rgba(20,30,40,.08)} h1{margin:0 0 18px;font-size:20px;color:#1e2730} .meta{margin:0 0 6px;font-size:14px} .label{font-weight:600;color:#45525b} .footer{margin-top:32px;font-size:12px;color:#6b7880;text-align:center} .logo{max-width:240px;margin:0 0 18px} .hl{background:#f0f4f7;padding:10px 14px;border-radius:8px;white-space:pre-line;font-size:14px}</style></head><body>' .
        '<div class="card">'
        .'<img class="logo" src="cid:'.htmlspecialchars($cid).'" alt="Logo">'
        .'<h1>Nueva solicitud de cita</h1>'
        .'<p class="meta"><span class="label">Fecha recepción:</span> '.htmlspecialchars($fechaEnvio).'</p>'
        .'<p class="meta"><span class="label">ID Cita:</span> '.htmlspecialchars($citaId).'</p>'
        .'<p class="meta"><span class="label">Nombre:</span> '.htmlspecialchars($nombre).'</p>'
        .'<p class="meta"><span class="label">Correo:</span> '.htmlspecialchars($email).'</p>'
        .'<p class="meta"><span class="label">Teléfono:</span> '.($telefono?htmlspecialchars($telefono):'<em>No proporcionado</em>').'</p>'
        .'<p class="meta"><span class="label">Servicio:</span> '.htmlspecialchars($serviceTitle).' (ID '.htmlspecialchars($servicioId).')</p>'
        .($mensaje?'<div class="hl"><span class="label">Mensaje:</span> '.nl2br(htmlspecialchars($mensaje)).'</div>':'')
        .'<div class="footer">Puede responder directamente a este correo para ponerse en contacto con el cliente.<br>© '.date('Y').' '.htmlspecialchars($empresaNombre).'.</div>'
        .'</div></body></html>';

      // Enviar al administrador / propietario
      $fromAddress = 'no-reply@' . preg_replace('/^www\./','', $_SERVER['SERVER_NAME'] ?? 'medlex.mx');
      $adminSent = send_app_mail([
        'to' => _safe_email($ownerEmail),
        'subject' => "Nueva solicitud de cita: {$serviceTitle}",
        'text' => $plainAdmin,
        'html' => $htmlAdmin,
        'reply_to' => _safe_email($email),
        'from_name' => $nombre . ' – MEDLEX',
        'embed' => [ [ 'path' => __DIR__.'/img/'.$logoFullFile, 'cid' => $cid ] ]
      ]);
      if (!$adminSent) {
        $emailWarnings[] = 'No se pudo enviar correo al administrador (revisar configuración de correo del servidor).';
      }

      // Correo de confirmación al cliente
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $plainUser = "Estimado(a) {$nombre},\n\nHemos recibido su solicitud de cita referente a: {$serviceTitle}.".
          ($mensaje?"\n\nMensaje enviado:\n{$mensaje}\n":"\n").
          "\nNuestro equipo revisará la información y se pondrá en contacto con usted a la brevedad.\n\n".
          "Datos de referencia:\nID Cita: {$citaId}\nFecha: {$fechaEnvio}\n\nAtentamente,\n{$empresaNombre}";

        $htmlUser = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Confirmación de solicitud</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f6f8;margin:0;padding:0;color:#243039} .card{background:#ffffff;margin:20px auto;padding:28px;max-width:640px;border-radius:16px;box-shadow:0 8px 26px rgba(20,30,40,.08)} h1{margin:0 0 14px;font-size:22px;color:#1d2730} p{font-size:15px;line-height:1.5;margin:0 0 14px} .logo{max-width:240px;margin:0 0 18px} .ref{background:#f0f4f7;padding:14px 16px;border-radius:10px;font-size:13px;line-height:1.4;white-space:pre-line} .footer{margin-top:28px;font-size:12px;color:#6b7880;text-align:center} .strong{font-weight:600}</style></head><body>'
          .'<div class="card">'
          .'<img class="logo" src="cid:'.htmlspecialchars($cid).'" alt="Logo">'
          .'<h1>Confirmación de solicitud</h1>'
          .'<p>Estimado(a) <span class="strong">'.htmlspecialchars($nombre).'</span>,</p>'
          .'<p>Hemos recibido su solicitud de cita referente a <span class="strong">'.htmlspecialchars($serviceTitle).'</span>. Nuestro equipo revisará la información y se pondrá en contacto con usted a la brevedad para confirmar detalles.</p>'
          .($mensaje?'<div class="ref"><span class="strong">Mensaje enviado:</span> '.nl2br(htmlspecialchars($mensaje)).'</div>':'')
          .'<div class="ref">ID Cita: '.htmlspecialchars($citaId).' </div>'
          .'<p>Este mensaje es una constancia de recepción. No es necesario responder salvo que desee añadir información adicional.</p>'
          .'<p>Atentamente,<br>'.htmlspecialchars($empresaNombre).'</p>'
          .'<div class="footer">© '.date('Y').' '.htmlspecialchars($empresaNombre).'. Todos los derechos reservados.</div>'
          .'</div></body></html>';

        $userSent = send_app_mail([
          'to' => _safe_email($email),
          'subject' => 'Confirmación de solicitud de cita',
          'text' => $plainUser,
          'html' => $htmlUser,
          'reply_to' => _safe_email($ownerEmail),
          'embed' => [ [ 'path' => __DIR__.'/img/'.$logoFullFile, 'cid' => $cid ] ]
        ]);
        if (!$userSent) {
          $emailWarnings[] = 'La confirmación no pudo enviarse a su correo (guarde el ID de la cita).';
        }
      }

      // PRG: limpiar token usado y redirigir a GET para evitar reenvío en recarga
      unset($_SESSION['agendar_token']);
      $_SESSION['agendar_flash'] = [
        'success' => true,
        'warnings' => $emailWarnings,
        'citaId' => $citaId,
        'sent_admin' => (bool)$adminSent,
        'sent_user' => (bool)$userSent,
        'owner_email' => $ownerEmail,
        'client_email' => $email,
        'service_title' => $serviceTitle,
      ];
      header('Location: agendar.php?ok=1');
      exit;
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

    /* Botón con estado de carga */
    .btn.is-loading { opacity: 0.9; pointer-events: none; position: relative; }
    .btn .spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.55); border-top-color:#fff; border-radius: 50%; margin-left:10px; vertical-align: middle; animation: spin .7s linear infinite; }
    .btn.is-loading .spinner { display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
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
              Gracias, su solicitud ha sido recibida.
              <?php if (!empty($flash['service_title'])): ?>
                <br><strong>Tema/Servicio:</strong> <?php echo htmlspecialchars($flash['service_title']); ?>
              <?php endif; ?>
              <?php if (!empty($flash['sent_user']) && !empty($flash['client_email'])): ?>
                <br><strong>Confirmación enviada a:</strong> <?php echo htmlspecialchars($flash['client_email']); ?>
              <?php endif; ?>
              
              <br>Le contactaremos pronto.
            </div>
            <?php if (!empty($emailWarnings)): ?>
              <div class="notice warning" style="margin-top:12px; padding:12px; background:#fff8e1; color:#8a6d00; border-radius:8px;">
                <?php echo implode('<br>', array_map('htmlspecialchars', $emailWarnings)); ?>
                <?php if (!empty($GLOBALS['MAIL_LAST_ERROR'])): ?>
                  <br><strong>Detalle técnico:</strong> <?php echo htmlspecialchars($GLOBALS['MAIL_LAST_ERROR']); ?>
                <?php endif; ?>
                <br>Acciones recomendadas:
                <ul style="margin:6px 0 0 18px; padding:0; font-size:0.85rem; line-height:1.3;">
                  <li>Instale PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                  <li>Complete datos SMTP reales en <code>config.php</code></li>
                  <li>Verifique puerto/firewall (587 TLS o 465 SSL).</li>
                  <li>Si es Gmail: use contraseña de aplicación (2FA).</li>
                </ul>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <?php if (!empty($errors)): ?>
              <div class="notice error" style="margin-top:16px; padding:12px; background:#fff1f0; color:#7a1a1a; border-radius:8px;">
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
              </div>
            <?php endif; ?>

            <?php
              // Generar nuevo token CSRF para la siguiente solicitud (o primera carga)
              $formToken = bin2hex(random_bytes(16));
              $_SESSION['agendar_token'] = $formToken;
            ?>
            <form id="agendarForm" action="agendar.php" method="post" style="margin-top:18px; display:grid; gap:12px; position:relative;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($formToken); ?>">
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
               <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                <button id="submitBtn" type="submit" class="btn btn-orange" style="padding:10px 18px; border-radius:8px; font-weight:700; display:inline-flex; align-items:center;">
                  <span class="btn-label">Enviar solicitud</span>
                  <span class="spinner" aria-hidden="true"></span>
                </button>
                <span id="formLoading" style="display:none; font-size:0.95rem; color:#6b7a82;">Procesando su solicitud...</span>
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
  
  <script>
    (function(){
      const form = document.getElementById('agendarForm');
      if (!form) return;
      const btn = document.getElementById('submitBtn');
      const loadingText = document.getElementById('formLoading');
      let submitting = false;

      form.addEventListener('submit', function(){
        if (submitting) return;
        submitting = true;
        if (btn){
          btn.classList.add('is-loading');
          btn.setAttribute('disabled', 'disabled');
        }
        if (loadingText){
          loadingText.style.display = 'inline';
        }
      });
    })();
  </script>
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