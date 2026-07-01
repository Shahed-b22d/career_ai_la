<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $service = app(\App\Services\AiCareerService::class);
    $result = $service->analyzeGap(
        'PHP developer with 3 years experience in Laravel and MySQL',
        'Laravel Developer'
    );
    echo "SUCCESS:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
