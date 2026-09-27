<?php
/**
 * SIPAE - Sistema de Información para la Programación y Almuerzo Escolar
 * Colegio OEA - Institución Educativa Distrital
 * 
 * Punto de entrada principal (PHP Nativo + MySQL)
 */
require_once __DIR__ . '/conexion.php';

// Si el usuario ya tiene sesión activa, redirigir a su panel correspondiente
if (isset($_SESSION['usuario_id']) && isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'coordinador') {
        header('Location: dashboard_coordinador.php');
        exit;
    } else {
        header('Location: dashboard_docente.php');
        exit;
    }
}

// Si no ha iniciado sesión, redirigir al login
header('Location: login.php');
exit;
