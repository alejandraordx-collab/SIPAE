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
require_once __DIR__ . '/libs/correo.php';

function generarContrasenaTemporal(int $longitud = 16): string
{
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    $maxIndex = strlen($caracteres) - 1;
    $resultado = '';

    for ($i = 0; $i < $longitud; $i++) {
        $resultado .= $caracteres[random_int(0, $maxIndex)];
    }

    return $resultado;
}

$nombre     = trim($_POST['nombre'] ?? '');
$correo     = trim($_POST['correo'] ?? '');
$rol        = trim($_POST['rol'] ?? '');

if (
    $nombre === ''
    || !filter_var($correo, FILTER_VALIDATE_EMAIL)
    || !in_array($rol, ['docente', 'coordinador'], true)
) {
    header('Location: usuarios.php?error=validacion');
    exit;
}

$temporal = generarContrasenaTemporal();
$hash = password_hash($temporal, PASSWORD_DEFAULT);

$pdo = obtenerConexion();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, correo, contrasena, rol, debe_cambiar_contrasena)
         VALUES (:nombre, :correo, :contrasena, :rol, 1)'
    );
    $stmt->execute([
        ':nombre'     => $nombre,
        ':correo'     => $correo,
        ':contrasena' => $hash,
        ':rol'        => $rol,
    ]);

    $correoEnviado = enviarCorreoCredencialTemporal(
        $correo,
        $nombre,
        $temporal
    );

    if ($correoEnviado) {
        header('Location: usuarios.php?guardado=1');
    } else {
        header('Location: usuarios.php?guardado=1&email_error=1');
    }

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
