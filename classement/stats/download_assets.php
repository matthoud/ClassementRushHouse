<?php
/* Vérification de sécurité - à adapter selon votre système d'authentification */
session_start();

/* Vérifier si le dossier assets/rank est accessible en écriture */
$rankDir = __DIR__ . '/assets/rank';
if (!file_exists($rankDir)) {
    if (!@mkdir($rankDir, 0755, true)) {
        die("Erreur : Impossible de créer le dossier $rankDir. Vérifiez les permissions.");
    }
}

if (!is_writable($rankDir)) {
    die("Erreur : Le dossier $rankDir n'est pas accessible en écriture. Permissions actuelles : " . 
        decoct(fileperms($rankDir) & 0777));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['rank_icons'])) {
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
    $rankNames = ['iron', 'bronze', 'silver', 'gold', 'platinum', 'diamond', 'master', 'grandmaster', 'challenger'];
    
    /* Vérifier l'espace disque disponible */
    $diskFree = disk_free_space($rankDir);
    if ($diskFree === false || $diskFree < 1024 * 1024 * 10) { /* 10MB minimum */
        die("Erreur : Espace disque insuffisant");
    }
    
    foreach ($_FILES['rank_icons']['tmp_name'] as $key => $tmp_name) {
        if (!isset($rankNames[$key])) continue;
        
        $file_type = $_FILES['rank_icons']['type'][$key];
        $file_name = $_FILES['rank_icons']['name'][$key];
        $file_size = $_FILES['rank_icons']['size'][$key];
        
        /* Vérifications de sécurité */
        if (!in_array($file_type, $allowedTypes)) {
            die("Type de fichier non autorisé : $file_name (type: $file_type)");
        }
        
        if ($file_size > 1024 * 1024 * 2) { /* 2MB max par fichier */
            die("Fichier trop volumineux : $file_name");
        }
        
        /* Déplacer le fichier */
        $destination = $rankDir . '/' . $rankNames[$key] . '.png';
        if (!@move_uploaded_file($tmp_name, $destination)) {
            $error = error_get_last();
            die("Erreur lors du téléchargement de $file_name : " . ($error['message'] ?? 'Erreur inconnue'));
        }
        
        /* Vérifier que le fichier a bien été créé */
        if (!file_exists($destination)) {
            die("Erreur : Le fichier $destination n'a pas été créé");
        }
    }
    
    echo "Icônes mises à jour avec succès! Vérifiez que les fichiers sont bien présents dans $rankDir";
    exit;
}

/* Afficher les permissions actuelles et l'utilisateur PHP */
$currentUser = posix_getpwuid(posix_geteuid());
$currentGroup = posix_getgrgid(posix_getegid());
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestion des icônes de rank</title>
    <style>
        .upload-form { margin: 20px; padding: 20px; }
        .file-input { margin: 10px 0; }
        .system-info { 
            background: #f5f5f5; 
            padding: 10px; 
            margin: 10px 0; 
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="upload-form">
        <div class="system-info">
            <h3>Informations système :</h3>
            <p>Utilisateur PHP : <?php echo $currentUser['name']; ?></p>
            <p>Groupe PHP : <?php echo $currentGroup['name']; ?></p>
            <p>Permissions du dossier rank : <?php echo decoct(fileperms($rankDir) & 0777); ?></p>
            <p>Chemin du dossier : <?php echo $rankDir; ?></p>
        </div>
        
        <h2>Télécharger les icônes de rank</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="file-input">
                <label>Iron:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Bronze:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Silver:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Gold:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Platinum:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Diamond:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Master:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Grandmaster:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <div class="file-input">
                <label>Challenger:</label>
                <input type="file" name="rank_icons[]" accept=".png,.jpg,.jpeg" required>
            </div>
            <button type="submit">Télécharger les icônes</button>
        </form>
    </div>
</body>
</html> 
