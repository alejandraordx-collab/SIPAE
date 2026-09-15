<?php
/**
 * procesar_usuario.php
 * Recibe el formulario de usuarios.php y crea una nueva cuenta de acceso
 * (docente o coordinador) en la tabla `usuarios`.
 *
 * La contraseña jamás se guarda en texto plano: password_hash() genera
 * un hash bcrypt (con una sal distinta cada vez), y login.php la valida
 * con password_verify().
 */

session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'coordinador') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$nombre     = trim($_POST['nombre'] ?? '');
$correo     = trim($_POST['correo'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');
$rol        = trim($_POST['rol'] ?? '');

// Validación básica: nombre y contraseña no vacíos, correo válido,
// contraseña de al menos 8 caracteres y rol dentro de los dos permitidos.
if (
    $nombre === '' || $contrasena === ''
    || !filter_var($correo, FILTER_VALIDATE_EMAIL)
    || strlen($contrasena) < 8
    || !in_array($rol, ['docente', 'coordinador'], true)
) {
    header('Location: usuarios.php?error=validacion');
    exit;
}

// password_hash() con PASSWORD_DEFAULT usa bcrypt —el mismo algoritmo que
// password_verify() espera en login.php— y genera una sal distinta en
// cada llamada, así que dos usuarios con la misma contraseña nunca
// terminan con el mismo hash guardado.
$hash = password_hash($contrasena, PASSWORD_DEFAULT);

$pdo = obtenerConexion();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, correo, contrasena, rol)
         VALUES (:nombre, :correo, :contrasena, :rol)'
    );
    $stmt->execute([
        ':nombre'     => $nombre,
        ':correo'     => $correo,
        ':contrasena' => $hash,
        ':rol'        => $rol,
    ]);

    header('Location: usuarios.php?guardado=1');

} catch (PDOException $e) {
    // Código 23000 = violación de restricción única (el correo ya existe,
    // gracias a la restricción uq_usr_correo definida en la base de datos).
    if ($e->getCode() === '23000') {
        header('Location: usuarios.php?error=duplicado');
    } else {
        error_log('[SIPAE] Error al crear usuario: ' . $e->getMessage());
        header('Location: usuarios.php?error=bd');
    }
}

exit;
