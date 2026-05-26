<?php
$url = 'http://127.0.0.1:8000/api/auth/login';
$data = json_encode([
    'email'    => 'test@test.com',
    'password' => '123456',
    'role'     => 'job',
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "cURL Error: $error\n";
echo "Response: $response\n";
