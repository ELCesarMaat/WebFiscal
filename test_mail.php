<?php
// Script de prueba para verificar SMTP con PHPMailer
// Visita en el navegador: https://tu-dominio/test_mail.php (ajusta la ruta si está en subcarpeta)

require __DIR__.'/mailer.php';

$destino = isset($_GET['to']) ? trim($_GET['to']) : 'cesarmaat@hotmail.com'; // cambia en la URL ?to=tu@correo.com

$ok = send_app_mail([
    'to' => $destino,
    'subject' => 'Prueba SMTP MEDLEX '.date('H:i:s'),
    'text' => 'Prueba de envío SMTP (texto plano) '.date('Y-m-d H:i:s'),
    'html' => '<h2 style="font-family:Arial;">Prueba SMTP MEDLEX</h2><p style="font-family:Arial;">Este es un correo de prueba enviado a <b>'.htmlspecialchars($destino).'</b><br>Fecha: '.date('Y-m-d H:i:s').'</p>',
]);

header('Content-Type: text/plain; charset=UTF-8');
if ($ok) {
    echo "RESULTADO: OK\n";
} else {
    echo "RESULTADO: FALLO\n";
}
if (!empty($GLOBALS['MAIL_LAST_ERROR'])) {
    echo "DETALLE: ".$GLOBALS['MAIL_LAST_ERROR']."\n";
}
echo "Revisa email_log.txt para más detalles.\n";
