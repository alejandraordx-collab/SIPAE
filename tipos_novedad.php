<?php
/**
 * tipos_novedad.php
 * Lista blanca de tipos de novedad para asistencia.
 * Compartida por dashboard_docente.php (render del select) y
 * procesar_asistencia.php (validación server-side), para que ambos
 * archivos usen siempre la misma lista.
 */
return [
    'cita_medica'        => 'Cita médica',
    'problema_salud'     => 'Problema de salud',
    'calamidad_familiar' => 'Calamidad familiar',
    'transporte'         => 'Problemas de transporte',
    'incapacidad_medica' => 'Incapacidad médica',
    'disciplinaria'      => 'Situación disciplinaria',
    'permiso_previo'     => 'Permiso previamente solicitado',
    'sin_justificar'     => 'Inasistencia sin justificar',
    'otra'               => 'Otra novedad',
];
