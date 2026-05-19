<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f6f9fc;
            color: #30313d;
            display: flex;
            min-height: 100vh;
        }
        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            margin: auto;
            background: white;
            box-shadow: 0 50px 100px -20px rgba(50,50,93,0.12), 0 30px 60px -30px rgba(0,0,0,0.15);
            border-radius: 16px;
            overflow: hidden;
        }
        .left-panel {
            flex: 1;
            background-color: #ffffff;
            padding: 60px;
            border-right: 1px solid #e6ebf1;
            display: flex;
            flex-direction: column;
        }
        .right-panel {
            flex: 1.2;
            background-color: #ffffff;
            padding: 60px;
            display: flex;
            flex-direction: column;
        }
        .back-link {
            color: #635bff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            margin-bottom: 40px;
        }
        .back-link:hover {
            color: #0a2540;
        }
        .merchant-name {
            font-size: 16px;
            color: #697386;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .product-title {
            font-size: 32px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 24px;
        }
        .price {
            font-size: 40px;
            font-weight: 700;
            color: #0a2540;
            margin-bottom: 8px;
        }
        .price-label {
            font-size: 14px;
            color: #697386;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #e6ebf1;
            margin-bottom: 30px;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 600;
            color: #697386;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab.active {
            color: #635bff;
            border-bottom-color: #635bff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #4f5b66;
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d9e2ec;
            border-radius: 8px;
            font-size: 16px;
            color: #0a2540;
            background-color: #fcfdff;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #635bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(99,91,255,0.1);
        }
        .row-inputs {
            display: flex;
            gap: 16px;
        }
        .row-inputs .form-group {
            flex: 1;
        }
        .pay-btn {
            background-color: #635bff;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .pay-btn:hover {
            background-color: #0a2540;
        }
        .secure-badge {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            color: #697386;
            font-size: 13px;
            margin-top: 24px;
        }
        @media (max-width: 800px) {
            body {
                padding: 16px;
            }
            .container {
                flex-direction: column;
            }
            .left-panel, .right-panel {
                padding: 30px;
            }
            .left-panel {
                border-right: none;
                border-bottom: 1px solid #e6ebf1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Product Details -->
        <div class="left-panel">
            <a href="{{ $cancelUrl }}" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Cancel payment
            </a>
            <div class="merchant-name">CareerAI Inc.</div>
            <div class="product-title">Job Post Publishing</div>
            <div class="price">$25.00</div>
            <div class="price-label">Job Title: {{ $job->title }}</div>
        </div>

        <!-- Payment Details -->
        <div class="right-panel">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('card')">Credit Card</div>
                <div class="tab" onclick="switchTab('stc')">STC Pay</div>
            </div>

            <form action="{{ $successUrl }}" method="GET">
                <input type="hidden" name="job_id" value="{{ $job->id }}">
                
                <div id="card-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" placeholder="you@example.com" required value="{{ auth()->user()->email ?? '' }}">
                    </div>

                    <div class="form-group">
                        <label for="card-num">Card information</label>
                        <input type="text" id="card-num" placeholder="1234 5678 1234 5678" required>
                    </div>

                    <div class="row-inputs">
                        <div class="form-group">
                            <label for="card-exp">Expiry</label>
                            <input type="text" id="card-exp" placeholder="MM / YY" required>
                        </div>
                        <div class="form-group">
                            <label for="card-cvc">CVC</label>
                            <input type="text" id="card-cvc" placeholder="CVC" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="card-name">Name on card</label>
                        <input type="text" id="card-name" placeholder="John Doe" required value="{{ auth()->user()->name ?? '' }}">
                    </div>
                </div>

                <div id="stc-form" style="display: none;">
                    <div class="form-group">
                        <label for="phone">STC Pay Mobile Number</label>
                        <input type="tel" id="phone" placeholder="+966 50 000 0000">
                    </div>
                    <p style="font-size: 13px; color: #697386; margin-bottom: 20px;">You will receive an OTP code on your phone to complete payment.</p>
                </div>

                <button type="submit" class="pay-btn">
                    Pay $25.00
                </button>
            </form>

            <div class="secure-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #697386;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Secure payment powered by Stripe
            </div>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const tabs = document.querySelectorAll('.tab');
            const cardForm = document.getElementById('card-form');
            const stcForm = document.getElementById('stc-form');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            
            if (type === 'card') {
                tabs[0].classList.add('active');
                cardForm.style.display = 'block';
                stcForm.style.display = 'none';
                document.getElementById('card-num').required = true;
                document.getElementById('card-exp').required = true;
                document.getElementById('card-cvc').required = true;
                document.getElementById('card-name').required = true;
            } else {
                tabs[1].classList.add('active');
                cardForm.style.display = 'none';
                stcForm.style.display = 'block';
                document.getElementById('card-num').required = false;
                document.getElementById('card-exp').required = false;
                document.getElementById('card-cvc').required = false;
                document.getElementById('card-name').required = false;
            }
        }
    </script>
</body>
</html>
