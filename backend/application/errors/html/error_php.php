<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Error PHP</title>
    <style type="text/css">
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { color: #b91c1c; font-size: 20px; margin: 0 0 12px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .muted { color: #6b7280; }
        code { background: #111827; color: #f9fafb; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Error PHP</h1>
    <div class="box">
        <p class="muted">Se produjo un error interno al procesar la solicitud.</p>
        <?php if (isset($severity, $message, $filepath, $line)) : ?>
            <p><strong>Mensaje:</strong> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Archivo:</strong> <?php echo htmlspecialchars($filepath, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Línea:</strong> <?php echo (int) $line; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
