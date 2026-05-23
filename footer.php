
<footer class="mt-auto pt-4 pb-3">
    <div class="container-fluid">
        <hr class="text-secondary opacity-25 mb-4">
        <div class="row align-items-center">
            <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                <p class="text-muted small mb-0">
                    &copy; <?= date('Y') ?> <span class="fw-bold text-dark">BD Technology</span>. 
                    <span class="d-none d-sm-inline">All Rights Reserved.</span>
                </p>
            </div>

            <div class="col-md-4 text-center mb-3 mb-md-0">
                <div class="d-inline-flex align-items-center bg-white px-3 py-1 rounded-pill shadow-sm border">
                    <span class="status-dot pulse me-2"></span>
                    <small class="fw-bold text-secondary" style="font-size: 11px;">
                        SYSTEM SECURE & ACTIVE
                    </small>
                </div>
            </div>

            <div class="col-md-4 text-center text-md-end">
                <div class="footer-links">
                    <span class="badge bg-light text-dark border me-2" style="font-size: 10px;">
                        <i class="fas fa-code-branch me-1 text-primary"></i> v3.0.4
                    </span>
                    <a href="support.php" class="text-decoration-none small text-primary fw-semibold">
                        <i class="fas fa-headset me-1"></i> Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Footerকে নিচে ধরে রাখার জন্য */
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    .container-fluid.row {
        flex: 1;
    }

    /* স্ট্যাটাস ডট এনিমেশন */
    .status-dot {
        height: 8px;
        width: 8px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
    }

    .pulse {
        animation: pulse-animation 2s infinite;
    }

    @keyframes pulse-animation {
        0% { box-shadow: 0 0 0 0px rgba(16, 185, 129, 0.7); }
        100% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    }

    .footer-links a:hover {
        color: #0369a1 !important;
        text-decoration: underline !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>



