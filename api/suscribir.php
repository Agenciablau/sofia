<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://sofiarenas.es');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ── CONFIGURACIÓN ──────────────────────────────────────────
$BREVO_API_KEY = 'TU_BREVO_API_KEY_AQUI';
$LISTA_ID      = 3;
$PDF_URL       = 'https://sofiarenas.es/giua.pdf';
$REMITENTE     = ['name' => 'Sofía Arenas', 'email' => 'hi@sofiarenas.es'];
// ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$nombre = htmlspecialchars(trim($body['nombre'] ?? ''));
$email  = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email || !$nombre) {
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

// 1. Añadir contacto a Brevo
$contactPayload = json_encode([
    'email'         => $email,
    'attributes'    => ['FIRSTNAME' => $nombre],
    'listIds'       => [$LISTA_ID],
    'updateEnabled' => true
]);

$ch = curl_init('https://api.brevo.com/v3/contacts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $contactPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'api-key: ' . $BREVO_API_KEY
    ]
]);
$contactRes  = curl_exec($ch);
$contactCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 2. Enviar email con enlace al PDF
$emailPayload = json_encode([
    'sender'      => $REMITENTE,
    'to'          => [['email' => $email, 'name' => $nombre]],
    'subject'     => 'Tu guía "Descifra tu apego" está aquí',
    'htmlContent' => "
        <div style='font-family:sans-serif;max-width:560px;margin:0 auto;color:#0F0E0C;padding:32px 24px;'>
          <p style='font-size:20px;font-weight:700;margin-bottom:8px;'>Hola {$nombre},</p>
          <p style='font-size:15px;line-height:1.7;color:#8A8278;margin-bottom:28px;'>
            Gracias por descargar la guía. Aquí la tienes:
          </p>
          <a href='{$PDF_URL}'
             style='display:inline-block;padding:14px 32px;background:#7C6BB5;color:#fff;
                    text-decoration:none;font-size:14px;letter-spacing:.08em;'>
            Descargar — Descifra tu apego
          </a>
          <p style='font-size:12px;color:#8A8278;margin-top:20px;line-height:1.7;'>
            Si el botón no funciona copia este enlace:<br>
            <a href='{$PDF_URL}' style='color:#7C6BB5;'>{$PDF_URL}</a>
          </p>
          <hr style='border:none;border-top:1px solid #EDE7D9;margin:32px 0;'>
          <p style='font-size:12px;color:#C4BEB4;'>
            Sofía Arenas · @sofiarenas.psi<br>
            <a href='https://sofiarenas.es' style='color:#C4BEB4;'>sofiarenas.es</a>
          </p>
        </div>
    "
]);

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $emailPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'api-key: ' . $BREVO_API_KEY
    ]
]);
$emailRes  = curl_exec($ch);
$emailCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($emailCode === 201) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Error al enviar el email', 'detail' => $emailRes]);
}
?>
