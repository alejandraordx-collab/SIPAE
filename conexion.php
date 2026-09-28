<?php
/**
 * SIPAE - Sistema Integral de Permanencia, Asistencia y Alimentación Escolar
 * Archivo de conexión a la base de datos MySQL mediante PDO.
 * Stack: PHP + MySQL (Colegio OEA)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$secretsFile = __DIR__ . '/secrets.local.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'sipae');
$user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
$pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Si la conexión falla, se detiene con mensaje descriptivo
    die("Error al conectar con la base de datos: " . htmlspecialchars($e->getMessage()));
}

/**
 * Funciones de autenticación y control de acceso
 */
function estaAutenticado() {
    return isset($_SESSION['usuario_id']);
}

function requerirAutenticacion() {
    if (!estaAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

function requerirRol($rol) {
    requerirAutenticacion();
    if ($_SESSION['rol'] !== $rol) {
        if ($_SESSION['rol'] === 'coordinador') {
            header('Location: dashboard_coordinador.php');
        } else {
            header('Location: dashboard_docente.php');
        }
        exit;
    }
}
?>
