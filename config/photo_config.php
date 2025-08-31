<?php
/**
 * Configuration pour la gestion des photos des étudiants
 */

// Configuration des photos
define('PHOTO_CONFIG', [
    // Dossier de stockage des photos
    'UPLOAD_DIR' => 'uploads/students/photos/',
    
    // Types de fichiers autorisés
    'ALLOWED_TYPES' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    
    // Taille maximale des fichiers (en octets) - 5MB
    'MAX_FILE_SIZE' => 5 * 1024 * 1024,
    
    // Dimensions maximales des images
    'MAX_WIDTH' => 1920,
    'MAX_HEIGHT' => 1080,
    
    // Qualité de compression JPEG (0-100)
    'JPEG_QUALITY' => 85,
    
    // Préfixe pour les noms de fichiers
    'FILE_PREFIX' => 'student_',
    
    // Thumbnail dimensions
    'THUMB_WIDTH' => 150,
    'THUMB_HEIGHT' => 150,
    
    // Dossier des thumbnails
    'THUMB_DIR' => 'uploads/students/photos/thumbnails/'
]);

// Fonction pour valider le type de fichier
function isValidPhotoType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, PHOTO_CONFIG['ALLOWED_TYPES']);
}

// Fonction pour générer un nom de fichier unique
function generatePhotoFilename($student_id, $extension) {
    $timestamp = time();
    $random = rand(1000, 9999);
    return PHOTO_CONFIG['FILE_PREFIX'] . $student_id . '_' . $timestamp . '_' . $random . '.' . $extension;
}

// Fonction pour obtenir le chemin complet d'une photo
function getPhotoPath($filename) {
    return PHOTO_CONFIG['UPLOAD_DIR'] . $filename;
}

// Fonction pour obtenir l'URL d'une photo
function getPhotoUrl($filename) {
    if (empty($filename)) {
        return null;
    }
    return PHOTO_CONFIG['UPLOAD_DIR'] . $filename;
}

// Fonction pour vérifier si une photo existe
function photoExists($filename) {
    if (empty($filename)) {
        return false;
    }
    return file_exists(PHOTO_CONFIG['UPLOAD_DIR'] . $filename);
}

// Fonction pour supprimer une photo
function deletePhoto($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $photo_path = PHOTO_CONFIG['UPLOAD_DIR'] . $filename;
    $thumb_path = PHOTO_CONFIG['THUMB_DIR'] . $filename;
    
    $deleted = false;
    
    if (file_exists($photo_path)) {
        $deleted = unlink($photo_path);
    }
    
    if (file_exists($thumb_path)) {
        unlink($thumb_path);
    }
    
    return $deleted;
}

// Fonction pour gérer l'upload des photos des étudiants
function handleStudentPhotoUpload($file, $student_id) {
    $result = [
        'success' => false,
        'filename' => null,
        'message' => ''
    ];
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Erreur lors de l\'upload du fichier.';
        return $result;
    }
    
    // Vérifier la taille du fichier
    if ($file['size'] > PHOTO_CONFIG['MAX_FILE_SIZE']) {
        $result['message'] = 'Le fichier est trop volumineux. Taille maximale: ' . (PHOTO_CONFIG['MAX_FILE_SIZE'] / 1024 / 1024) . ' MB.';
        return $result;
    }
    
    // Vérifier le type de fichier
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isValidPhotoType($file['name'])) {
        $result['message'] = 'Type de fichier non autorisé. Types acceptés: ' . implode(', ', PHOTO_CONFIG['ALLOWED_TYPES']) . '.';
        return $result;
    }
    
    // Générer un nom de fichier unique
    $filename = generatePhotoFilename($student_id, $extension);
    $upload_path = PHOTO_CONFIG['UPLOAD_DIR'] . $filename;
    
    // Créer le dossier d'upload s'il n'existe pas
    if (!is_dir(PHOTO_CONFIG['UPLOAD_DIR'])) {
        mkdir(PHOTO_CONFIG['UPLOAD_DIR'], 0755, true);
    }
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Copier le fichier dans le dossier des thumbnails (sans redimensionnement)
        $thumb_path = PHOTO_CONFIG['THUMB_DIR'] . $filename;
        if (!is_dir(PHOTO_CONFIG['THUMB_DIR'])) {
            mkdir(PHOTO_CONFIG['THUMB_DIR'], 0755, true);
        }
        copy($upload_path, $thumb_path);
        
        $result['success'] = true;
        $result['filename'] = $filename;
        $result['message'] = 'Photo uploadée avec succès.';
    } else {
        $result['message'] = 'Erreur lors du déplacement du fichier.';
    }
    
    return $result;
}
?>
