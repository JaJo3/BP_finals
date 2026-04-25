<?php
// JWT Debug Script - Decode token to see payload
// Usage: php decode_jwt.php "eyJhbG..."

if ($argc < 2) {
    echo "Usage: php decode_jwt.php <jwt_token>\n";
    exit(1);
}

$token = $argv[1];
$parts = explode('.', $token);

if (count($parts) !== 3) {
    echo "Invalid JWT format - should have 3 parts\n";
    exit(1);
}

// Decode header
$header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
echo "=== HEADER ===\n";
print_r($header);

// Decode payload
$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
echo "\n=== PAYLOAD ===\n";
print_r($payload);

echo "\n=== KEY INFO ===\n";
if (isset($payload['sub'])) {
    echo "User Identifier (sub): " . $payload['sub'] . "\n";
} else {
    echo "WARNING: No 'sub' claim found!\n";
}

if (isset($payload['email'])) {
    echo "Email claim: " . $payload['email'] . "\n";
} else {
    echo "No 'email' claim in token\n";
}

if (isset($payload['exp'])) {
    $expired = $payload['exp'] < time();
    echo "Token expires: " . date('Y-m-d H:i:s', $payload['exp']) . " (" . ($expired ? "EXPIRED" : "valid") . ")\n";
}
