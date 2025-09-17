<?php
$config = require __DIR__ . '/config.php';
$dbconf = $config['db'];
$conexion = new mysqli($dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name']);
if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}

// Consulta para obtener los servicios, incluyendo la imagen y el Id
$sql = "SELECT Id, Titulo, Descripcion, Image FROM servicios WHERE Activo = 1";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MEDLEX Despacho Jurídico | Temas fiscales y contables</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header>
    <div class="header-container">
      <img src="img/logo.png" alt="MEDLEX" class="logo">
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
          <img src="img/logofull.png" alt="MEDLEX Despacho Jurídico" class="logo-full">
        </div>
        <div class="hero-text">
          <h1>Tu tranquilidad legal y fiscal comienza en <strong>MEDLEX Despacho Jurídico</strong></h1>
          <p>
            En MEDLEX, nuestro equipo de abogados y especialistas te acompaña en cada paso para resolver tus necesidades legales y fiscales. Ofrecemos asesoría y defensa en <strong>materia penal, civil, familiar, laboral y fiscal</strong>, brindando soluciones integrales y personalizadas para proteger tus intereses y patrimonio.<br><br>
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
        <?php while($servicio = $resultado->fetch_assoc()):
          $serviceUrl = 'agendar.php?servicio_id=' . urlencode($servicio['Id']);
        ?>
          <div class="servicio-card" style="background-image: url('img/<?php echo htmlspecialchars($servicio['Image']); ?>');">
            <span><?php echo htmlspecialchars($servicio['Titulo']); ?></span>
            <div class="servicio-info">
              <?php echo htmlspecialchars($servicio['Descripcion']); ?><br>
              <a href="<?php echo $serviceUrl; ?>" class="btn-agendar">Agendar</a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </section>
    <!-- Puedes agregar más secciones aquí siguiendo el mismo formato -->
  </main>
  <footer>
    <div class="footer-bg">
      <p>© <?php echo date('Y'); ?> MEDLEX Despacho Jurídico. Todos los derechos reservados.</p>
    </div>
  </footer>
  
  <script src="js/script.js"></script>
</body>
</html>
<?php
$conexion->close();
?>
