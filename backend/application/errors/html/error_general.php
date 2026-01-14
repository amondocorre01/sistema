<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Error</title>
    <style type="text/css">
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { color: #b91c1c; font-size: 20px; margin: 0 0 12px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Error interno</h1>
    <div class="box">
        <p class="muted">Se produjo un error interno al procesar la solicitud.</p>
        <?php if (isset($message)) : ?>
            <p><strong>Mensaje:</strong> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
