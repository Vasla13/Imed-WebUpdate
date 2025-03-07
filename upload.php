<?php
require_once 'config.php';
require_once 'db.php';

// Convertir des notations "5G" en bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int)$val;
    switch ($last) {
        case 'g': $val *= 1024 * 1024 * 1024; break;
        case 'm': $val *= 1024 * 1024; break;
        case 'k': $val *= 1024; break;
    }
    return $val;
}

// Vérifier la taille POST
$postMaxSize = return_bytes(ini_get('post_max_size'));
if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $postMaxSize) {
    die("Fehler: Die gesamte Datenmenge überschreitet das post_max_size-Limit (" . ini_get('post_max_size') . ").");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Unberechtigter Zugriff.");
}

// Vérifier rôle admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Nur Administratoren dürfen Dateien hochladen.");
}

if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {

    // Limite 5GB
    $maxSize = 5368709120; // 5 GB
    if ($_FILES['file']['size'] > $maxSize) {
        die("Fehler: Die Datei ist größer als 5 GB.");
    }

    // Extensions autorisées
    $allowed_extensions = ['zip', 'tar', 'gz', 'tgz', 'rar'];
    $originalFileName = basename($_FILES['file']['name']);
    $file_extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        die("Fehler: Ungültiger Dateityp.");
    }

    // Champs du formulaire
    $version = $_POST['version'] ?? '';
    $release_date = $_POST['release_date'] ?? '';
    $comment = $_POST['comment'] ?? '';

    // Dossier d'upload
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Nom de fichier unique
    $newFileName = time() . "_" . $originalFileName;
    $targetFile = $targetDir . $newFileName;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
        $stmt = $conn->prepare("INSERT INTO VERSIONS (VERSION, RELEASE_DATE, DATEIEN, COMMENT) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            die("Fehler bei der Vorbereitung: " . $conn->error);
        }
        $stmt->bind_param("ssss", $version, $release_date, $targetFile, $comment);
        if ($stmt->execute()) {
            // Retour
            $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
            header("Location: " . $redirectUrl);
            exit();
        } else {
            die("Datenbankfehler: " . $stmt->error);
        }
    } else {
        die("Fehler beim Verschieben der Datei.");
    }
} else {
    // Gérer les erreurs
    $error_code = isset($_FILES['file']) ? $_FILES['file']['error'] : "Keine Datei hochgeladen";
    $error_message = "";
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            $error_message = "Die Datei überschreitet das durch php.ini definierte Upload-Limit.";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $error_message = "Die Datei überschreitet das im Formular definierte Limit.";
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message = "Die Datei wurde nur teilweise hochgeladen.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message = "Es wurde keine Datei hochgeladen.";
            break;
        default:
            $error_message = "Fehler beim Upload. Fehlercode: " . $error_code;
    }
    die($error_message);
}
?>
