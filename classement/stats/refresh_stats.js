// Fonction pour actualiser les stats d'un joueur
function refreshStats(playerId) {
    // Afficher le spinner de chargement
    document.querySelector('.loading').style.display = 'flex';
    
    // Faire la requête AJAX
    fetch(`/classement/stats/sync_player_stats.php?id=${playerId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recharger la page pour afficher les nouvelles données
            window.location.reload();
        } else {
            alert('Erreur lors de l\'actualisation: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'actualisation des données');
    })
    .finally(() => {
        // Cacher le spinner de chargement
        document.querySelector('.loading').style.display = 'none';
    });
}

// Ajouter un écouteur d'événements sur le bouton d'actualisation
document.querySelector('.btn-actualiser').addEventListener('click', function() {
    // Récupérer l'ID du joueur depuis l'URL ou un attribut data
    const playerId = this.dataset.playerId;
    refreshStats(playerId);
}); 
