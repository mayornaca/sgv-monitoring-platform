/**
 * SGV - AJAX Login System
 * Migrated from legacy jQuery to modern Vanilla JS with Fetch API
 * Features: AJAX login, progress bar, spinner, notifications
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeLoginForm();
        initializeFloatingLabels();
    });

    /**
     * Initialize the login form with AJAX submission
     */
    function initializeLoginForm() {
        const form = document.getElementById('form_login');
        const submitButton = document.getElementById('btn-ajax-login');

        if (!form || !submitButton) {
            return;
        }

        // Handle Enter key submission
        form.addEventListener('keyup', function(event) {
            if (event.keyCode === 13) {
                event.preventDefault();
                submitButton.click();
            }
        });

        // Handle button click
        submitButton.addEventListener('click', function(e) {
            e.stopPropagation();
            handleLogin();
        });
    }

    /**
     * Initialize Material Design floating labels
     */
    function initializeFloatingLabels() {
        const floatingInputs = document.querySelectorAll('.form-material.floating input');

        floatingInputs.forEach(function(input) {
            // Add 'filled' class if input has value on page load
            if (input.value && input.value.length > 0) {
                input.classList.add('filled');
            }

            // Update on input change
            input.addEventListener('input', function() {
                if (this.value && this.value.length > 0) {
                    this.classList.add('filled');
                } else {
                    this.classList.remove('filled');
                }
            });

            // Update on blur
            input.addEventListener('blur', function() {
                if (this.value && this.value.length > 0) {
                    this.classList.add('filled');
                }
            });
        });
    }

    /**
     * Handle the login process via AJAX
     */
    function handleLogin() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const rememberMe = document.getElementById('remember_me').checked;
        const csrfToken = document.querySelector('input[name="_csrf_token"]').value;

        // Validate inputs
        if (!username || username.length === 0 || !password || password.length === 0) {
            showNotification('Ingrese su usuario y su contraseña para iniciar sesión.', 'error');
            return;
        }

        // Show loading indicators
        showLoadingBar();
        showButtonSpinner();

        // Prepare form data
        const formData = new FormData();
        formData.append('_username', username);
        formData.append('_password', password);
        formData.append('_csrf_token', csrfToken);
        if (rememberMe) {
            formData.append('_remember_me', 'on');
        }

        // Incluir token reCAPTCHA si está presente
        const recaptchaResponse = document.querySelector('[name="g-recaptcha-response"]');
        if (recaptchaResponse && recaptchaResponse.value) {
            formData.append('g-recaptcha-response', recaptchaResponse.value);
        }

        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                // Login failed
                hideLoadingBar();
                hideButtonSpinner();
                showNotification(data.error, 'error');
            } else if (data.login_ok) {
                // Login successful
                showNotification('Validando inicio de sesión, espere mientras lo redireccionamos.', 'success');

                // Redirect after short delay
                setTimeout(function() {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.href = '/admin';
                    }
                }, 1250);
            }
        })
        .catch(error => {
            hideLoadingBar();
            hideButtonSpinner();
            console.error('Login error:', error);
            showNotification('Ha ocurrido un error al intentar procesar los datos.', 'error');
        });
    }

    /**
     * Show the progress bar at the top
     */
    function showLoadingBar() {
        const loader = document.querySelector('.loader-css-bar');
        if (loader) {
            loader.style.display = 'block';
        }
    }

    /**
     * Hide the progress bar
     */
    function hideLoadingBar() {
        const loader = document.querySelector('.loader-css-bar');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    /**
     * Show spinner inside the button
     */
    function showButtonSpinner() {
        const spinner = document.querySelector('.has-spinner');
        const button = document.getElementById('btn-ajax-login');
        if (spinner) {
            spinner.style.display = 'inline-block';
        }
        if (button) {
            button.classList.add('hover');
            button.disabled = true;
        }
    }

    /**
     * Hide spinner inside the button
     */
    function hideButtonSpinner() {
        const spinner = document.querySelector('.has-spinner');
        const button = document.getElementById('btn-ajax-login');
        if (spinner) {
            spinner.style.display = 'none';
        }
        if (button) {
            button.classList.remove('hover');
            button.disabled = false;
        }
    }

    /**
     * Show notification message
     * @param {string} message - The message to display
     * @param {string} type - Type: 'success', 'error', 'warning', 'info'
     */
    function showNotification(message, type) {
        // If notify.js is available, use it
        if (typeof notify !== 'undefined') {
            const form = document.getElementById('form_login');
            if (form && form.notify) {
                form.notify(message, {
                    style: 'bootstrap',
                    className: type,
                    position: 'top'
                });
                return;
            }
        }

        // Fallback: Create a simple notification div
        createSimpleNotification(message, type);
    }

    /**
     * Create a simple notification without external libraries
     * @param {string} message
     * @param {string} type
     */
    function createSimpleNotification(message, type) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.auth-notification');
        existing.forEach(el => el.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `auth-notification auth-notification-${type}`;
        notification.textContent = message;

        // Add styles
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '4px',
            boxShadow: '0 4px 6px rgba(0,0,0,0.1)',
            zIndex: '10000',
            maxWidth: '400px',
            animation: 'slideInRight 0.3s ease-out',
            fontFamily: 'Muli, sans-serif',
            fontSize: '14px'
        });

        // Set colors based on type
        if (type === 'success') {
            notification.style.backgroundColor = '#d1fae5';
            notification.style.color = '#065f46';
            notification.style.border = '1px solid #a7f3d0';
        } else if (type === 'error') {
            notification.style.backgroundColor = '#fee2e2';
            notification.style.color = '#991b1b';
            notification.style.border = '1px solid #fecaca';
        } else if (type === 'warning') {
            notification.style.backgroundColor = '#fef3c7';
            notification.style.color = '#92400e';
            notification.style.border = '1px solid #fde68a';
        } else {
            notification.style.backgroundColor = '#dbeafe';
            notification.style.color = '#1e40af';
            notification.style.border = '1px solid #bfdbfe';
        }

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(function() {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 5000);
    }

    // Add CSS animation for notifications
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

})();
