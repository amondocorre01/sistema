<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class OpcionesPago extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('OpcionPago_model');
    }

    private function normalize_tipo($tipo) {
        $t = strtoupper(trim((string) $tipo));
        $allowed = ['EFECTIVO', 'QR', 'TARJETA', 'MIXTO'];
        if (!in_array($t, $allowed, true)) {
            $this->response_library->error('Tipo no válido. Use EFECTIVO, QR, TARJETA o MIXTO', 400);
        }
        return $t;
    }

    private function upload_qr_imagen() {
        if (!isset($_FILES['qr_imagen']) || empty($_FILES['qr_imagen']['name'])) {
            return null;
        }

        $file = $_FILES['qr_imagen'];

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->response_library->error('Error al subir imagen QR (código ' . (int) $file['error'] . ')', 400);
        }

        $max_bytes = 4 * 1024 * 1024;
        if (isset($file['size']) && (int) $file['size'] > $max_bytes) {
            $this->response_library->error('La imagen QR excede el tamaño máximo permitido (4MB)', 400);
        }

        $original_name = isset($file['name']) ? (string) $file['name'] : '';
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];

        if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
            $this->response_library->error('El tipo de archivo que intentas subir no está permitido.', 400);
        }

        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->response_library->error('Archivo temporal inválido', 400);
        }

        $fh = @fopen($tmp, 'rb');
        if (!$fh) {
            $this->response_library->error('No se pudo leer el archivo subido', 400);
        }
        $head = @fread($fh, 16);
        @fclose($fh);

        $is_jpeg = $head !== false && strlen($head) >= 3 && substr($head, 0, 3) === "\xFF\xD8\xFF";
        $is_png = $head !== false && strlen($head) >= 8 && substr($head, 0, 8) === "\x89PNG\r\n\x1A\n";
        $is_webp = $head !== false && strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP';

        if (in_array($ext, ['jpg', 'jpeg', 'jfif'], true) && !$is_jpeg) {
            $this->response_library->error('La imagen no parece ser un JPEG válido', 400);
        }
        if ($ext === 'png' && !$is_png) {
            $this->response_library->error('La imagen no parece ser un PNG válido', 400);
        }
        if ($ext === 'webp' && !$is_webp) {
            $this->response_library->error('La imagen no parece ser un WEBP válido', 400);
        }

        $upload_dir = FCPATH . 'uploads/opciones_pago';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }

        try {
            $random = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $random = uniqid('', true);
        }

        $file_name = $random . '.' . $ext;
        $dest = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;

        if (!@move_uploaded_file($tmp, $dest)) {
            $this->response_library->error('No se pudo guardar la imagen QR subida', 500);
        }

        return 'uploads/opciones_pago/' . $file_name;
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('opciones_pago.leer');

        $filters = [
            'activo' => $this->input->get('activo'),
            'tipo' => $this->input->get('tipo'),
            'search' => $this->input->get('search')
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $rows = $this->OpcionPago_model->get_all($filters);
        $this->response_library->success($rows);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('opciones_pago.leer');

        $row = $this->OpcionPago_model->get_by_id((int) $id);
        if (!$row) {
            $this->response_library->not_found('Opción de pago no encontrada');
        }

        $this->response_library->success($row);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('opciones_pago.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        $this->validate_required([
            'nombre' => 'Nombre',
            'tipo' => 'Tipo'
        ], $data);

        $nombre = trim((string) $data['nombre']);
        if ($nombre === '' || strlen($nombre) > 120) {
            $this->response_library->error('Nombre inválido', 400);
        }

        $tipo = $this->normalize_tipo($data['tipo']);

        if ($this->OpcionPago_model->get_by_nombre($nombre)) {
            $this->response_library->error('Ya existe una opción de pago con ese nombre', 409);
        }

        $descripcion = array_key_exists('descripcion', $data) ? (string) $data['descripcion'] : null;

        $activo = 1;
        if (isset($data['estado'])) {
            $estado = strtoupper(trim((string) $data['estado']));
            if ($estado === 'ACTIVO') {
                $activo = 1;
            } elseif ($estado === 'INACTIVO') {
                $activo = 0;
            } else {
                $this->response_library->error('Estado no válido. Use ACTIVO o INACTIVO', 400);
            }
        } elseif (isset($data['activo'])) {
            $activo = (int) (bool) $data['activo'];
        }

        if (!in_array($tipo, ['QR', 'MIXTO'], true) && isset($_FILES['qr_imagen']) && !empty($_FILES['qr_imagen']['name'])) {
            $this->response_library->error('Solo puede subir imagen QR si el tipo es QR o MIXTO', 400);
        }

        $qr_url = null;
        if ($is_multipart && in_array($tipo, ['QR', 'MIXTO'], true)) {
            $qr_url = $this->upload_qr_imagen();
        }

        $insert = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => ($descripcion !== null && trim((string) $descripcion) !== '') ? $descripcion : null,
            'qr_imagen_url' => $qr_url,
            'activo' => $activo
        ];

        $id = $this->OpcionPago_model->insert($insert);
        if (!$id) {
            $this->response_library->error('Error al crear opción de pago');
        }

        $this->log_audit('opciones_pago', (int) $id, 'INSERT', null, $insert, 'Opción de pago creada');

        $row = $this->OpcionPago_model->get_by_id((int) $id);
        $this->response_library->success($row, 'Opción de pago creada exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('opciones_pago.actualizar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->OpcionPago_model->get_by_id((int) $id);
        if (!$actual) {
            $this->response_library->not_found('Opción de pago no encontrada');
        }

        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        $update = [];

        if (isset($data['nombre'])) {
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 120) {
                $this->response_library->error('Nombre inválido', 400);
            }
            if ($this->OpcionPago_model->get_by_nombre($nombre, (int) $id)) {
                $this->response_library->error('Ya existe una opción de pago con ese nombre', 409);
            }
            $update['nombre'] = $nombre;
        }

        $next_tipo = isset($data['tipo']) ? $this->normalize_tipo($data['tipo']) : strtoupper((string) $actual->tipo);
        if (isset($data['tipo'])) {
            $update['tipo'] = $next_tipo;
        }

        if (array_key_exists('descripcion', $data)) {
            $desc = $data['descripcion'] !== null ? (string) $data['descripcion'] : null;
            $update['descripcion'] = ($desc !== null && trim((string) $desc) !== '') ? $desc : null;
        }

        if (isset($data['estado'])) {
            $estado = strtoupper(trim((string) $data['estado']));
            if ($estado === 'ACTIVO') {
                $update['activo'] = 1;
            } elseif ($estado === 'INACTIVO') {
                $update['activo'] = 0;
            } else {
                $this->response_library->error('Estado no válido. Use ACTIVO o INACTIVO', 400);
            }
        } elseif (isset($data['activo'])) {
            $update['activo'] = (int) (bool) $data['activo'];
        }

        if (!in_array($next_tipo, ['QR', 'MIXTO'], true) && isset($_FILES['qr_imagen']) && !empty($_FILES['qr_imagen']['name'])) {
            $this->response_library->error('Solo puede subir imagen QR si el tipo es QR o MIXTO', 400);
        }

        if ($is_multipart && in_array($next_tipo, ['QR', 'MIXTO'], true)) {
            $qr_url = $this->upload_qr_imagen();
            if ($qr_url) {
                $update['qr_imagen_url'] = $qr_url;
            }
        }

        if (isset($update['tipo']) && !in_array($update['tipo'], ['QR', 'MIXTO'], true)) {
            $update['qr_imagen_url'] = null;
        }

        if (empty($update)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $ok = $this->OpcionPago_model->update((int) $id, $update);
        if (!$ok) {
            $this->response_library->error('Error al actualizar opción de pago');
        }

        $this->log_audit('opciones_pago', (int) $id, 'UPDATE', (array) $actual, $update, 'Opción de pago actualizada');

        $row = $this->OpcionPago_model->get_by_id((int) $id);
        $this->response_library->success($row, 'Opción de pago actualizada exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('opciones_pago.eliminar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->OpcionPago_model->get_by_id((int) $id);
        if (!$actual) {
            $this->response_library->not_found('Opción de pago no encontrada');
        }

        $ok = $this->OpcionPago_model->disable((int) $id);
        if (!$ok) {
            $this->response_library->error('Error al desactivar opción de pago');
        }

        $this->log_audit('opciones_pago', (int) $id, 'UPDATE', (array) $actual, ['activo' => 0], 'Opción de pago desactivada');
        $this->response_library->success(null, 'Opción de pago desactivada exitosamente');
    }
}
