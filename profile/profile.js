/**
 * JavaScript pour le module de profil utilisateur
 * Naklass - Système de Gestion Scolaire
 */

// Configuration globale
const ProfileManager = {
    init() {
        this.initEventListeners();
        this.initAnimations();
        this.initFileUpload();
        this.initTooltips();
        this.initSecurityChecks();
    },

    // ===== ÉVÉNEMENTS ===== 
    initEventListeners() {
        // Auto-dismiss des alertes après 5 secondes
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // Smooth scroll pour les ancres
        const anchorLinks = document.querySelectorAll('a[href^="#"]');
        anchorLinks.forEach(link => {
            link.addEventListener('click', this.smoothScroll);
        });

        // Validation en temps réel des formulaires
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('input', this.validateForm);
            form.addEventListener('submit', this.handleFormSubmit);
        });

        // Boutons de navigation
        this.initNavigationButtons();
    },

    // ===== ANIMATIONS ===== 
    initAnimations() {
        // Animation d'apparition des cartes
        const cards = document.querySelectorAll('.card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('fade-in-up');
                    }, index * 100);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        cards.forEach(card => {
            observer.observe(card);
        });

        // Animation de la photo de profil
        const profilePhoto = document.querySelector('.profile-photo, .profile-photo-placeholder');
        if (profilePhoto) {
            profilePhoto.addEventListener('mouseenter', function() {
                this.classList.add('pulse-animation');
            });
            profilePhoto.addEventListener('mouseleave', function() {
                this.classList.remove('pulse-animation');
            });
        }
    },

    // ===== UPLOAD DE FICHIER ===== 
    initFileUpload() {
        const photoInput = document.getElementById('photo-input');
        const dropZone = document.querySelector('.profile-photo-container');
        
        if (photoInput && dropZone) {
            // Drag & Drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, this.preventDefaults, false);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

            dropZone.addEventListener('drop', this.handleDrop, false);
            dropZone.addEventListener('click', () => photoInput.click());

            // Validation des fichiers
            photoInput.addEventListener('change', this.validateFileUpload);
        }
    },

    // ===== TOOLTIPS ===== 
    initTooltips() {
        // Initialiser les tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Tooltips personnalisés
        const customTooltips = document.querySelectorAll('[data-tooltip]');
        customTooltips.forEach(element => {
            element.classList.add('tooltip-naklass');
        });
    },

    // ===== SÉCURITÉ ===== 
    initSecurityChecks() {
        // Vérification de la force du mot de passe en temps réel
        const passwordField = document.getElementById('new_password');
        if (passwordField) {
            passwordField.addEventListener('input', this.checkPasswordStrength);
        }

        // Vérification de la correspondance des mots de passe
        const confirmField = document.getElementById('confirm_password');
        if (confirmField) {
            confirmField.addEventListener('input', this.checkPasswordMatch);
        }

        // Auto-logout si inactif (optionnel)
        this.initInactivityTimer();
    },

    // ===== FONCTIONS UTILITAIRES ===== 
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    },

    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        const photoInput = document.getElementById('photo-input');
        
        if (files.length > 0 && photoInput) {
            photoInput.files = files;
            ProfileManager.validateFileUpload({ target: photoInput });
        }
    },

    validateFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            this.showNotification('Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.', 'danger');
            e.target.value = '';
            return;
        }

        if (file.size > maxSize) {
            this.showNotification('Le fichier est trop volumineux (maximum 5MB).', 'danger');
            e.target.value = '';
            return;
        }

        // Prévisualisation
        this.previewImage(file);
        this.showNotification('Fichier sélectionné avec succès. Cliquez sur "Télécharger" pour sauvegarder.', 'success');
    },

    previewImage(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentPhoto = document.getElementById('current-photo');
            const placeholder = document.getElementById('photo-placeholder');
            
            if (currentPhoto) {
                currentPhoto.src = e.target.result;
                currentPhoto.style.opacity = '0.7';
                currentPhoto.style.border = '3px dashed var(--naklass-primary)';
            } else if (placeholder) {
                placeholder.outerHTML = `
                    <img src="${e.target.result}" alt="Aperçu" class="profile-photo" 
                         id="current-photo" style="opacity: 0.7; border: 3px dashed var(--naklass-primary);">
                `;
            }
        };
        reader.readAsDataURL(file);
    },

    smoothScroll(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    },

    validateForm(e) {
        const form = e.target.closest('form');
        if (!form) return;

        const inputs = form.querySelectorAll('input, select, textarea');
        let isValid = true;

        inputs.forEach(input => {
            if (input.hasAttribute('required') && !input.value.trim()) {
                isValid = false;
            }
        });

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = !isValid;
        }
    },

    handleFormSubmit(e) {
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> En cours...';
            
            // Réactiver après 3 secondes en cas d'erreur
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Enregistrer';
            }, 3000);
        }
    },

    initNavigationButtons() {
        // Bouton retour intelligent
        const backButtons = document.querySelectorAll('.btn-back');
        backButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (window.history.length > 1) {
                    e.preventDefault();
                    window.history.back();
                }
            });
        });

        // Boutons de navigation rapide
        const quickNavButtons = document.querySelectorAll('[data-quick-nav]');
        quickNavButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const target = btn.dataset.quickNav;
                window.location.href = target;
            });
        });
    },

    // ===== SÉCURITÉ AVANCÉE ===== 
    checkPasswordStrength(e) {
        const password = e.target.value;
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');
        
        if (!strengthBar || !strengthText) return;

        let strength = 0;
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };

        const passedChecks = Object.values(checks).filter(Boolean).length;
        strength = (passedChecks / 5) * 100;

        // Mise à jour visuelle
        strengthBar.style.width = strength + '%';
        
        if (strength < 40) {
            strengthBar.className = 'progress-bar bg-danger';
            strengthText.textContent = 'Mot de passe faible';
            strengthText.className = 'form-text text-danger';
        } else if (strength < 80) {
            strengthBar.className = 'progress-bar bg-warning';
            strengthText.textContent = 'Mot de passe moyen';
            strengthText.className = 'form-text text-warning';
        } else {
            strengthBar.className = 'progress-bar bg-success';
            strengthText.textContent = 'Mot de passe fort';
            strengthText.className = 'form-text text-success';
        }

        // Animation de la barre
        strengthBar.style.transition = 'all 0.3s ease';
    },

    checkPasswordMatch() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const matchText = document.getElementById('password-match-text');
        
        if (!newPassword || !confirmPassword || !matchText) return;

        if (confirmPassword.value.length > 0) {
            if (newPassword.value === confirmPassword.value) {
                matchText.textContent = '✓ Les mots de passe correspondent';
                matchText.className = 'form-text text-success';
                confirmPassword.setCustomValidity('');
            } else {
                matchText.textContent = '✗ Les mots de passe ne correspondent pas';
                matchText.className = 'form-text text-danger';
                confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
            }
        } else {
            matchText.textContent = '';
            confirmPassword.setCustomValidity('');
        }
    },

    initInactivityTimer() {
        let inactivityTimer;
        const inactivityTime = 30 * 60 * 1000; // 30 minutes

        const resetTimer = () => {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                this.showInactivityWarning();
            }, inactivityTime);
        };

        // Événements qui réinitialisent le timer
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        resetTimer();
    },

    showInactivityWarning() {
        const modal = new bootstrap.Modal(document.createElement('div'));
        // Implémentation du modal d'avertissement d'inactivité
        console.log('Session inactive détectée');
    },

    // ===== NOTIFICATIONS ===== 
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        notification.innerHTML = `
            <i class="bi bi-${this.getIconForType(type)}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                const bsAlert = new bootstrap.Alert(notification);
                bsAlert.close();
            }
        }, 5000);
    },

    getIconForType(type) {
        const icons = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    // ===== UTILITAIRES ===== 
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// ===== FONCTIONS GLOBALES ===== 
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (!field || !icon) return;
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        ProfileManager.previewImage(input.files[0]);
    }
}

// ===== INITIALISATION ===== 
document.addEventListener('DOMContentLoaded', function() {
    ProfileManager.init();
    
    // Animation CSS pour les éléments qui se chargent
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.3s ease';
    
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);
});

// ===== GESTION DES ERREURS ===== 
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript:', e.error);
    ProfileManager.showNotification('Une erreur inattendue s\'est produite.', 'danger');
});

// ===== EXPORT POUR UTILISATION EXTERNE ===== 
window.ProfileManager = ProfileManager;
