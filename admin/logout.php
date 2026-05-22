<?php
require_once '../config/database.php';

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
header('Location: ../index.php');
exit();
?>