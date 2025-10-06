<?php
// inc/empresa.php - obtiene datos de la tabla empresa (la activa) y expone $empresa y helpers
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  // si no hay conexión previa, crear una temporal propia y cerrarla al final
  $config = require __DIR__ . '/../config.php';
  $dbconf = $config['db'];
  $___tmp_conn = new mysqli($dbconf['host'], $dbconf['user'], $dbconf['pass'], $dbconf['name']);
  if ($___tmp_conn->connect_error) {
    die('Error de conexión: ' . $___tmp_conn->connect_error);
  }
  $___close_after = true;
  $___conn = $___tmp_conn;
} else {
  $___conn = $conexion;
  $___close_after = false;
}

$empresa = [];
$resEmp = $___conn->query("SELECT Id, Nombre, Telefono, Email, correo_administrador, Direccion, Horario, HorarioDetalle, Redes, Logo, logo_full FROM empresa WHERE Activo = 1 LIMIT 1");
if ($resEmp && $rowEmp = $resEmp->fetch_assoc()) {
  $empresa = $rowEmp;
}
if ($resEmp) $resEmp->free();

// Helpers
function empresa_nombre($fallback = 'MEDLEX Despacho Jurídico') {
  global $empresa; return !empty($empresa['Nombre']) ? $empresa['Nombre'] : $fallback;
}
function empresa_logo_full_filename($fallback = 'logofull.png') {
  global $empresa; if (!empty($empresa['logo_full'])) return $empresa['logo_full']; if (!empty($empresa['Logo'])) return $empresa['Logo']; return $fallback;
}
function empresa_logo_filename($fallback = 'logo.png') {
  global $empresa; return !empty($empresa['Logo']) ? $empresa['Logo'] : $fallback;
}
function empresa_email_admin($fallback = 'contacto@medlex.mx') {
  global $empresa; return $empresa['correo_administrador'] ?? ($empresa['Email'] ?? $fallback);
}

// === Nuevos helpers (faltantes) ===
function empresa_email($fallback = 'contacto@medlex.mx') {
  global $empresa; return !empty($empresa['Email']) ? $empresa['Email'] : $fallback;
}
function empresa_telefono($fallback = '') {
  global $empresa; return !empty($empresa['Telefono']) ? $empresa['Telefono'] : $fallback;
}
function empresa_direccion($fallback = '') {
  global $empresa; return !empty($empresa['Direccion']) ? $empresa['Direccion'] : $fallback;
}
function empresa_horario($fallback = '') {
  global $empresa; return !empty($empresa['Horario']) ? $empresa['Horario'] : $fallback;
}
function empresa_horario_detalle($fallback = '') {
  global $empresa; return !empty($empresa['HorarioDetalle']) ? $empresa['HorarioDetalle'] : $fallback;
}
function empresa_redes(): array {
  global $empresa;
  if (empty($empresa['Redes'])) return [];
  $r = json_decode($empresa['Redes'], true);
  return is_array($r) ? $r : [];
}

if (!empty($___close_after)) { $___conn->close(); }
