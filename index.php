<?php
session_start();

if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die("config.php not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeachFinder | Learn & Teach Better</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --tf-blue: #4a90e2; }
        body { font-family: 'Inter', sans-serif; }
        .navbar { padding: 15px 0; }
        .hero-section { padding: 120px 0; background: linear-gradient(135deg, #4a90e2, #67b26f); color: white; text-align: center; }
        .feature-icon { font-size: 2.5rem; color: var(--tf-blue); margin-bottom: 20px; }
        .btn-primary { background: #4a90e2; border: none; padding: 12px 30px; }
        .btn-outline-light { border-width: 2px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="frontpage.php">
                <i class="fas fa-graduation-cap text-primary"></i> TeachFinder
            </a>
            <div class="d-flex">
                <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                <a href="signup.php" class="btn btn-primary">Signup</a>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container">
            <h1 class="display-3 fw-bold">Master New Skills</h1>
            <p class="lead mb-4">Connect with expert teachers or find the perfect students today.</p>
            <div class="mt-4">
                <a href="signup.php" class="btn btn-light btn-lg px-4 me-2 text-primary fw-bold">Get Started</a>
            </div>
        </div>
    </header>

    <div class="container py-5">
        <div class="row text-center mt-4">
            <div class="col-md-6 mb-4">
                <div class="feature-icon"><i class="fas fa-user-graduate"></i></div>
                <h3 class="fw-bold">For Students</h3>
                <p class="text-muted">Browse thousands of qualified tutors, view profiles, and book your first lesson with ease.</p>
            </div>
            <div class="col-md-6 mb-4">
                <div class="feature-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3 class="fw-bold">For Teachers</h3>
                <p class="text-muted">Create your professional profile, set your own rates, and manage your student bookings effortlessly.</p>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 text-center">
        <div class="container">
            <p class="mb-0">&copy; 2026 TeachFinder. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
