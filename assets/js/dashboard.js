/**
 * JavaScript pour le tableau de bord Naklass
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Initialiser le sidebar
    initializeSidebar();
    
    // Initialiser les graphiques
    initializeCharts();
    
    // Initialiser les animations
    initializeAnimations();
    
    // Initialiser les tooltips
    initializeTooltips();
    
    // Vérifier la session
    checkSession();
}

/**
 * Gestion du sidebar
 */
function initializeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
    const sidebarOverlay = createSidebarOverlay();
    
    // Toggle sidebar sur mobile
    sidebarToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            if (window.innerWidth <= 1200) {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });
    });
    
    // Fermer sidebar en cliquant sur l'overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    // Restaurer l'état du sidebar depuis localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 1200) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
    
    // Gérer le redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1200) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('sidebar-collapsed');
        }
    });
}

/**
 * Créer l'overlay pour le sidebar sur mobile
 */
function createSidebarOverlay() {
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    return overlay;
}

/**
 * Initialiser les graphiques Chart.js
 */
function initializeCharts() {
    // Graphique des paiements
    const paymentsCanvas = document.getElementById('paymentsChart');
    if (paymentsCanvas) {
        initializePaymentsChart(paymentsCanvas);
    }
}

/**
 * Graphique d'évolution des paiements
 */
function initializePaymentsChart(canvas) {
    const ctx = canvas.getContext('2d');
    
            // Données simulées pour la démonstration
        const data = {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Paiements (CDF)',
                data: [1200000, 1900000, 3000000, 5000000, 2000000, 3000000, 4500000, 3200000, 2800000, 4100000, 3500000, 5200000],
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return null;
                    
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(0, 119, 182, 0.1)');
                    gradient.addColorStop(1, 'rgba(0, 119, 182, 0.8)');
                    return gradient;
                },
                borderColor: '#0077b6',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
                            pointBackgroundColor: '#0077b6',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
        }]
    };
    
    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#343a40',
                        font: {
                            size: 14,
                            weight: '500'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(52, 58, 64, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#0077b6',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return 'Paiements: ' + new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' FC';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6c757d',
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6c757d',
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return new Intl.NumberFormat('fr-FR', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value) + ' FC';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    };
    
    new Chart(ctx, config);
}

/**
 * Initialiser les animations au scroll
 */
function initializeAnimations() {
    const animatedElements = document.querySelectorAll('.stat-card, .alert-card, .card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(element);
    });
}

/**
 * Initialiser les tooltips Bootstrap
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Vérifier l'état de la session
 */
function checkSession() {
    // Vérifier la session toutes les 5 minutes
    setInterval(function() {
        fetch('../auth/check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    // Rediriger directement vers la page d'accueil au lieu d'afficher une modal
                    window.location.href = '../index.php';
                }
            })
            .catch(error => {
                console.error('Erreur de vérification de session:', error);
                // En cas d'erreur, rediriger aussi vers la page d'accueil
                window.location.href = '../index.php';
            });
    }, 300000); // 5 minutes
}

/**
 * Afficher la modal de session expirée (fonction conservée pour compatibilité)
 */
function showSessionExpiredModal() {
    // Rediriger directement vers la page d'accueil
    window.location.href = '../index.php';
}

/**
 * Fonctions utilitaires
 */

// Formater les nombres
function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

// Formater les montants
function formatCurrency(amount, currency = 'CDF') {
    const formatted = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(amount);
    
    switch (currency) {
        case 'USD':
            return formatted + ' $';
        case 'EUR':
            return formatted + ' €';
        case 'CDF':
        default:
            return formatted + ' FC';
    }
}

// Afficher des notifications toast
function showToast(message, type = 'info') {
    const toastContainer = getOrCreateToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bootstrapToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });
    
    bootstrapToast.show();
    
    // Supprimer le toast après fermeture
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Obtenir ou créer le conteneur de toast
function getOrCreateToastContainer() {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
    }
    return container;
}

// Confirmer une action
function confirmAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-question-circle me-2"></i>Confirmation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmBtn">Confirmer</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    const confirmBtn = modal.querySelector('#confirmBtn');
    confirmBtn.addEventListener('click', function() {
        callback();
        bootstrapModal.hide();
    });
    
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
    });
}

// Charger du contenu dynamiquement
function loadContent(url, container, showLoader = true) {
    const targetContainer = typeof container === 'string' ? 
        document.querySelector(container) : container;
    
    if (showLoader) {
        targetContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
    }
    
    return fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur de chargement');
            }
            return response.text();
        })
        .then(html => {
            targetContainer.innerHTML = html;
            // Réinitialiser les tooltips et autres composants
            initializeTooltips();
        })
        .catch(error => {
            targetContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur lors du chargement du contenu.
                </div>
            `;
            console.error('Erreur:', error);
        });
}

// Gérer les formulaires AJAX
function submitForm(form, callback) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Désactiver le bouton et afficher le loader
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Opération réussie', 'success');
            if (callback) callback(data);
        } else {
            showToast(data.message || 'Une erreur est survenue', 'danger');
        }
    })
    .catch(error => {
        showToast('Erreur de communication avec le serveur', 'danger');
        console.error('Erreur:', error);
    })
    .finally(() => {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Exporter les fonctions utilitaires
window.NaklassUtils = {
    formatNumber,
    formatCurrency,
    showToast,
    confirmAction,
    loadContent,
    submitForm
};
