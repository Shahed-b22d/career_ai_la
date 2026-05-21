<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Services\AiCareerService;

$service = new AiCareerService();
$userData = "Summary: Experienced developer with 3 years of experience.
Skills: Dart, Git, Firebase.
Experience: Mobile App Developer at TechCorp (2023-present). Developed 5 apps.
Education: BS in Computer Science, Damascus University (2020-2023).";

$personalInfo = "Name: John Doe\nEmail: john@example.com\nPhone: +963999999999\nLocation: Damascus";

$html = $service->generateAtsCv($userData, ["CI/CD", "Unit Testing"], $personalInfo);

file_put_contents('ats_cv_test_output.html', $html);
echo "Saved HTML to ats_cv_test_output.html. Length: " . strlen($html) . "\n";
