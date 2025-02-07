class PlayerStats {
    constructor() {
        this.lpChart = null;
        this.playerId = this.getPlayerId();
        this.lpData = window.lpData || [];
        this.playerData = window.playerData || {};
        this.init();
    }

    getPlayerId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    async init() {
        this.initializeCharts();
        this.setupEventListeners();
        await this.loadInitialData();
        this.hideLoadingSpinner(); // Cacher le spinner au démarrage
    }

    setupEventListeners() {
        const updateButton = document.querySelector('.btn i.fa-sync-alt').parentElement;
        if (updateButton) {
            updateButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshData();
            });
        }
    }

    showLoadingSpinner() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    hideLoadingSpinner() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async refreshData() {
        try {
            this.showLoadingSpinner();
            
            const response = await fetch(`synchroniser_joueur.php?id=${this.playerId}`);
            const result = await response.json();
            
            if (result.success) {
                window.location.reload();
            } else {
                alert(result.message || 'Erreur lors de la synchronisation');
            }
        } catch (error) {
            console.error('Erreur lors de l\'actualisation:', error);
            alert('Erreur lors de l\'actualisation des données');
        } finally {
            this.hideLoadingSpinner();
        }
    }

    async loadInitialData() {
        try {
            if (this.playerData && Object.keys(this.playerData).length > 0) {
                this.updateStats(this.playerData);
                return;
            }
            const response = await fetch(`api/player_stats.php?id=${this.playerId}`);
            const data = await response.json();
            this.updateStats(data);
        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
        }
    }

    initializeCharts() {
        // Vérifier si ApexCharts est chargé
        if (typeof ApexCharts === 'undefined') {
            const chartContainer = document.getElementById('lpChart');
            chartContainer.innerHTML = '<div class="chart-error">Erreur: ApexCharts n\'est pas chargé</div>';
            console.error('ApexCharts n\'est pas chargé');
            return;
        }

        // Vérifier si nous avons des données
        if (!this.lpData || this.lpData.length === 0) {
            const chartContainer = document.getElementById('lpChart');
            chartContainer.innerHTML = '<div class="chart-error">Aucune donnée d\'historique LP disponible</div>';
            return;
        }

        // Convertir les LP en valeur numérique totale
        function calculateTotalLP(tier, rank, lp) {
            const tierValues = {
                'IRON': 0,
                'BRONZE': 400,
                'SILVER': 800,
                'GOLD': 1200,
                'PLATINUM': 1600,
                'EMERALD': 2000,
                'DIAMOND': 2400,
                'MASTER': 2800,
                'GRANDMASTER': 3200,
                'CHALLENGER': 3600
            };
            
            const rankValues = {
                'IV': 0,
                'III': 100,
                'II': 200,
                'I': 300
            };

            const tierBase = tierValues[tier] || 0;
            const rankBonus = rankValues[rank] || 0;
            return tierBase + rankBonus + parseInt(lp);
        }

        // Transformer les données pour le graphique
        const chartData = this.lpData.map(entry => ({
            x: new Date(entry.timestamp).getTime(),
            y: calculateTotalLP(entry.tier, entry.rank, entry.lp)
        }));

        // Configuration du graphique LP
        const lpOptions = {
            series: [{
                name: 'LP',
                data: chartData
            }],
            chart: {
                type: 'area',
                height: 350,
                toolbar: {
                    show: true,
                    tools: {
                        download: false,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        pan: true,
                    }
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                    animateGradually: {
                        enabled: true,
                        delay: 150
                    },
                    dynamicAnimation: {
                        enabled: true,
                        speed: 350
                    }
                },
                background: 'transparent'
            },
            theme: {
                mode: 'dark'
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3,
                    stops: [0, 90, 100],
                    colorStops: [
                        {
                            offset: 0,
                            color: '#00FFC2',
                            opacity: 0.7
                        },
                        {
                            offset: 100,
                            color: '#00FFC2',
                            opacity: 0.3
                        }
                    ]
                }
            },
            colors: ['#00FFC2'],
            xaxis: {
                type: 'datetime',
                labels: {
                    style: { colors: '#ffffff' }
                }
            },
            yaxis: {
                labels: {
                    style: { colors: '#ffffff' }
                }
            },
            tooltip: {
                theme: 'dark',
                x: {
                    format: 'dd MMM yyyy'
                },
                y: {
                    formatter: (value) => `${value} LP`
                }
            }
        };

        const chartContainer = document.getElementById('lpChart');
        chartContainer.innerHTML = ''; // Nettoyer le conteneur
        
        this.lpChart = new ApexCharts(chartContainer, lpOptions);
        this.lpChart.render();
    }

    updateStats(data) {
        // Mise à jour du graphique LP
        if (data.lp_history && data.lp_history.length > 0) {
            const seriesData = data.lp_history.map(entry => ({
                x: new Date(entry.timestamp).getTime(),
                y: entry.lp
            }));
            this.lpChart.updateSeries([{
                name: 'LP',
                data: seriesData
            }]);
        }

        // Mise à jour des statistiques avec animations
        this.updateStatWithAnimation('total-games', data.player.total_games);
        this.updateStatWithAnimation('winrate', data.player.pourcentage_victoire);
        this.updateStatWithAnimation('cs-per-min', data.global_stats.avg_cs_per_game);
        this.updateStatWithAnimation('vision-score', data.global_stats.avg_vision);
    }

    updateStatWithAnimation(elementId, newValue) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const currentValue = parseFloat(element.textContent);
        const duration = 1000;
        const steps = 60;
        const increment = (newValue - currentValue) / steps;
        let currentStep = 0;

        const animate = () => {
            currentStep++;
            const value = currentValue + (increment * currentStep);
            element.textContent = value.toFixed(1);

            if (currentStep < steps) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    window.playerStats = new PlayerStats();
});

// Supprimer cette fonction globale car elle est maintenant gérée dans la classe
window.updatePlayerStats = undefined; 