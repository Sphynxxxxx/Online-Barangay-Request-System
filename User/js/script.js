// Script to handle the "Send Code" button
document.getElementById('sendCodeBtn').addEventListener('click', function() {
    const email = document.getElementById('email').value;
    const loading = document.getElementById('loading');
    const btnText = document.getElementById('btnText');
    const alertBox = document.getElementById('emailAlert');
    
    if (email === '') {
        alert('Please enter your email address first');
        return;
    }
    
    // Email validation using regex
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        return;
    }
    
    // Show loading state
    if (loading) loading.style.display = 'inline-block';
    if (btnText) btnText.textContent = 'Sending...';
    this.disabled = true;
    
    // Generate a random 6-digit code
    const code = Math.floor(100000 + Math.random() * 900000);
    
    // Send email using fetch API
    fetch('../../backend/connections/send_email_verification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: email,
            code: code
        })
    })
    .then(response => response.json())
    .then(data => {
        if (loading) loading.style.display = 'none';
        
        if (data.success) {
            // Store the code in sessionStorage for verification with expiration time (30 minutes)
            const expirationTime = Date.now() + (30 * 60 * 1000);
            sessionStorage.setItem('verificationCode', code);
            sessionStorage.setItem('verificationEmail', email);
            sessionStorage.setItem('verificationExpires', expirationTime.toString());
            
            saveCodeToSession(email, code);
            
            // Start the countdown timer
            startVerificationCountdown();
            
            alertBox.innerHTML = 'Verification code sent to your email. Please check your inbox.';
            alertBox.classList.remove('alert-danger');
            alertBox.classList.add('alert-success');
            alertBox.style.display = 'block';
            
            if (btnText) btnText.textContent = 'Resend Code';
        } else {
            alertBox.innerHTML = 'Error: ' + (data.error || 'Failed to send code');
            alertBox.classList.remove('alert-success');
            alertBox.classList.add('alert-danger');
            alertBox.style.display = 'block';
            
            if (btnText) btnText.textContent = 'Try Again';
        }
        this.disabled = false;
    })
    .catch(error => {
        if (loading) loading.style.display = 'none';
        
        alertBox.innerHTML = 'Error connecting to server. Please try again.';
        alertBox.classList.remove('alert-success');
        alertBox.classList.add('alert-danger');
        alertBox.style.display = 'block';
        
        if (btnText) btnText.textContent = 'Try Again';
        this.disabled = false;
        console.error('Error:', error);
    });
});

// Function to save verification code to PHP session
function saveCodeToSession(email, code) {
    fetch('../../backend/connections/save_verification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: email,
            code: code
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Code saved to session:', data.success);
    })
    .catch(error => {
        console.error('Error saving code to session:', error);
    });
}

// Function to start countdown timer for verification code
function startVerificationCountdown() {
    // Clear any existing countdown element
    const existingCountdown = document.getElementById('verification-countdown');
    if (existingCountdown) {
        existingCountdown.remove();
    }
    
    // Clear any existing interval
    if (window.countdownInterval) {
        clearInterval(window.countdownInterval);
    }
    
    // Create countdown element
    const countdownElement = document.createElement('div');
    countdownElement.id = 'verification-countdown';
    countdownElement.className = 'mt-2 small text-muted';
    
    // Append it after the verification code input
    const verificationInputParent = document.getElementById('verificationCode').closest('.col-md-6');
    verificationInputParent.appendChild(countdownElement);
    
    // Calculate end time
    const expirationTime = parseInt(sessionStorage.getItem('verificationExpires'));
    
    // Start the countdown
    window.countdownInterval = setInterval(function() {
        const currentTime = Date.now();
        const timeLeft = Math.round((expirationTime - currentTime) / 1000);
        
        if (timeLeft <= 0) {
            // Code has expired
            clearInterval(window.countdownInterval);
            countdownElement.textContent = 'Verification code has expired. Please request a new one.';
            countdownElement.className = 'mt-2 small text-danger';
            
            // Clear the verification data
            sessionStorage.removeItem('verificationCode');
            sessionStorage.removeItem('verificationEmail');
            sessionStorage.removeItem('verificationExpires');
            
            // Clear the server-side session data too
            fetch('clear_verification.php', {
                method: 'POST'
            });
        } else {
            // Format and display the time left
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `Code expires in: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        }
    }, 1000);
}

// Check if we need to restore the countdown timer when page loads
window.addEventListener('DOMContentLoaded', function() {
    const expirationTime = sessionStorage.getItem('verificationExpires');
    if (expirationTime) {
        const currentTime = Date.now();
        const timeLeft = Math.round((parseInt(expirationTime) - currentTime) / 1000);
        
        if (timeLeft > 0) {
            // There's still time left, start the countdown
            startVerificationCountdown();
        } else {
            // Code has expired, clear the data
            sessionStorage.removeItem('verificationCode');
            sessionStorage.removeItem('verificationEmail');
            sessionStorage.removeItem('verificationExpires');
            
            // Clear server-side session too
            fetch('clear_verification.php', {
                method: 'POST'
            });
        }
    }
});

// Password validation
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const hasMinLength = password.length >= 8;
    const hasLetter = /[a-zA-Z]/.test(password);
    const hasNumber = /\d/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    // Update password strength indicator
    if (hasMinLength && hasLetter && hasNumber && hasSpecial) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.classList.remove('is-valid');
        // Don't add 'is-invalid' here to avoid showing the error too early
    }
});

// Confirm password validation
document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password === confirmPassword) {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});

// Check verification code from sessionStorage on form submission
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const verificationCode = document.getElementById('verificationCode').value;
    const email = document.getElementById('email').value;
    
    // Get stored verification data
    const storedCode = sessionStorage.getItem('verificationCode');
    const storedEmail = sessionStorage.getItem('verificationEmail');
    const expirationTime = sessionStorage.getItem('verificationExpires');
    
    if (!storedCode || !storedEmail || !expirationTime) {
        e.preventDefault();
        alert('No valid verification code found. Please request a verification code.');
        return false;
    }
    
    // Check if the code matches
    if (storedCode !== verificationCode || storedEmail !== email) {
        e.preventDefault();
        alert('Invalid verification code or email does not match the one used for verification.');
        return false;
    }
    
    // Check if the code has expired
    const currentTime = Date.now();
    if (currentTime > parseInt(expirationTime)) {
        e.preventDefault();
        alert('Verification code has expired. Please request a new code.');
        
        // Clear the expired verification data
        sessionStorage.removeItem('verificationCode');
        sessionStorage.removeItem('verificationEmail');
        sessionStorage.removeItem('verificationExpires');
        
        return false;
    }
});