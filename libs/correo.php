<?php
/**
 * libs/correo.php
 * Helper de envío de correo por SMTP (PHPMailer), pensado para reutilizarse
 * en cualquier flujo del sistema que necesite enviar un correo (por ahora,
 * solo la recuperación de contraseña).
 *
 * A diferencia de enviar_alerta.php, este helper NUNCA guarda credenciales
 * en el código: las lee de variables de entorno del servidor. Si no están
 * configuradas, no intenta enviar nada (para no fallar a medias) y deja
 * constancia en el log del servidor — el llamador decide qué mostrarle al
 * usuario.
 *
 * Variables de entorno esperadas (configurables en cPanel → PHP → Variables
 * de entorno, o en el .conf de PHP-FPM/Apache del hosting):
 *   SIPAE_SMTP_HOST      (opcional, por defecto smtp.gmail.com)
 *   SIPAE_SMTP_PUERTO    (opcional, por defecto 587)
 *   SIPAE_SMTP_USUARIO   (obligatoria para poder enviar)
 *   SIPAE_SMTP_CLAVE     (obligatoria para poder enviar — contraseña de aplicación)
 *   SIPAE_SMTP_NOMBRE    (opcional, por defecto "SIPAE — Colegio OEA")
 */

/**
 * Envía un correo HTML (con alternativa en texto plano) usando PHPMailer.
 *
 * @return bool true si se envió correctamente, false si no se pudo enviar
 *              (incluye el caso de no tener credenciales configuradas).
 */
function enviarCorreoSMTP(string $destinatario, string $asunto, string $cuerpoHtml, string $cuerpoTexto): bool
{
    $smtpUsuario = getenv('SIPAE_SMTP_USUARIO') ?: '';
    $smtpClave   = getenv('SIPAE_SMTP_CLAVE')   ?: '';

    if ($smtpUsuario === '' || $smtpClave === '') {
        error_log('[SIPAE] Envío de correo omitido: SIPAE_SMTP_USUARIO / SIPAE_SMTP_CLAVE no están configuradas como variables de entorno.');
        return false;
    }

    $smtpHost   = getenv('SIPAE_SMTP_HOST')   ?: 'smtp.gmail.com';
    $smtpPuerto = (int) (getenv('SIPAE_SMTP_PUERTO') ?: 587);
    $smtpNombre = getenv('SIPAE_SMTP_NOMBRE') ?: 'SIPAE — Colegio OEA';

    $libPHPMailer = __DIR__ . '/PHPMailer/';

    if (!file_exists($libPHPMailer . 'PHPMailer.php')) {
        error_log('[SIPAE] Envío de correo omitido: PHPMailer no está instalado en ' . $libPHPMailer);
        return false;
    }

    require_once $libPHPMailer . 'Exception.php';
    require_once $libPHPMailer . 'PHPMailer.php';
    require_once $libPHPMailer . 'SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsuario;
        $mail->Password   = $smtpClave;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPuerto;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtpUsuario, $smtpNombre);
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;
        $mail->AltBody = $cuerpoTexto;

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[SIPAE] Error PHPMailer (recuperación de contraseña): ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Arma y envía el correo de recuperación de contraseña, con el diseño
 * visual de SIPAE (misma línea que enviar_alerta.php).
 */
function enviarCorreoCredencialTemporal(string $destinatario, string $nombre, string $contrasenaTemporal): bool
{
    $asunto = 'Tu contraseña temporal de SIPAE — Colegio OEA';
    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $fechaHoy = date('d/m/Y');

    $cuerpoHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">
        <tr>
          <td style="background:#ee7374;padding:28px 36px">
            <p style="margin:0;font-size:22px;font-weight:700;color:#fff">Colegio OEA</p>
            <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,.85)">SIPAE — Sistema Integral de Permanencia, Asistencia y Alimentación Escolar</p>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 36px">
            <p style="margin:0 0 16px;font-size:15px;color:#374151">Hola {$nombreSeguro},</p>
            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.6">
              Tu cuenta en SIPAE ha sido creada. Para ingresar por primera vez, usa la siguiente contraseña temporal:
            </p>
            <p style="margin:0 0 20px;padding:14px 18px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;font-size:22px;font-weight:700;color:#b91c1c;letter-spacing:1px;text-align:center">
              {$contrasenaTemporal}
            </p>
            <p style="margin:0;font-size:15px;color:#374151;line-height:1.6">
              Al iniciar sesión, el sistema te pedirá cambiarla de inmediato. Esta clave es de uso único y solo sirve para acceder la primera vez.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:18px 36px;border-top:1px solid #e5e7eb">
            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5">
              Este mensaje fue generado automáticamente por el sistema SIPAE del Colegio OEA.<br>
              © {$fechaHoy} Colegio OEA — Bogotá, Colombia.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $cuerpoTexto = "Hola {$nombre},\n\n"
        . "Tu cuenta en SIPAE ha sido creada. Usa la siguiente contraseña temporal para ingresar por primera vez:\n\n"
        . "{$contrasenaTemporal}\n\n"
        . "Al iniciar sesión, el sistema te pedirá cambiarla de inmediato.\n\n"
        . "— Sistema SIPAE";

    return enviarCorreoSMTP($destinatario, $asunto, $cuerpoHtml, $cuerpoTexto);
}

function enviarCorreoRecuperacion(string $destinatario, string $nombre, string $enlace): bool
{
    $asunto = 'Recuperación de contraseña — SIPAE Colegio OEA';

    $nombreSeguro  = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $enlaceSeguro  = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
    $fechaHoy      = date('d/m/Y');

    $cuerpoHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Segoe UI',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)">

        <tr>
          <td style="background:#ee7374;padding:28px 36px">
            <p style="margin:0;font-size:22px;font-weight:700;color:#fff">Colegio OEA</p>
            <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,.85)">
              SIPAE — Sistema Integral de Permanencia, Asistencia y Alimentación Escolar
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:28px 36px">
            <p style="margin:0 0 16px;font-size:15px;color:#374151">
              Hola {$nombreSeguro},
            </p>
            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.6">
              Recibimos una solicitud para restablecer la contraseña de tu cuenta en SIPAE.
              Si fuiste tú, haz clic en el siguiente botón para elegir una nueva contraseña.
              Este enlace es válido por <strong>1 hora</strong> y solo puede usarse una vez.
            </p>

            <p style="text-align:center;margin:24px 0">
              <a href="{$enlaceSeguro}"
                 style="background:#ee7374;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700">
                Restablecer contraseña
              </a>
            </p>

            <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6">
              Si tú no solicitaste este cambio, puedes ignorar este correo: tu contraseña
              actual seguirá funcionando con normalidad.
            </p>
          </td>
        </tr>

        <tr>
          <td style="background:#f9fafb;padding:18px 36px;border-top:1px solid #e5e7eb">
            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.5">
              Este mensaje fue generado automáticamente por el sistema SIPAE del Colegio OEA.
              Por favor no responda directamente a este correo.
              <br>© {$fechaHoy} Colegio OEA — Bogotá, Colombia.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $cuerpoTexto = "Hola {$nombre},\n\n"
        . "Recibimos una solicitud para restablecer tu contraseña en SIPAE.\n"
        . "Abre este enlace (válido por 1 hora, un solo uso) para elegir una nueva:\n\n"
        . "{$enlace}\n\n"
        . "Si tú no solicitaste este cambio, ignora este correo.\n\n"
        . "— Sistema SIPAE";

    return enviarCorreoSMTP($destinatario, $asunto, $cuerpoHtml, $cuerpoTexto);
}
