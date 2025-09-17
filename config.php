<?php
// Evita acceso directo desde navegador
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Forbidden');
}

return [
  'db' => [
    'host' => 'mysql.hostinger.com',
    'user' => 'u908748408_cesarmaat',
    'pass' => 'Cesar2211333',
    'name' => 'u908748408_webfiscal',
  ],
  'owner_email' => 'contacto@medlex.mx',
];
?>