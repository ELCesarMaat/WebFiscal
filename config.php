<?php
// Evita acceso directo desde navegador
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  exit('Forbidden');
}

return [
  'db' => [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'webfiscal',
  ],
  'owner_email' => 'contacto@medlex.mx',
];
?>