<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transportes extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Transporte_model');
    }

    private function upload_foto_vehiculo() {
        if (!isset($_FILES['foto_vehiculo']) || empty($_FILES['foto_vehiculo']['name'])) {
            return null;
        }

        $file = $_FILES['foto_vehiculo'];

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->response_library->error('Error al subir foto (código ' . (int) $file['error'] . ')', 400);
        }

        $max_bytes = 4 * 1024 * 1024;
        if (isset($file['size']) && (int) $file['size'] > $max_bytes) {
            $this->response_library->error('La foto excede el tamaño máximo permitido (4MB)', 400);
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
            $this->response_library->error('La foto no parece ser un JPEG válido', 400);
        }
        if ($ext === 'png' && !$is_png) {
            $this->response_library->error('La foto no parece ser un PNG válido', 400);
        }
        if ($ext === 'webp' && !$is_webp) {
            $this->response_library->error('La foto no parece ser un WEBP válido', 400);
        }

        $upload_dir = FCPATH . 'uploads/transportes';
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
            $this->response_library->error('No se pudo guardar la foto subida', 500);
        }

        return 'uploads/transportes/' . $file_name;
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('transportes.leer');

        $filters = [
            'activo' => $this->input->get('activo'),
            'search' => $this->input->get('search')
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $rows = $this->Transporte_model->get_all($filters);
        $this->response_library->success($rows);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('transportes.leer');

        $row = $this->Transporte_model->get_by_id((int) $id);
        if (!$row) {
            $this->response_library->not_found('Transporte no encontrado');
        }

        $this->response_library->success($row);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('transportes.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        $this->validate_required([
            'nombre_completo' => 'Nombre completo',
            'ci' => 'C.I',
            'placa' => 'Número de placa'
        ], $data);

        $nombre_completo = trim((string) $data['nombre_completo']);
        $ci = trim((string) $data['ci']);
        $placa = trim((string) $data['placa']);

        if ($nombre_completo === '' || strlen($nombre_completo) > 150) {
            $this->response_library->error('Nombre completo inválido', 400);
        }
        if ($ci === '' || strlen($ci) > 30) {
            $this->response_library->error('C.I inválido', 400);
        }
        if ($placa === '' || strlen($placa) > 30) {
            $this->response_library->error('Número de placa inválido', 400);
        }

        if ($this->Transporte_model->get_by_ci($ci)) {
            $this->response_library->error('Ya existe un transporte con ese C.I', 409);
        }
        if ($this->Transporte_model->get_by_placa($placa)) {
            $this->response_library->error('Ya existe un transporte con esa placa', 409);
        }

        $celular = isset($data['celular']) ? trim((string) $data['celular']) : null;
        $capacidad_carga = isset($data['capacidad_carga']) && $data['capacidad_carga'] !== '' ? (float) $data['capacidad_carga'] : null;
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

        $foto_url = null;
        if ($is_multipart) {
            $foto_url = $this->upload_foto_vehiculo();
        }

        $insert = [
            'nombre_completo' => $nombre_completo,
            'ci' => $ci,
            'celular' => ($celular !== null && $celular !== '') ? $celular : null,
            'placa' => $placa,
            'capacidad_carga' => $capacidad_carga,
            'foto_vehiculo_url' => $foto_url,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'activo' => $activo
        ];

        $id = $this->Transporte_model->insert($insert);
        if (!$id) {
            $this->response_library->error('Error al crear transporte');
        }

        $this->log_audit('transportes', (int) $id, 'INSERT', null, $insert, 'Transporte creado');

        $row = $this->Transporte_model->get_by_id((int) $id);
        $this->response_library->success($row, 'Transporte creado exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('transportes.actualizar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->Transporte_model->get_by_id((int) $id);
        if (!$actual) {
            $this->response_library->not_found('Transporte no encontrado');
        }

        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        $update = [];

        if (isset($data['nombre_completo'])) {
            $nombre_completo = trim((string) $data['nombre_completo']);
            if ($nombre_completo === '' || strlen($nombre_completo) > 150) {
                $this->response_library->error('Nombre completo inválido', 400);
            }
            $update['nombre_completo'] = $nombre_completo;
        }

        if (isset($data['ci'])) {
            $ci = trim((string) $data['ci']);
            if ($ci === '' || strlen($ci) > 30) {
                $this->response_library->error('C.I inválido', 400);
            }
            if ($this->Transporte_model->get_by_ci($ci, (int) $id)) {
                $this->response_library->error('Ya existe un transporte con ese C.I', 409);
            }
            $update['ci'] = $ci;
        }

        if (isset($data['placa'])) {
            $placa = trim((string) $data['placa']);
            if ($placa === '' || strlen($placa) > 30) {
                $this->response_library->error('Número de placa inválido', 400);
            }
            if ($this->Transporte_model->get_by_placa($placa, (int) $id)) {
                $this->response_library->error('Ya existe un transporte con esa placa', 409);
            }
            $update['placa'] = $placa;
        }

        if (array_key_exists('celular', $data)) {
            $celular = $data['celular'] !== null ? trim((string) $data['celular']) : null;
            $update['celular'] = ($celular !== null && $celular !== '') ? $celular : null;
        }

        if (array_key_exists('capacidad_carga', $data)) {
            $update['capacidad_carga'] = ($data['capacidad_carga'] !== null && $data['capacidad_carga'] !== '') ? (float) $data['capacidad_carga'] : null;
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

        if ($is_multipart) {
            $foto_url = $this->upload_foto_vehiculo();
            if ($foto_url) {
                $update['foto_vehiculo_url'] = $foto_url;
            }
        }

        if (empty($update)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $ok = $this->Transporte_model->update((int) $id, $update);
        if (!$ok) {
            $this->response_library->error('Error al actualizar transporte');
        }

        $this->log_audit('transportes', (int) $id, 'UPDATE', (array) $actual, $update, 'Transporte actualizado');

        $row = $this->Transporte_model->get_by_id((int) $id);
        $this->response_library->success($row, 'Transporte actualizado exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('transportes.eliminar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->Transporte_model->get_by_id((int) $id);
        if (!$actual) {
            $this->response_library->not_found('Transporte no encontrado');
        }

        $ok = $this->Transporte_model->disable((int) $id);
        if (!$ok) {
            $this->response_library->error('Error al desactivar transporte');
        }

        $this->log_audit('transportes', (int) $id, 'UPDATE', (array) $actual, ['activo' => 0], 'Transporte desactivado');
        $this->response_library->success(null, 'Transporte desactivado exitosamente');
    }
}
