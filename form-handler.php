<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// === LOG INICIAL ===
file_put_contents("/tmp/php_debug.log", date("Y-m-d H:i:s") . " | Script iniciado\n", FILE_APPEND);

// === CARGAR .ENV MANUALMENTE ===
$envPath = __DIR__ . '/../.env'; // .env un nivel arriba de /2025
if (file_exists($envPath)) {
    $vars = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($vars !== false) {
        foreach ($vars as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// Variables desde .env
$brevoApiKey     = getenv('BREVO_API_KEY') ?: '';
$listId          = getenv('BREVO_LIST_ID') ?: '';
$recaptchaSecret = getenv('RECAPTCHA_SECRET') ?: '';

// === CAPTURAR DATOS DEL POST ===
$nombre    = $_POST['NOMBRE'] ?? '';
$apellidos = $_POST['APELLIDOS'] ?? '';
$email     = $_POST['EMAIL'] ?? '';
$empresa   = $_POST['EMPRESA'] ?? '';
$smsCode   = $_POST['SMS_COUNTRY_CODE'] ?? '';
$smsNum    = $_POST['SMS'] ?? '';
$consulta  = $_POST['CONSULTA'] ?? '';
$captcha   = $_POST['captchaToken'] ?? '';

// === LOG POST DATA ===
file_put_contents(
    "/tmp/php_debug.log",
    date("Y-m-d H:i:s") . " | POST: " . json_encode($_POST) . "\n",
    FILE_APPEND
);

// Validar que llegue captcha
if ($captcha === '') {
    echo json_encode(["success" => false, "error" => "No se recibió captchaToken"]);
    exit;
}

// Validar reCAPTCHA
$captchaVerify = @file_get_contents(
    "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($recaptchaSecret) .
    "&response=" . urlencode($captcha)
);

if ($captchaVerify === false) {
    echo json_encode(["success" => false, "error" => "No se pudo conectar con reCAPTCHA"]);
    exit;
}

$captchaData = json_decode($captchaVerify, true);

if (!$captchaData || !$captchaData['success']) {
    echo json_encode(["success" => false, "error" => "Captcha inválido"]);
    exit;
}

// Preparar payload para Brevo
$payload = [
    "updateEnabled" => true,
    "email" => $email,
    "attributes" => [
        "FIRSTNAME" => $nombre,
        "LASTNAME"  => $apellidos,
        "COMPANY"   => $empresa,
        "SMS"       => $smsCode . $smsNum,
        "CONSULTA"  => $consulta
    ],
    "listIds" => [(int)$listId]
];

// Enviar a Brevo
$ch = curl_init("https://api.brevo.com/v3/contacts");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "api-key: $brevoApiKey"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(["success" => false, "error" => "cURL error: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// === LOG BREVO RESPONSE ===
file_put_contents(
    "/tmp/brevo_debug.log",
    date("Y-m-d H:i:s") . " | HTTP: $httpCode | Respuesta: $response\n",
    FILE_APPEND
);

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $response]);
}
