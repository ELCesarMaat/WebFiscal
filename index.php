<?php
$config = require __DIR__ . '/config.php';
$dbconf = $config['db'];
$conexion = new mysqli($dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name']);
if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}

// --- obtener datos de la empresa (reutilizable) ---
require_once __DIR__ . '/inc/empresa.php';
$empresaNombre = empresa_nombre('MEDLEX Despacho Jurídico');
$logoFullFile = empresa_logo_full_filename('logofull.png');

// Consulta para obtener los servicios, incluyendo la imagen y el Id
$sql = "SELECT Id, Titulo, Descripcion, Image FROM servicios WHERE Activo = 1 ORDER BY Orden ASC, Titulo ASC";
$res = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($empresaNombre); ?> | Temas fiscales y contables</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    /* Redes sociales (footer) */
    .footer-social {
      display:flex; gap:12px; flex-wrap:wrap; justify-content:center; padding:14px 0 8px;
    }
    .social-btn {
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 12px; border-radius:999px; background:#fff; color:#0b3b5e;
      border:1px solid #e5e9ef; text-decoration:none; font-weight:600;
      transition:all .18s ease; box-shadow:0 6px 14px rgba(15,25,35,0.08);
    }
    .social-btn:hover { transform: translateY(-1px); }
    .social-btn .icon { width:18px; height:18px; display:inline-block; }
    .social-facebook:hover { border-color:#1877f2; background:#e8f0ff; }
    .social-instagram:hover { border-color:#e4405f; background:#fff0f3; }
    .social-twitter:hover { border-color:#000; background:#f2f2f2; }
    .social-telegram:hover { border-color:#26a5e4; background:#e9f7ff; }
    .social-youtube:hover { border-color:#ff0000; background:#fff0f0; }
    .social-linkedin:hover { border-color:#0a66c2; background:#eaf3ff; }
  </style>
</head>
<body>
  <header>
    <div class="header-container">
      <?php $miniLogoPath = __DIR__ . '/img/logo.png'; $miniLogoV = @file_exists($miniLogoPath) ? @filemtime($miniLogoPath) : null; ?>
  <img src="img/logo.png<?php echo $miniLogoV ? ('?v=' . $miniLogoV) : ''; ?>" alt="<?php echo htmlspecialchars($empresaNombre); ?>" class="logo">
      <nav>
        <ul>
          <li><a href="#inicio">Quienes somos</a></li>
          <li><a href="#servicios">Servicios</a></li>
          <li><a href="agendar.php">Agendar</a></li>
        </ul>
      </nav>
    </div>
  </header>
  <!-- Logo completo debajo del header -->
  
  <main>
    <section id="inicio" class="hero">
      <div class="hero-content">
        <div class="logo-full-container">
          <?php $logoV = @filemtime(__DIR__ . '/img/' . $logoFullFile) ?: null; ?>
          <img src="<?php echo 'img/' . htmlspecialchars($logoFullFile) . ($logoV ? ('?v=' . $logoV) : ''); ?>" alt="<?php echo htmlspecialchars($empresaNombre); ?>" class="logo-full">
        </div>
        <div class="hero-text">
          <h1>Tu tranquilidad legal y fiscal comienza en <strong><?php echo htmlspecialchars($empresaNombre); ?></strong></h1>
          <p>
            En <?php echo htmlspecialchars($empresaNombre); ?>, nuestro equipo de abogados y especialistas te acompaña en cada paso para resolver tus necesidades legales y fiscales. Ofrecemos asesoría y defensa en <strong>materia penal, civil, familiar, laboral y fiscal</strong>, brindando soluciones integrales y personalizadas para proteger tus intereses y patrimonio.<br><br>
            Agenda tu cita y recibe atención profesional en divorcios, herencias, contratos, despidos, defensa penal y consultoría fiscal. ¡Confía en nosotros para cuidar lo que más importa!
          </p>
          <div class="hero-buttons">
            <a href="agendar.php" class="btn btn-orange">Agendar Cita →</a>
          </div>
        </div>
        
      </div>
    </section>

    <section id="servicios" class="servicios">
      <h2 class="section-title">Servicios</h2>
      <div class="servicios-lista">
        <?php while($servicio = $res->fetch_assoc()):
          $serviceUrl = 'agendar.php?servicio_id=' . urlencode($servicio['Id']);
        ?>
          <?php $img = $servicio['Image']; $imgPath = __DIR__ . '/img/' . $img; $imgV = @file_exists($imgPath) ? @filemtime($imgPath) : null; ?>
          <div class="servicio-card" style="background-image: url('img/<?php echo htmlspecialchars($img); ?><?php echo $imgV ? ('?v=' . $imgV) : ''; ?>');">
            <span><?php echo htmlspecialchars($servicio['Titulo']); ?></span>
            <div class="servicio-info">
              <?php echo htmlspecialchars($servicio['Descripcion']); ?><br>
              <a href="<?php echo $serviceUrl; ?>" class="btn-agendar">Agendar</a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </section>

    <!-- Preguntas frecuentes -->
    <section id="preguntas" class="preguntas">
      <div class="container">
        <h2 class="section-title">Preguntas frecuentes</h2>
        <p class="lead">Respuestas rápidas a dudas comunes sobre nuestros servicios legales y fiscales.</p>

        <div class="preguntas-grid">
          <article class="pregunta-card" tabindex="0">
            <div class="pregunta-num">01</div>
            <h3 class="pregunta-titulo">¿Cómo empiezo como persona física o persona moral?</h3>
            <div class="pregunta-respuesta">
              Para iniciar debes definir tu régimen fiscal, obtener RFC y llevar contabilidad básica. Podemos asesorarte para elegir el régimen adecuado y registrar tu actividad ante el SAT.
            </div>
          </article>

          <article class="pregunta-card" tabindex="0">
            <div class="pregunta-num">02</div>
            <h3 class="pregunta-titulo">¿Qué documentos se necesitan para trámites fiscales?</h3>
            <div class="pregunta-respuesta">
              Identificación oficial, comprobante de domicilio, RFC, y documentos relacionados con tu actividad (contratos, facturas). Podemos revisar tu caso y preparar la lista exacta.
            </div>
          </article>

          <article class="pregunta-card" tabindex="0">
            <div class="pregunta-num">03</div>
            <h3 class="pregunta-titulo">¿Qué opciones tengo en caso de despido?</h3>
            <div class="pregunta-respuesta">
              Revisamos tu contrato y el motivo del despido: posible indemnización, finiquito, reinstalación o demanda laboral. Agenda una consulta para evaluación y acciones.
            </div>
          </article>

          <article class="pregunta-card" tabindex="0">
            <div class="pregunta-num">04</div>
            <h3 class="pregunta-titulo">¿Cómo procedo en un divorcio o asuntos familiares?</h3>
            <div class="pregunta-respuesta">
              Existen opciones de divorcio voluntario o contencioso, acuerdos de custodia y pensión. Evaluamos tu situación y proponemos la estrategia más conveniente.
            </div>
          </article>
          
        </div> <!-- .preguntas-grid -->

        <!-- CTA final en Preguntas: contacto / agendar -->
        <div class="preguntas-contacto" style="text-align:center; margin-top:28px;">
          <p style="font-size:1rem; color:var(--color-azul-oscuro); margin-bottom:12px;">
            ¿Tienes alguna otra duda o necesitas asesoría personalizada? Ponte en contacto con nosotros y agenda una cita.
          </p>
          <a href="agendar.php" class="btn btn-orange" style="padding:12px 22px; border-radius:999px; font-weight:700;">
            Contactar / Agendar cita →
          </a>
        </div>

      </div>
    </section>
    <!-- Puedes agregar más secciones aquí siguiendo el mismo formato -->
  </main>
  <footer>
    <div class="footer-bg">
      <?php
        // Construye links desde empresa->Redes
        $redes = function_exists('empresa_redes') ? empresa_redes(true) : [];
        $platforms = [
          'facebook'  => 'Facebook',
          'instagram' => 'Instagram',
          'telegram'  => 'Telegram',
          'twitter'   => 'Twitter',
          'youtube'   => 'YouTube',
          'linkedin'  => 'LinkedIn',
        ];
        // SVGs por plataforma (fill usa currentColor)
        $svgs = [
          'facebook'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.9v-7h-2.7V12h2.7V9.5c0-2.66 1.58-4.14 4-4.14 1.16 0 2.38.2 2.38.2v2.62h-1.34c-1.32 0-1.73.82-1.73 1.66V12h2.95l-.47 2.9h-2.48v7A10 10 0 0 0 22 12z"/></svg>',
          'instagram' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7zm5 3.2a5.8 5.8 0 1 1 0 11.6 5.8 5.8 0 0 1 0-11.6zm0 2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6zM18.6 6.4a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 0 1 0-2.8z"/></svg>',
          'twitter'   => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.46 6c-.77.34-1.6.57-2.46.67a4.28 4.28 0 0 0 1.88-2.37 8.6 8.6 0 0 1-2.72 1.04 4.26 4.26 0 0 0-7.27 3.29c0 .33.04.66.11.97A12.09 12.09 0 0 1 3 5.15a4.26 4.26 0 0 0 1.32 5.69 4.23 4.23 0 0 1-1.93-.53v.05c0 2.06 1.46 3.78 3.4 4.17-.36.1-.74.15-1.13.15-.28 0-.55-.03-.81-.08.55 1.72 2.15 2.98 4.05 3.01A8.55 8.55 0 0 1 2 19.54 12.06 12.06 0 0 0 8.29 21c7.55 0 11.68-6.26 11.68-11.68v-.53A8.34 8.34 0 0 0 22.46 6z"/></svg>',
          'telegram'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.03 15.6l-.35 5.06 2.6-2.78 5.72 4.1c.78.43 1.32.21 1.54-.69l3.31-15.52c.26-1.21-.42-1.72-1.29-1.39L1.95 9.39c-1.16.46-1.14 1.12-.21 1.4l5.38 1.67L19.6 4.69c.58-.39 1.1-.17.67.24L9.03 15.6z"/></svg>',
          'youtube'   => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2c-.2-.8-.9-1.5-1.7-1.7C18 5.2 12 5.2 12 5.2s-6 0-7.9.3c-.8.2-1.5.9-1.7 1.7C2.1 9.1 2.1 12 2.1 12s0 2.9.3 4.8c.2.8.9 1.5 1.7 1.7 1.9.3 7.9.3 7.9.3s6 0 7.9-.3c.8-.2 1.5-.9 1.7-1.7.3-1.9.3-4.8.3-4.8s0-2.9-.3-4.8zM10 15.5v-7l5.2 3.5L10 15.5z"/></svg>',
          'linkedin'  => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8h4v15h-4V8zm7.5 0h3.8v2.05h.05c.53-1 1.84-2.05 3.8-2.05 4.06 0 4.8 2.67 4.8 6.15V23h-4v-6.65c0-1.58-.03-3.62-2.2-3.62-2.2 0-2.54 1.72-2.54 3.5V23h-4V8z"/></svg>',
        ];
        $links = [];
        foreach ($platforms as $key => $label) {
          $url = $redes[$key] ?? '';
          if (is_string($url) && trim($url) !== '') {
            $url = trim($url);
            if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
            $links[] = ['key'=>$key, 'label'=>$label, 'url'=>$url];
          }
        }
      ?>
      <?php if (!empty($links)): ?>
        <div class="footer-social">
          <?php foreach ($links as $ln): ?>
            <a class="social-btn social-<?php echo htmlspecialchars($ln['key']); ?>"
               href="<?php echo htmlspecialchars($ln['url']); ?>"
               target="_blank" rel="noopener noreferrer"
               aria-label="<?php echo htmlspecialchars($ln['label']); ?>">
              <span class="icon" aria-hidden="true">
                <?php echo $svgs[$ln['key']] ?? ''; ?>
              </span>
              <span class="label"><?php echo htmlspecialchars($ln['label']); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($empresaNombre); ?>. Todos los derechos reservados.</p>
    </div>
  </footer>
  
  <script src="js/script.js"></script>
  <script src="js/preguntas-carousel.js"></script>
</body>
</html>
<?php
$conexion->close();
?>
