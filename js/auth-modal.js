// Modal d'authentification unifiée avec Mot de passe oublié
class AuthModal {
    constructor() {
        this.isOpen = false;
        this.modal = null;
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.createModal();
                this.bindEvents();
            });
        } else {
            this.createModal();
            this.bindEvents();
        }
    }

    createModal() {
        const modalHTML = `
            <div id="authModal" class="auth-modal">
                <div class="auth-backdrop"></div>
                <div class="auth-content">
                    <div class="auth-header">
                        <h2 id="authTitle">Connexion</h2>
                        <button class="close-btn" onclick="authModal.closeModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="auth-body">
                        <!-- Onglets -->
                        <div class="auth-tabs">
                            <button class="auth-tab active" data-tab="login">
                                <i class="fas fa-sign-in-alt"></i>
                                Connexion
                            </button>
                            <button class="auth-tab" data-tab="register">
                                <i class="fas fa-user-plus"></i>
                                Inscription
                            </button>
                        </div>

                        <!-- Formulaire de connexion -->
                        <form id="loginForm" class="auth-form active">
                            <div class="form-group">
                                <input type="text" id="loginEmail" placeholder="Email ou nom d'utilisateur" required autocomplete="username">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="form-group">
                                <input type="password" id="loginPassword" placeholder="Mot de passe" required autocomplete="current-password">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="forgot-password-link">
                                <a href="#" onclick="authModal.showForgotPassword(); return false;">
                                    <i class="fas fa-question-circle"></i>
                                    Mot de passe oublié ?
                                </a>
                            </div>
                            <button type="submit" class="auth-submit-btn">
                                <i class="fas fa-sign-in-alt"></i>
                                Se connecter
                            </button>
                            <p class="auth-hint">
                                <i class="fas fa-info-circle"></i>
                                Vous êtes les bienvenus à Béjaïa 
                            </p>
                        </form>

                        <!-- Formulaire d'inscription -->
                        <form id="registerForm" class="auth-form">
                            <div class="form-group">
                                <input type="text" id="registerName" placeholder="Nom complet" required autocomplete="name">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="form-group">
                                <input type="email" id="registerEmail" placeholder="Email" required autocomplete="email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="form-group">
                                <input type="tel" id="registerPhone" placeholder="Téléphone (+213...)" required autocomplete="tel">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="form-group">
                                <input type="password" id="registerPassword" placeholder="Mot de passe (min. 6 caractères)" required autocomplete="new-password">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="form-group">
                                <input type="password" id="confirmPassword" placeholder="Confirmer le mot de passe" required autocomplete="new-password">
                                <i class="fas fa-lock"></i>
                            </div>
                            <button type="submit" class="auth-submit-btn">
                                <i class="fas fa-user-plus"></i>
                                S'inscrire
                            </button>
                        </form>

                        <!-- Formulaire mot de passe oublié -->
                        <form id="forgotPasswordForm" class="auth-form">
                            <div class="forgot-password-info">
                                <i class="fas fa-info-circle"></i>
                                <p>Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
                            </div>
                            <div class="form-group">
                                <input type="email" id="forgotEmail" placeholder="Votre email" required autocomplete="email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <button type="submit" class="auth-submit-btn">
                                <i class="fas fa-paper-plane"></i>
                                Envoyer le lien
                            </button>
                            <div class="back-to-login">
                                <a href="#" onclick="authModal.showLogin(); return false;">
                                    <i class="fas fa-arrow-left"></i>
                                    Retour à la connexion
                                </a>
                            </div>
                        </form>

                        <div class="auth-footer">
                            <p>Besoin d'aide ? <a href="https://wa.me/213775654995" target="_blank">Contactez-nous sur WhatsApp</a></p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('authModal');
        this.addAuthStyles();
    }

    addAuthStyles() {
        const styles = `
            .auth-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }

            .auth-modal.active {
                display: flex;
                animation: fadeIn 0.3s ease;
            }

            .auth-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(5px);
            }

            .auth-content {
                position: relative;
                background: white;
                border-radius: 1.5rem;
                max-width: 450px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
                animation: slideUp 0.3s ease;
            }

            .auth-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 2rem 2rem 1rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .auth-header h2 {
                color: #1f2937;
                font-size: 1.5rem;
                font-weight: 600;
            }

            .close-btn {
                background: #f3f4f6;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s;
            }

            .close-btn:hover {
                background: #e5e7eb;
                transform: rotate(90deg);
            }

            .auth-body {
                padding: 2rem;
            }

            .auth-tabs {
                display: flex;
                background: #f3f4f6;
                border-radius: 0.75rem;
                padding: 0.25rem;
                margin-bottom: 2rem;
            }

            .auth-tab {
                flex: 1;
                background: transparent;
                border: none;
                padding: 0.75rem;
                border-radius: 0.5rem;
                font-weight: 500;
                color: #6b7280;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .auth-tab.active {
                background: white;
                color: #0ea5e9;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .auth-form {
                display: none;
            }

            .auth-form.active {
                display: block;
            }

            .form-group {
                position: relative;
                margin-bottom: 1.5rem;
            }

            .form-group input {
                width: 100%;
                padding: 1rem 1rem 1rem 3rem;
                border: 2px solid #e5e7eb;
                border-radius: 0.75rem;
                font-size: 1rem;
                transition: all 0.3s;
            }

            .form-group input:focus {
                outline: none;
                border-color: #0ea5e9;
                box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            }

            .form-group i {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #9ca3af;
                font-size: 1.1rem;
            }

            .forgot-password-link {
                text-align: right;
                margin-bottom: 1rem;
                margin-top: -0.5rem;
            }

            .forgot-password-link a {
                color: #0ea5e9;
                text-decoration: none;
                font-size: 0.9em;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-weight: 500;
            }

            .forgot-password-link a:hover {
                text-decoration: underline;
            }

            .forgot-password-info {
                background: #eff6ff;
                border-left: 4px solid #0ea5e9;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 8px;
                display: flex;
                gap: 12px;
                align-items: start;
            }

            .forgot-password-info i {
                color: #0ea5e9;
                font-size: 1.3em;
                margin-top: 2px;
            }

            .forgot-password-info p {
                color: #1e3a8a;
                font-size: 0.9em;
                line-height: 1.5;
                margin: 0;
            }

            .back-to-login {
                text-align: center;
                margin-top: 15px;
            }

            .back-to-login a {
                color: #6b7280;
                text-decoration: none;
                font-size: 0.9em;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .back-to-login a:hover {
                color: #0ea5e9;
            }

            .auth-submit-btn {
                width: 100%;
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
                color: white;
                padding: 1rem;
                border: none;
                border-radius: 0.75rem;
                font-size: 1.1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .auth-submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(14, 165, 233, 0.3);
            }

            .auth-submit-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }

            .auth-hint {
                text-align: center;
                color: #6b7280;
                font-size: 0.85rem;
                margin: 1rem 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .auth-hint i {
                color: #0ea5e9;
            }

            .auth-footer {
                text-align: center;
                padding-top: 1rem;
                border-top: 1px solid #e5e7eb;
            }

            .auth-footer p {
                color: #6b7280;
                font-size: 0.9rem;
            }

            .auth-footer a {
                color: #0ea5e9;
                text-decoration: none;
                font-weight: 500;
            }

            .auth-footer a:hover {
                text-decoration: underline;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            @media (max-width: 768px) {
                .auth-content {
                    margin: 1rem;
                    max-height: 95vh;
                }

                .auth-body {
                    padding: 1.5rem;
                }
            }
        `;

        if (!document.querySelector('#auth-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'auth-styles';
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        }
    }

    bindEvents() {
        setTimeout(() => {
            // Onglets
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabType = e.currentTarget.dataset.tab;
                    this.switchTab(tabType);
                });
            });

            // Formulaire de connexion
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleLogin();
                });
            }

            // Formulaire d'inscription
            const registerForm = document.getElementById('registerForm');
            if (registerForm) {
                registerForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleRegister();
                });
            }

            // Formulaire mot de passe oublié
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            if (forgotPasswordForm) {
                forgotPasswordForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleForgotPassword();
                });
            }

            // Fermer avec Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.closeModal();
                }
            });

            // Fermer en cliquant sur le backdrop
            const backdrop = document.querySelector('.auth-backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', () => {
                    this.closeModal();
                });
            }
        }, 100);
    }

    switchTab(tabType) {
        // Cacher les onglets si on affiche le formulaire de mot de passe oublié
        const tabs = document.querySelector('.auth-tabs');
        if (tabType === 'forgot') {
            tabs.style.display = 'none';
            document.getElementById('authTitle').textContent = 'Mot de passe oublié';
        } else {
            tabs.style.display = 'flex';
            document.getElementById('authTitle').textContent = tabType === 'login' ? 'Connexion' : 'Inscription';
            
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.tab === tabType);
            });
        }

        document.querySelectorAll('.auth-form').forEach(form => {
            form.classList.remove('active');
        });
        
        if (tabType === 'forgot') {
            document.getElementById('forgotPasswordForm').classList.add('active');
        } else {
            document.getElementById(`${tabType}Form`).classList.add('active');
        }
    }

    showForgotPassword() {
        this.switchTab('forgot');
    }

    showLogin() {
        this.switchTab('login');
    }

    openModal() {
        if (this.modal) {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.isOpen = true;
        }
    }

    closeModal() {
        if (this.modal) {
            this.modal.classList.remove('active');
            document.body.style.overflow = '';
            this.isOpen = false;
            this.resetForms();
            this.switchTab('login'); // Revenir à l'onglet connexion
        }
    }

    resetForms() {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        if (loginForm) loginForm.reset();
        if (registerForm) registerForm.reset();
        if (forgotPasswordForm) forgotPasswordForm.reset();
    }

    async handleLogin() {
        const emailInput = document.getElementById('loginEmail');
        const passwordInput = document.getElementById('loginPassword');
        
        if (!emailInput || !passwordInput) {
            this.showNotification('Erreur de formulaire', 'error');
            return;
        }

        const identifier = emailInput.value.trim();
        const password = passwordInput.value;
        
        if (!identifier || !password) {
            this.showNotification('Veuillez remplir tous les champs', 'error');
            return;
        }

        const submitBtn = document.querySelector('#loginForm .auth-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';

        try {
            // Essayer connexion admin
            const adminResponse = await fetch('./api/admin_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: identifier,
                    password: password
                })
            });
            
            const adminData = await adminResponse.json();
            
            if (adminData.success) {
                this.showNotification('Connexion administrateur réussie !', 'success');
                setTimeout(() => {
                    window.location.href = 'admin/index.php';
                }, 1000);
                return;
            }

            // Essayer connexion client
            const clientResponse = await fetch('./api/client_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: identifier,
                    password: password
                })
            });
            
            const clientData = await clientResponse.json();
            
            if (clientData.success) {
                this.showNotification('Connexion réussie !', 'success');
                
                // Vérifier s'il y a une redirection en attente
                const redirectUrl = sessionStorage.getItem('redirectAfterLogin');
                
                setTimeout(() => {
                    if (redirectUrl) {
                        sessionStorage.removeItem('redirectAfterLogin');
                        window.location.href = redirectUrl;
                    } else {
                        window.location.reload();
                    }
                }, 1000);
                return;
            }

            this.showNotification('Identifiants incorrects', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Se connecter';

        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Erreur de connexion au serveur', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Se connecter';
        }
    }

    async handleRegister() {
        const name = document.getElementById('registerName').value.trim();
        const email = document.getElementById('registerEmail').value.trim();
        const phone = document.getElementById('registerPhone').value.trim();
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (!name || !email || !phone || !password || !confirmPassword) {
            this.showNotification('Veuillez remplir tous les champs', 'error');
            return;
        }

        if (password !== confirmPassword) {
            this.showNotification('Les mots de passe ne correspondent pas', 'error');
            return;
        }

        if (password.length < 6) {
            this.showNotification('Le mot de passe doit contenir au moins 6 caractères', 'error');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showNotification('Email invalide', 'error');
            return;
        }

        const submitBtn = document.querySelector('#registerForm .auth-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inscription...';

        try {
            const response = await fetch('./api/client_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    email: email,
                    phone: phone,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('✉️ Inscription réussie ! Vérifiez votre email pour activer votre compte.', 'success');
                setTimeout(() => {
                    this.switchTab('login');
                }, 3000);
            } else {
                this.showNotification(data.error || 'Erreur lors de l\'inscription', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Erreur de connexion au serveur', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> S\'inscrire';
        }
    }

    async handleForgotPassword() {
        const emailInput = document.getElementById('forgotEmail');
        const email = emailInput.value.trim();

        if (!email) {
            this.showNotification('Veuillez entrer votre email', 'error');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showNotification('Format d\'email invalide', 'error');
            return;
        }

        const submitBtn = document.querySelector('#forgotPasswordForm .auth-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

        try {
            const response = await fetch('./api/forgot_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('✉️ Email envoyé ! Vérifiez votre boîte de réception.', 'success');
                setTimeout(() => {
                    this.showLogin();
                }, 3000);
            } else {
                this.showNotification(data.error || 'Erreur lors de l\'envoi', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Erreur de connexion au serveur', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le lien';
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        else if (type === 'error') icon = 'exclamation-circle';
        
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
        `;
        
        const styles = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                color: white;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                z-index: 99999;
                animation: slideInRight 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-width: 400px;
            }
            .notification-success {
                background: linear-gradient(135deg, #10b981, #059669);
            }
            .notification-error {
                background: linear-gradient(135deg, #ef4444, #dc2626);
            }
            .notification-info {
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
            }
            @keyframes slideInRight {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        
        if (!document.querySelector('#notification-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'notification-styles';
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        }
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}

// Initialiser le modal
let authModal;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        authModal = new AuthModal();
    });
} else {
    authModal = new AuthModal();
}

// Fonction globale pour ouvrir le modal
function openAuthModal() {
    if (authModal) {
        authModal.openModal();
    }
}