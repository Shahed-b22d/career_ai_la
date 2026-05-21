<?php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Services\AiCareerService;

$service = new AiCareerService();

echo "=== Testing analyzeGap ===\n";
try {
    $result = $service->analyzeGap("Experienced Flutter Developer with 3 years of experience. Knowledge of Dart, Git, Firebase. Looking to learn CI/CD and unit testing.", "Senior Flutter Engineer");
    echo "Success!\n";
    print_r($result);
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}

echo "\n=== Testing generateQuiz ===\n";
try {
    $result = $service->generateQuiz(["CI/CD", "Flutter Unit Testing"]);
    echo "Success!\n";
    print_r($result);
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}

echo "\n=== Testing generateAtsCv ===\n";
try {
    $result = $service->generateAtsCv("Experienced Flutter Developer with 3 years of experience. Knowledge of Dart, Git, Firebase.", ["CI/CD", "Unit Testing"], "Name: Test User\nEmail: test@example.com\nPhone: 123456\nLocation: Damascus");
    echo "Success! Length of HTML: " . strlen($result) . "\n";
    echo substr($result, 0, 500) . "...\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
