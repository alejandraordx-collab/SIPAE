<?php
/**
 * procesar_editar_usuario.php
 * Recibe el formulario de editar_usuario.php y actualiza una cuenta de
 * usuario ya existente: nombre, correo, rol y, opcionalmente, una nueva
 * contraseña (por ejemplo, cuando la persona la olvidó).
 *
 * Si el campo "contrasena" llega vacío, la contraseña guardada NO se
 * modifica. Si llega con texto, se valida y se guarda con password_hash(),
 * igual que en procesar_usuario.php.
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

$id         = (int) ($_POST['id'] ?? 0);
$nombre     = trim($_POST['nombre'] ?? '');
$correo     = trim($_POST['correo'] ?? '');
$rol        = trim($_POST['rol'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');

$esUsuarioActual = $id === (int) $_SESSION['usuario_id'];

// Validación básica: nombre y correo obligatorios, correo válido, rol
// dentro de los dos permitidos, y si escribieron contraseña nueva,
// que tenga al menos 8 caracteres.
if (
    $id <= 0
    || $nombre === ''
    || !filter_var($correo, FILTER_VALIDATE_EMAIL)
    || !in_array($rol, ['docente', 'coordinador'], true)
    || ($contrasena !== '' && strlen($contrasena) < 8)
) {
    header('Location: editar_usuario.php?id=' . $id . '&error=validacion');
    exit;
}

// Evita que un coordinador se quite a sí mismo el rol mientras tiene la
// sesión abierta (el formulario ya bloquea este campo para su propia
// cuenta, pero se valida también aquí por seguridad).
if ($esUsuarioActual && $rol !== 'coordinador') {
    header('Location: editar_usuario.php?id=' . $id . '&error=auto_rol');
    exit;
}

$pdo = obtenerConexion();

try {
    if ($contrasena !== '') {
        // password_hash() con PASSWORD_DEFAULT usa bcrypt, igual que en
        // procesar_usuario.php, así que login.php sigue validando con
        // password_verify() sin cambios.
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'UPDATE usuarios
                SET nombre = :nombre, correo = :correo, rol = :rol, contrasena = :contrasena
              WHERE id = :id'
        );
        $stmt->execute([
            ':nombre'     => $nombre,
            ':correo'     => $correo,
            ':rol'        => $rol,
            ':contrasena' => $hash,
            ':id'         => $id,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE usuarios
                SET nombre = :nombre, correo = :correo, rol = :rol
              WHERE id = :id'
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':rol'    => $rol,
            ':id'     => $id,
        ]);
    }

    // Si el coordinador se editó a sí mismo, refrescamos el nombre en la
    // sesión para que se vea actualizado de inmediato en la interfaz.
    if ($esUsuarioActual) {
        $_SESSION['nombre'] = $nombre;
    }

    header('Location: usuarios.php?editado=1');

} catch (PDOException $e) {
    // Código 23000 = violación de restricción única (el correo ya
    // pertenece a otro usuario).
    if ($e->getCode() === '23000') {
        header('Location: editar_usuario.php?id=' . $id . '&error=duplicado');
    } else {
        error_log('[SIPAE] Error al editar usuario: ' . $e->getMessage());
        header('Location: editar_usuario.php?id=' . $id . '&error=bd');
    }
}

exit;
