try {
    $body = '{"email":"ahmad@test.com","password":"password123","role":"job"}'
    $headers = @{
        'Content-Type' = 'application/json'
        'Accept'       = 'application/json'
    }
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/auth/login' `
         -Method POST -Headers $headers -Body $body -UseBasicParsing
    Write-Host "✅ Server is RUNNING"
    Write-Host "Status: $($r.StatusCode)"
    Write-Host "Response: $($r.Content)"
} catch {
    $code = $_.Exception.Response.StatusCode.value__
    if ($code) {
        Write-Host "✅ Server is RUNNING (got HTTP $code)"
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        Write-Host "Response: $($reader.ReadToEnd())"
    } else {
        Write-Host "❌ Server is NOT running or unreachable"
        Write-Host "Error: $($_.Exception.Message)"
    }
}
