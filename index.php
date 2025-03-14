<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="User\css\index.css">

    
    
</head>
<body>
    <div class="content-wrapper">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-file-earmark-text me-2"></i>
                Barangay Services
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!--<li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>-->
                </ul>
                <div class="d-flex">
                    <a href="User/view/login.php" class="btn btn-light me-2">Login</a>
                    <a href="User/view/register.php" class="btn btn-outline-light">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">Online Barangay Document Request System</h1>
            <p class="lead mb-4">Brgy. Lanit Jaro, Iloilo City</p>
            <p class="lead mb-4">Simplifying document requests for our community members</p>
            <!--<div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <button type="button" class="btn btn-light btn-lg px-4 gap-3">Get Started</button>
                <button type="button" class="btn btn-outline-light btn-lg px-4">Learn More</button>
            </div>-->
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Our Services</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-file-earmark-check"></i>
                        </div>
                        <h3 class="fs-4">Barangay Clearance</h3>
                        <p class="text-muted">Request and receive your barangay clearance electronically. Fast and efficient processing.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="fs-4">Residency Certificate</h3>
                        <p class="text-muted">Apply for proof of residency certificates. Essential for various government transactions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 text-center">
                        <div class="feature-icon mb-3">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <h3 class="fs-4">Business Permit</h3>
                        <p class="text-muted">Apply for barangay business permits and track your application status online.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row align-items-center">
                <div class="col-md-6 order-md-2">
                    <!--<img src="#" alt="System process" class="img-fluid rounded shadow">-->
                </div>
                <div class="col-md-6 order-md-1">
                    <div class="d-flex mb-4">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; font-weight: bold;">1</div>
                        <div class="ms-3">
                            <h4>Create an Account</h4>
                            <p class="text-muted">Register using your valid ID and contact information.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; font-weight: bold;">2</div>
                        <div class="ms-3">
                            <h4>Submit Document Request</h4>
                            <p class="text-muted">Choose the document you need and provide required information.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; font-weight: bold;">3</div>
                        <div class="ms-3">
                            <h4>Track Your Request</h4>
                            <p class="text-muted">Monitor the status of your document request in real-time.</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; font-weight: bold;">4</div>
                        <div class="ms-3">
                            <h4>Receive Your Document</h4>
                            <p class="text-muted">Get notified when your document is ready for pickup or digital download.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card p-5 text-center">
                        <h2 class="mb-3">Ready to Get Started?</h2>
                        <p class="text-muted mb-4">Join hundreds of community members already using our system to request documents easily.</p>
                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                            <a href="User/view/register.php" class="btn btn-primary btn-lg px-4">Register Now</a>
                            <a href="User/view/login.php" class="btn btn-outline-secondary btn-lg px-4">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white">
        <div class="container">
            <div class="row">
                <p>&copy; 2025 Barangay Clearance and Document Request System</p>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-muted me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-muted me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-muted"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>