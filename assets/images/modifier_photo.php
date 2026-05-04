<?php
session_start();
require 'db.php';

if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ../pages/connexion.html');
    exit;
}

$id = $_SESSION['utilisateur_id'];
$type = $_SESSION['type'];
$table = ($type === 'patient') ? 'patients' : 'medecins';

if (isset($_FILES['nouvelle_photo']) && $_FILES['nouvelle_photo']['error'] == 0) {
    $uploadDir = 'uploads/photos/';
    if (!is_dir('../' . $uploadDir)) {
        mkdir('../' . $uploadDir, 0777, true);
    }
    $photoName = uniqid() . '_' . basename($_FILES['nouvelle_photo']['name']);
    $uploadFile = $uploadDir . $photoName;

    if (move_uploaded_file($_FILES['nouvelle_photo']['tmp_name'], '../' . $uploadFile)) {
        $stmt = $pdo->prepare("UPDATE $table SET photo = ? WHERE id = ?");
        $stmt->execute([$uploadFile, $id]);
        echo "<script>alert('Votre photo a été mise à jour avec succès !'); window.location.href='profil.php';</script>";
    } else {
        echo "<script>alert('Erreur lors du téléchargement du fichier.'); window.location.href='profil.php';</script>";
    }
} else {
    echo "<script>alert('Aucune photo sélectionnée.'); window.location.href='profil.php';</script>";
}
?>
