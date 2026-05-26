<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — CareerAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0052FF 0%, #6C63FF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #0052FF, #6C63FF);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        h1 { text-align: center; font-size: 22px; color: #1a1a2e; margin-bottom: 8px; }
        p  { text-align: center; color: #666; font-size: 14px; margin-bottom: 28px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
        input {
            width: 100%; padding: 14px 16px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px; font-size: 15px;
            font-family: 'Outfit', sans-serif;
            outline: none; transition: border-color .2s;
            margin-bottom: 16px;
        }
        input:focus { border-color: #0052FF; }
        button {
            width: 100%; padding: 15px;
            background: linear-gradient(135deg, #0052FF, #6C63FF);
            color: white; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer; transition: opacity .2s;
        }
        button:hover { opacity: .9; }
        .error {
            background: #fef2f2; border: 1px solid #fecaca;
            color: #dc2626; padding: 12px 16px;
            border-radius: 10px; font-size: 13px; margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">🔐</div>
        <h1>Set New Password</h1>
        <p>Enter your new password below to regain access to your CareerAI account.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/reset-password">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <label>New Password</label>
            <input type="password" name="password" placeholder="At least 6 characters" required>

            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" placeholder="Repeat your password" required>

            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>
</html>
