<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Error de Base de Datos</title>
    <style type="text/css">
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { color: #b91c1c; font-size: 20px; margin: 0 0 12px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .muted { color: #6b7280; }
        pre { background: #111827; color: #f9fafb; padding: 12px; border-radius: 8px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Error de base de datos</h1>
    <div class="box">
        <p class="muted">Se produjo un error al ejecutar una consulta.</p>

        <?php if (isset($message)) : ?>
            <p><strong>Mensaje:</strong> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (isset($heading)) : ?>
            <p><strong>Encabezado:</strong> <?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (isset($sql)) : ?>
            <p><strong>SQL:</strong></p>
            <pre><?php echo htmlspecialchars($sql, ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>
