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
        this.hideLoadingSpinner();
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

    initializeCharts() {
        const chartContainer = document.getElementById('lpChart');
        if (!chartContainer) return;

        const lpOptions = {
            series: [{
                name: 'LP',
                data: this.lpData.map(entry => ({
                    x: new Date(entry.timestamp).getTime(),
                    y: entry.lp
                }))
            }],
            chart: {
                type: 'area',
                height: 350,
                background: 'transparent',
                foreColor: '#fff'
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
                    stops: [0, 90, 100]
                }
            },
            colors: ['#00FFC2'],
            xaxis: {
                type: 'datetime'
            },
            yaxis: {
                title: {
                    text: 'League Points'
                }
            }
        };

        this.lpChart = new ApexCharts(chartContainer, lpOptions);
        this.lpChart.render();
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
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                window.location.reload();
            } else {
                throw new Error(result.message || 'Erreur lors de la synchronisation');
            }
        } catch (error) {
            console.error('Erreur lors de l\'actualisation:', error);
            alert('Erreur lors de l\'actualisation des données: ' + error.message);
        } finally {
            this.hideLoadingSpinner();
        }
    }
}

class LPChart {
    constructor() {
        this.chart = null;
        this.container = document.querySelector('.chart-container');
        this.init();
    }

    init() {
        if (typeof window.lpHistory === 'undefined' || !window.lpHistory.length) {
            console.log('Pas de données LP disponibles');
            return;
        }

        const lpData = this.prepareLPData();
        this.createChart(lpData);
    }

    prepareLPData() {
        return window.lpHistory.map(entry => {
            const tierValues = {
                'IRON': 0,
                'BRONZE': 400,
                'SILVER': 800,
                'GOLD': 1200,
                'PLATINUM': 1600,
                'DIAMOND': 2000,
                'MASTER': 2400,
                'GRANDMASTER': 2800,
                'CHALLENGER': 3200
            };

            const rankValues = {
                'IV': 0,
                'III': 100,
                'II': 200,
                'I': 300
            };

            const totalLP = tierValues[entry.tier] + 
                           (entry.rank ? rankValues[entry.rank] : 0) + 
                           parseInt(entry.lp);

            return {
                date: new Date(entry.recorded_at),
                lp: totalLP,
                tier: entry.tier,
                rank: entry.rank,
                actualLP: entry.lp
            };
        });
    }

    createChart(lpData) {
        // Nettoyer le conteneur
        this.container.innerHTML = '';
        
        // Créer le canvas
        const canvas = document.createElement('canvas');
        canvas.width = 800;  // Largeur fixe
        canvas.height = 400; // Hauteur fixe
        this.container.appendChild(canvas);

        this.chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: lpData.map(d => d.date.toLocaleDateString()),
                datasets: [{
                    label: 'LP',
                    data: lpData.map(d => d.lp),
                    borderColor: '#00FFC2',
                    backgroundColor: 'rgba(0, 255, 194, 0.2)',
                    tension: 0.1,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#00FFC2'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const data = lpData[context.dataIndex];
                                return `${data.tier} ${data.rank || ''} ${data.actualLP} LP`;
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#00FFC2',
                        borderWidth: 1
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                const tiers = ['IRON', 'BRONZE', 'SILVER', 'GOLD', 'PLATINUM', 'DIAMOND', 'MASTER'];
                                const tier = tiers[Math.floor(value / 400)];
                                return tier || '';
                            },
                            color: '#fff',
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#fff',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser PlayerStats
    new PlayerStats();

    // Vérifier si nous avons des données d'historique LP
    if (typeof window.lpHistory === 'undefined' || !window.lpHistory.length) {
        console.log('Pas de données LP disponibles');
        return;
    }

    // Convertir les données pour le graphique
    const lpData = window.lpHistory.map(entry => {
        const tierValues = {
            'IRON': 0,
            'BRONZE': 400,
            'SILVER': 800,
            'GOLD': 1200,
            'PLATINUM': 1600,
            'DIAMOND': 2000,
            'MASTER': 2400,
            'GRANDMASTER': 2800,
            'CHALLENGER': 3200
        };

        const rankValues = {
            'IV': 0,
            'III': 100,
            'II': 200,
            'I': 300
        };

        const totalLP = tierValues[entry.tier] + 
                       (entry.rank ? rankValues[entry.rank] : 0) + 
                       parseInt(entry.lp);

        return {
            date: new Date(entry.recorded_at),
            lp: totalLP,
            tier: entry.tier,
            rank: entry.rank,
            actualLP: entry.lp
        };
    });

    // Créer le graphique
    const ctx = document.createElement('canvas');
    ctx.style.width = '100%';
    ctx.style.height = '300px';
    document.querySelector('.chart-container').appendChild(ctx);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: lpData.map(d => d.date.toLocaleDateString()),
            datasets: [{
                label: 'LP',
                data: lpData.map(d => d.lp),
                borderColor: '#00FFC2',
                backgroundColor: 'rgba(0, 255, 194, 0.2)',
                tension: 0.1,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#00FFC2'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const data = lpData[context.dataIndex];
                            return `${data.tier} ${data.rank || ''} ${data.actualLP} LP`;
                        }
                    },
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#00FFC2',
                    borderWidth: 1
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            const tiers = ['IRON', 'BRONZE', 'SILVER', 'GOLD', 'PLATINUM', 'DIAMOND', 'MASTER'];
                            const tier = tiers[Math.floor(value / 400)];
                            return tier || '';
                        },
                        color: '#fff',
                        padding: 10
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#fff',
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}); 