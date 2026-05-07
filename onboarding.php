<?php
session_start();

/* ===== HANDLE LET'S START ===== */
if (isset($_POST['start'])) {
    $_SESSION['seen_onboarding'] = true;
    header("Location: index.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to EcoHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            margin: 0;
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .onboarding-container {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 18px;
            padding: 30px 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            text-align: center;
        }

        .slide {
            display: none;
        }

        .slide.active {
            display: block;
            animation: fade 0.4s ease-in-out;
        }

        @keyframes fade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            margin-bottom: 10px;
            color: #2e7d32;
        }

        p {
            color: #555;
            font-size: 15px;
            line-height: 1.6;
        }

        ul {
            text-align: left;
            padding-left: 20px;
            color: #555;
            font-size: 15px;
        }

        ul li {
            margin-bottom: 8px;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .btn {
            margin-top: 25px;
            width: 100%;
            padding: 12px;
            background: #2e7d32;
            color: #fff;
            border: none;
            border-radius: 30px;
            font-size: 15px;
            cursor: pointer;
        }

        .btn:hover {
            background: #256428;
        }

        .progress {
            margin-top: 20px;
            font-size: 13px;
            color: #999;
        }
    </style>
</head>

<body>

<div class="onboarding-container">

    <!-- Slide 1 -->
    <div class="slide active">
        <div class="icon">🌱</div>
        <h2>Welcome to EcoHub</h2>
        <p>
            EcoHub is a sustainable sharing platform that allows users
            to rent and lend eco-friendly items instead of buying new ones.
        </p>
        <button class="btn" onclick="nextSlide()">Next</button>
        <div class="progress">1 / 4</div>
    </div>

    <!-- Slide 2 -->
    <div class="slide">
        <div class="icon">🔄</div>
        <h2>Borrow or Lend Items</h2>
        <p>
            Browse available items, send rental requests,
            or list your own items to help others.
        </p>
        <button class="btn" onclick="nextSlide()">Next</button>
        <div class="progress">2 / 4</div>
    </div>

    <!-- Slide 3 -->
    <div class="slide">
        <div class="icon">🌍</div>
        <h2>Why EcoHub?</h2>
        <ul>
            <li>Reduce environmental waste</li>
            <li>Save money through sharing</li>
            <li>Support a sustainable community</li>
        </ul>
        <button class="btn" onclick="nextSlide()">Next</button>
        <div class="progress">3 / 4</div>
    </div>

    <!-- Slide 4 -->
    <div class="slide">
        <div class="icon">🚀</div>
        <h2>Ready to Get Started?</h2>
        <p>
            Join EcoHub today and be part of a more sustainable future.
        </p>

        <form method="post">
            <button name="start" class="btn">Let’s Start</button>
        </form>

        <div class="progress">4 / 4</div>
    </div>

</div>

<script>
    let current = 0;
    const slides = document.querySelectorAll('.slide');

    function nextSlide() {
        slides[current].classList.remove('active');
        current++;
        slides[current].classList.add('active');
    }
</script>

</body>
</html>

<?php
if (isset($_POST['start'])) {
    $_SESSION['seen_onboarding'] = true;
    header("Location:index.php");
    exit;
}
?>
