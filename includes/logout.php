<?php
// =====================================================
// CERRAR SESIÓN - SISTEMA METRU
// =====================================================

session_start();
session_destroy();

// Redirigir al login
header('Location: ../index.php?logout=1');
exit();
?>