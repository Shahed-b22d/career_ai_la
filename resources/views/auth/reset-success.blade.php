<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset — CareerAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0052FF 0%, #6C63FF 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .card {
            background: white; border-radius: 24px; padding: 48px 40px;
            width: 100%; max-width: 400px; text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { font-size: 24px; color: #1a1a2e; margin-bottom: 10px; }
        p  { color: #666; font-size: 15px; line-height: 1.6; margin-bottom: 28px; }
        .btn {
            display: inline-block; padding: 14px 32px;
            background: linear-gradient(135deg, #0052FF, #6C63FF);
            color: white; border-radius: 12px; text-decoration: none;
            font-size: 15px; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Password Reset!</h1>
        <p>Your password has been updated successfully. You can now log in to CareerAI with your new password.</p>
        <a href="#" onclick="window.close()" class="btn">Close & Open App</a>
    </div>
</body>
</html>
