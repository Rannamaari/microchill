<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load environment variables
function loadEnv($file) {
    if (!file_exists($file)) {
        return false;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
    return true;
}

loadEnv(__DIR__ . '/.env');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $formData = json_decode($input, true);

    if (!$formData) {
        throw new Exception('Invalid JSON data');
    }

    // Verify reCAPTCHA
    $recaptchaSecret = $_ENV['RECAPTCHA_SECRET_KEY'];
    $recaptchaToken = $formData['recaptchaToken'] ?? '';

    $recaptchaResponse = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
            'secret' => $recaptchaSecret,
            'response' => $recaptchaToken
        ])
    );

    $recaptchaResult = json_decode($recaptchaResponse, true);

    if (!$recaptchaResult['success']) {
        http_response_code(400);
        echo json_encode(['error' => 'reCAPTCHA verification failed']);
        exit;
    }

    // Build address from components
    $addressParts = array_filter([
        $formData['house'] ?? '',
        $formData['road'] ?? '',
        $formData['area'] ?? '',
        !empty($formData['floor']) ? 'Floor: ' . $formData['floor'] : ''
    ]);
    $address = implode(', ', $addressParts);

    // Create Telegram message
    $message = "🆕 New Booking Request from MicroCool Website\n\n" .
        "👤 Name: " . ($formData['name'] ?? '') . "\n" .
        "📞 Phone: " . ($formData['phone'] ?? '') . "\n" .
        "📧 Email: " . ($formData['email'] ?? 'Not provided') . "\n" .
        "📍 Address: " . $address . "\n" .
        "📅 Date: " . ($formData['date'] ?? '') . "\n" .
        "⏰ Time Slot: " . ($formData['timeSlot'] ?? 'Anytime') . "\n" .
        "🔧 Service: " . ($formData['service'] ?? '') . "\n" .
        "❄️ AC Brand: " . ($formData['brand'] ?? 'Not specified') . "\n" .
        "📊 BTU: " . ($formData['btu'] ?? 'Not specified') . "\n" .
        "📝 Notes: " . ($formData['notes'] ?? 'None') . "\n" .
        "🛡️ Verified: ✅ Human verified (reCAPTCHA passed)";

    // Send to Telegram
    $telegramBotToken = $_ENV['TELEGRAM_BOT_TOKEN'];
    $telegramChatId = $_ENV['TELEGRAM_CHAT_ID'];

    $telegramUrl = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";

    $ch = curl_init($telegramUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'chat_id' => $telegramChatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $telegramResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to send Telegram message');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Booking request sent successfully'
    ]);

} catch (Exception $e) {
    error_log('Booking error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'Failed to process booking request'
    ]);
}
