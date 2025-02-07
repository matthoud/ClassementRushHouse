class PlayerStatsRefresh {
    constructor() {
        this.playerId = this.getPlayerId();
        this.init();
    }

    getPlayerId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    init() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        const updateButton = document.querySelector('.refresh-btn');
        if (updateButton) {
            updateButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshData();
            });
        }
    }

    async refreshData() {
        const button = document.querySelector('.refresh-btn');
        try {
            button.classList.add('loading');
            button.disabled = true;
            
            const url = new URL('sync_player_stats.php', window.location.href);
            url.searchParams.append('id', this.playerId);
            
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                button.classList.add('success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error(result.message || 'Erreur lors de la synchronisation');
            }
        } catch (error) {
            console.error('Erreur:', error);
            button.classList.add('error');
            alert('Erreur lors de l\'actualisation des données: ' + error.message);
        } finally {
            setTimeout(() => {
                button.classList.remove('loading', 'success', 'error');
                button.disabled = false;
            }, 2000);
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new PlayerStatsRefresh();
}); 