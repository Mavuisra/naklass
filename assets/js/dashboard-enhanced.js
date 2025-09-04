/**
 * JavaScript amélioré pour le tableau de bord Naklass
 * Utilise les vraies données de la base de données
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeEnhancedDashboard();
});

function initializeEnhancedDashboard() {
    // Initialiser le sidebar
    initializeSidebar();
    
    // Initialiser les graphiques avec vraies données
    initializeEnhancedCharts();
    
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
 * Initialiser les graphiques Chart.js avec vraies données
 */
function initializeEnhancedCharts() {
    // Graphique des paiements avec vraies données
    const paymentsCanvas = document.getElementById('paymentsChart');
    if (paymentsCanvas && typeof evolutionPaiements !== 'undefined') {
        initializeEnhancedPaymentsChart(paymentsCanvas);
    }
}

/**
 * Graphique d'évolution des paiements avec vraies données
 */
function initializeEnhancedPaymentsChart(canvas) {
    const ctx = canvas.getContext('2d');
    
    // Préparer les données depuis la base de données
    const chartData = prepareChartData();
    
    const config = {
        type: 'line',
        data: chartData,
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
            elements: {
                point: {
                    hoverRadius: 8,
                    radius: 6
                }
            }
        }
    };
    
    new Chart(ctx, config);
}

/**
 * Préparer les données du graphique depuis la base de données
 */
function prepareChartData() {
    if (!evolutionPaiements || evolutionPaiements.length === 0) {
        // Données par défaut si aucune donnée n'est disponible
        return {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Paiements (CDF)',
                data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                backgroundColor: 'rgba(0, 119, 182, 0.1)',
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
    }
    
    // Extraire les labels et données depuis la base de données
    const labels = evolutionPaiements.map(item => {
        // Formater le mois pour l'affichage
        const date = new Date(item.mois + '-01');
        return date.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
    });
    
    const data = evolutionPaiements.map(item => parseFloat(item.total_paiements) || 0);
    
    return {
        labels: labels,
        datasets: [{
            label: 'Paiements (CDF)',
            data: data,
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
}

/**
 * Initialiser les animations
 */
function initializeAnimations() {
    // Animation des cartes de statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animation des alertes
    const alertCards = document.querySelectorAll('.alert-card');
    alertCards.forEach((alert, index) => {
        alert.style.opacity = '0';
        alert.style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '1';
            alert.style.transform = 'scale(1)';
        }, 500 + index * 150);
    });
}

/**
 * Initialiser les tooltips
 */
function initializeTooltips() {
    // Initialiser les tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Tooltips personnalisés pour les statistiques
    const statNumbers = document.querySelectorAll('.stat-content h3');
    statNumbers.forEach(stat => {
        stat.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        stat.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

/**
 * Vérifier la session utilisateur
 */
function checkSession() {
    // Vérifier si la session est toujours valide
    const sessionCheck = setInterval(() => {
        fetch('check_session.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    clearInterval(sessionCheck);
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.log('Erreur de vérification de session:', error);
            });
    }, 300000); // Vérifier toutes les 5 minutes
}

/**
 * Fonctions utilitaires pour le dashboard
 */
function formatCurrency(amount, currency = 'CDF') {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function animateValue(element, start, end, duration) {
    const startTime = performance.now();
    
    function updateValue(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const current = start + (end - start) * progress;
        element.textContent = Math.floor(current).toLocaleString('fr-FR');
        
        if (progress < 1) {
            requestAnimationFrame(updateValue);
        }
    }
    
    requestAnimationFrame(updateValue);
}

// Export des fonctions pour utilisation globale
window.dashboardUtils = {
    formatCurrency,
    animateValue,
    prepareChartData
};





