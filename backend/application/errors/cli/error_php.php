<?php
defined('BASEPATH') OR exit('No direct script access allowed');

echo "Error PHP\n";
if (isset($message, $filepath, $line)) {
    echo "Mensaje: {$message}\n";
    echo "Archivo: {$filepath}\n";
    echo "Línea: {$line}\n";
}
