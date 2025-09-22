<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// === CARGAR .ENV MANUALMENTE ===
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $vars = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($vars !== false) {
        foreach ($vars as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// Variables desde .env
$brevoApiKey = getenv('BREVO_API_KEY') ?: '';
$listId      = getenv('BREVO_LIST_ID') ?: '';

// === CAPTURAR DATOS DEL POST ===
$nombre    = $_POST['NOMBRE'] ?? '';
$apellidos = $_POST['APELLIDOS'] ?? '';
$email     = $_POST['EMAIL'] ?? '';
$empresa   = $_POST['EMPRESA'] ?? '';
$smsCode   = $_POST['SMS_COUNTRY_CODE'] ?? '';
$smsNum    = $_POST['SMS'] ?? '';
$consulta  = $_POST['CONSULTA'] ?? '';

// === Validar email requerido por Brevo ===
if (empty($email)) {
    echo json_encode([
        "success" => false,
        "error"   => "El campo EMAIL es obligatorio"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// === Preparar payload para Brevo ===
$payload = [
    "updateEnabled" => true,
    "email" => $email,
    "attributes" => [
        "NOMBRE"    => $nombre,
        "APELLIDOS" => $apellidos,
        "EMPRESA"   => $empresa,
        "SMS"       => $smsCode . $smsNum,
        "WHATSAPP"  => $smsCode . $smsNum,
        "CONSULTA"  => $consulta
    ],
    "listIds" => [(int)$listId]
];

// print_r($payload);

// === Enviar a Brevo ===
$ch = curl_init("https://api.brevo.com/v3/contacts");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "api-key: $brevoApiKey"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
// echo "resultado: ";
// print_r($response);
// echo 'fin resultado';

if ($response === false) {
    echo json_encode([
        "success" => false,
        "error"   => "cURL error: " . curl_error($ch)
    ], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// === Decodificar la respuesta de Brevo para inspección ===
$decoded = json_decode($response, true);

// Si no se puede decodificar, devolver crudo
if ($decoded === null) {
    echo json_encode([
        "success" => false,
        "http"    => $httpCode,
        "error"   => $response
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Si Brevo responde 2xx pero incluye datos de contacto válidos
if ($httpCode >= 200 && $httpCode < 300) {
    if (isset($decoded['id']) || isset($decoded['email'])) {
        echo json_encode([
            "success" => true,
            "brevo"   => $decoded
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "success" => false,
            "error"   => $decoded ?: $response
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        "success" => false,
        "http"    => $httpCode,
        "error"   => $decoded ?: $response
    ], JSON_UNESCAPED_UNICODE);
}
