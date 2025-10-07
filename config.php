<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Evita acceso directo desde navegador
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Forbidden');
}

return [
  'db' => [
    'host' => 'localhost',
    'user' => 'u382854808_proteccion',
    'pass' => 'J0lxllcBv',
    'name' => 'u382854808_juridica',
  ],
  'owner_email' => 'contacto@medlex.mx',
  // Configuración SMTP para PHPMailer (rellenar con tus datos reales)
  'smtp' => [
    'enabled' => true,                // true para usar SMTP
    'host' => 'smtp.hostinger.com',   // host SMTP típico en Hostinger
    'user' => 'contacto@proteccionjuridica.mx', // CREA este buzón en hPanel o usa el existente
    'pass' => 'Puma1876@',   // reemplaza por la contraseña real del buzón
    'port' => 587,                    // 587 (TLS) o 465 (SSL)
    'secure' => 'tls',                // 'tls' o 'ssl'
    'auth' => true,                   // requerir autenticación
    'from_address' => 'contacto@proteccionjuridica.mx', // mantener igual que user para evitar rechazos
    'from_name' => 'Proteccion Juridica',
    'allow_fallback' => false,        // evita mail() si falla SMTP (más claro en errores)
  ],
];
// return [
//   'db' => [
//     'host' => 'localhost',
//     'user' => 'root',
//     'pass' => '',
//     'name' => 'webfiscal',
//   ],
//   'owner_email' => 'contacto@medlex.mx',
//   // Configuración SMTP para PHPMailer (rellenar con tus datos reales)
//   'smtp' => [
//     'enabled' => true,                // true para usar SMTP
//     'host' => 'smtp.hostinger.com',   // host SMTP típico en Hostinger
//     'user' => 'yo@cesarmaat.com', // CREA este buzón en hPanel o usa el existente
//     'pass' => 'Michi2214.',   // reemplaza por la contraseña real del buzón
//     'port' => 587,                    // 587 (TLS) o 465 (SSL)
//     'secure' => 'tls',                // 'tls' o 'ssl'
//     'auth' => true,                   // requerir autenticación
//     'from_address' => 'yo@cesarmaat.com', // mantener igual que user para evitar rechazos
//     'from_name' => 'MEDLEX',
//     'allow_fallback' => false,        // evita mail() si falla SMTP (más claro en errores)
//   ],
// ];
?>