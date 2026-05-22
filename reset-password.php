<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - HEWES BEJAIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Page de réinitialisation spécifique */
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-lg);
        }

        .reset-wrapper {
            width: 100%;
            max-width: 500px;
        }

        .reset-container {
            background: var(--white);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            background: var(--gradient-primary);
            color: var(--white);
            padding: var(--spacing-3xl) var(--spacing-2xl);
            text-align: center;
        }

        .reset-header i {
            font-size: 3.5rem;
            margin-bottom: var(--spacing-lg);
            display: block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .reset-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: var(--spacing-sm);
            font-weight: 700;
        }

        .reset-header p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .reset-body {
            padding: var(--spacing-3xl) var(--spacing-2xl);
        }

        .alert {
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-xl);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 2px solid var(--error-color);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 2px solid var(--success-color);
        }

        .alert i {
            font-size: 1.5rem;
        }

        .form-group {
            position: relative;
            margin-bottom: var(--spacing-xl);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: var(--spacing-lg) var(--spacing-lg) var(--spacing-lg) 3.5rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            transition: all var(--transition-normal);
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1);
        }

        .form-group i {
            position: absolute;
            left: var(--spacing-lg);
            bottom: var(--spacing-lg);
            color: var(--gray-400);
            font-size: 1.2rem;
        }

        .password-strength {
            margin-top: var(--spacing-md);
            height: 6px;
            background: var(--gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
            display: none;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all var(--transition-normal);
            border-radius: var(--radius-full);
        }

        .password-strength.weak .password-strength-bar {
            width: 33%;
            background: var(--error-color);
        }

        .password-strength.medium .password-strength-bar {
            width: 66%;
            background: var(--warning-color);
        }

        .password-strength.strong .password-strength-bar {
            width: 100%;
            background: var(--success-color);
        }

        .password-requirements {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-top: var(--spacing-md);
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
        }

        .password-requirements li {
            padding: var(--spacing-xs) 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .password-requirements li i {
            font-size: 0.9rem;
        }

        .password-requirements li.valid {
            color: var(--success-color);
        }

        .password-requirements li.valid i {
            color: var(--success-color);
        }

        .password-requirements li.invalid {
            color: var(--gray-400);
        }

        .password-requirements li.invalid i {
            color: var(--gray-400);
        }

        .submit-btn {
            width: 100%;
            background: var(--gradient-primary);
            color: var(--white);
            padding: var(--spacing-lg);
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            font-family: 'Inter', sans-serif;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            text-align: center;
            margin-top: var(--spacing-xl);
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-normal);
        }

        .back-link a:hover {
            gap: var(--spacing-md);
            color: var(--primary-dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .reset-header {
                padding: var(--spacing-2xl) var(--spacing-lg);
            }

            .reset-header h1 {
                font-size: 1.5rem;
            }

            .reset-header i {
                font-size: 3rem;
            }

            .reset-body {
                padding: var(--spacing-2xl) var(--spacing-lg);
            }
        }
    </style>
</head>
<body>
    <div class="reset-wrapper">
        <div class="reset-container">
            <div class="reset-header">
                <i class="fas fa-lock-open"></i>
                <h1>Nouveau mot de passe</h1>
                <p>Choisissez un mot de passe sécurisé</p>
            </div>

            <div class="reset-body">
                <div id="alertContainer"></div>

                <form id="resetPasswordForm">
                    <input type="hidden" id="token" name="token" value="<?php echo isset($_GET['token']) ? htmlspecialchars($_GET['token']) : ''; ?>">

                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Entrez votre nouveau mot de passe" required>
                        <div class="password-strength" id="passwordStrength">
                            <div class="password-strength-bar"></div>
                        </div>
                        <div class="password-requirements">
                            <ul id="requirements">
                                <li id="req-length" class="invalid">
                                    <i class="fas fa-times"></i> Au moins 6 caractères
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirmer le mot de passe</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirmez votre mot de passe" required>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-check"></i>
                        <span>Réinitialiser le mot de passe</span>
                    </button>
                </form>

                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i>
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('resetPasswordForm');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        const strengthIndicator = document.getElementById('passwordStrength');

        // Vérifier la force du mot de passe
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const lengthReq = document.getElementById('req-length');

            strengthIndicator.style.display = 'block';
            
            // Vérifier la longueur
            if (password.length >= 6) {
                lengthReq.classList.remove('invalid');
                lengthReq.classList.add('valid');
                lengthReq.querySelector('i').className = 'fas fa-check';
            } else {
                lengthReq.classList.remove('valid');
                lengthReq.classList.add('invalid');
                lengthReq.querySelector('i').className = 'fas fa-times';
            }

            // Afficher la force
            if (password.length < 6) {
                strengthIndicator.className = 'password-strength weak';
            } else if (password.length < 10) {
                strengthIndicator.className = 'password-strength medium';
            } else {
                strengthIndicator.className = 'password-strength strong';
            }
        });

        // Soumettre le formulaire
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const token = document.getElementById('token').value;
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Validation
            if (!token) {
                showAlert('Token manquant. Le lien est invalide.', 'error');
                return;
            }

            if (password.length < 6) {
                showAlert('Le mot de passe doit contenir au moins 6 caractères', 'error');
                return;
            }

            if (password !== confirmPassword) {
                showAlert('Les mots de passe ne correspondent pas', 'error');
                return;
            }

            // Désactiver le bouton
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Réinitialisation...</span>';

            try {
                const response = await fetch('./api/reset_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✓ Mot de passe réinitialisé avec succès ! Redirection...', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    showAlert(data.error || 'Erreur lors de la réinitialisation', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> <span>Réinitialiser le mot de passe</span>';
                }
            } catch (error) {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion au serveur', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check"></i> <span>Réinitialiser le mot de passe</span>';
            }
        });

        function showAlert(message, type) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
        }
    </script>
</body>
</html>