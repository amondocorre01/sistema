<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Entregas extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Entrega_model');
        $this->load->model('Contrato_model');
    }

    private function upload_foto_entrega() {
        if (!isset($_FILES['foto_entrega']) || empty($_FILES['foto_entrega']['name'])) {
            return null;
        }

        $file = $_FILES['foto_entrega'];

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

        $upload_dir = FCPATH . 'uploads/entregas';
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

        return 'uploads/entregas/' . $file_name;
    }

    private function upload_foto_vehiculo_carga() {
        if (!isset($_FILES['foto_vehiculo_carga']) || empty($_FILES['foto_vehiculo_carga']['name'])) {
            return null;
        }

        $file = $_FILES['foto_vehiculo_carga'];

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

        $upload_dir = FCPATH . 'uploads/entregas';
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

        return 'uploads/entregas/' . $file_name;
    }
    
    public function index() {
        $this->authenticate();
        $has_perm = false;
        if (($this->user_data['rol'] ?? '') === 'administrador') {
            $has_perm = true;
        } else {
            $perms = $this->user_data['permisos'] ?? [];
            if (in_array('entregas.leer', $perms) || in_array('entregas.validar', $perms) || in_array('entregas.registrar', $perms)) {
                $has_perm = true;
            }
        }
        if (!$has_perm) {
            $this->response_library->forbidden('No tiene permisos para esta acción');
        }
        
        $filters = [
            'estado' => $this->input->get('estado'),
            'contrato_id' => $this->input->get('contrato_id'),
            'almacen_id' => $this->input->get('almacen_id'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta')
        ];
        
        $filters = array_filter($filters);
        
        $entregas = $this->Entrega_model->get_all($filters);
        
        $this->response_library->success($entregas);
    }

    public function contratos_pendientes() {
        $this->authenticate();
        $this->check_permission('entregas.registrar');

        $sucursal_id = (int) $this->get_sucursal_activa(true);

        $rows = $this->Contrato_model->get_all([
            'estado' => 'listo_entrega',
            'sucursal_id' => $sucursal_id
        ]);

        // Solo contratos (no proformas)
        $rows = array_values(array_filter($rows, function ($r) {
            return isset($r->tipo_documento) ? ((string) $r->tipo_documento === 'contrato') : true;
        }));

        $this->response_library->success($rows);
    }
    
    public function show($id) {
        $this->authenticate();
        // Para validar una entrega, secretaria necesita ver el detalle.
        // Aceptamos cualquiera de los permisos: leer o validar.
        $has_perm = false;
        if (($this->user_data['rol'] ?? '') === 'administrador') {
            $has_perm = true;
        } else {
            $perms = $this->user_data['permisos'] ?? [];
            if (in_array('entregas.leer', $perms) || in_array('entregas.validar', $perms)) {
                $has_perm = true;
            }
        }
        if (!$has_perm) {
            $this->response_library->forbidden('No tiene permisos para esta acción');
        }
        
        $entrega = $this->Entrega_model->get_by_id($id);
        
        if (!$entrega) {
            $this->response_library->not_found('Entrega no encontrada');
        }
        
        $detalles = $this->Entrega_model->get_detalles($id);
        
        $data = [
            'entrega' => $entrega,
            'detalles' => $detalles
        ];
        
        $this->response_library->success($data);
    }
    
    public function pendientes() {
        $this->authenticate();
        $this->check_permission('entregas.validar');
        
        $entregas = $this->Entrega_model->get_pendientes();
        
        $this->response_library->success($entregas);
    }
    
    public function registrar() {
        $this->authenticate();
        $this->check_permission('entregas.registrar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        if ($is_multipart && isset($data['productos']) && is_string($data['productos'])) {
            $decoded = json_decode($data['productos'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['productos'] = $decoded;
            }
        }
        
        $this->validate_required([
            'contrato_id' => 'Contrato',
            'almacen_id' => 'Almacén',
            'fecha_entrega' => 'Fecha de entrega',
            'productos' => 'Productos'
        ], $data);
        
        if (empty($data['productos']) || !is_array($data['productos'])) {
            $this->response_library->error('Debe agregar al menos un producto');
        }

        // Por control interno: el almacenero no debe modificar la cantidad entregada.
        // Se fuerza a que sea igual a la cantidad programada.
        $fixed_productos = [];
        foreach ($data['productos'] as $p) {
            $cantidad_programada = isset($p['cantidad_programada']) ? (float) $p['cantidad_programada'] : 0;
            $fixed_productos[] = [
                'producto_id' => isset($p['producto_id']) ? (int) $p['producto_id'] : 0,
                'cantidad_programada' => $cantidad_programada,
                'cantidad_entregada' => $cantidad_programada,
                'observaciones' => isset($p['observaciones']) ? $p['observaciones'] : null
            ];
        }
        $data['productos'] = $fixed_productos;
        
        $contrato = $this->Contrato_model->get_by_id($data['contrato_id']);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if ($contrato->estado !== 'listo_entrega') {
            $this->response_library->error('El contrato debe estar autorizado (Listo para Entrega) para registrar entregas');
        }
        
        $foto_url = null;
        $foto_vehiculo_url = null;
        if ($is_multipart) {
            $foto_url = $this->upload_foto_entrega();
            $foto_vehiculo_url = $this->upload_foto_vehiculo_carga();
        }

        $entrega_data = [
            'contrato_id' => $data['contrato_id'],
            'almacen_id' => $data['almacen_id'],
            'fecha_entrega' => $data['fecha_entrega'],
            'entregado_por' => $this->user_data['user_id'],
            'estado' => 'registrado',
            'observaciones_almacen' => isset($data['observaciones_almacen']) ? $data['observaciones_almacen'] : null,
            'foto_entrega_url' => $foto_url,
            'foto_vehiculo_carga_url' => $foto_vehiculo_url
        ];

        // Compatibilidad: si aún no se ejecutaron las migraciones en BD, evitamos error 1054.
        if (!$this->db->field_exists('foto_entrega_url', 'entregas')) {
            unset($entrega_data['foto_entrega_url']);
        }
        if (!$this->db->field_exists('foto_vehiculo_carga_url', 'entregas')) {
            unset($entrega_data['foto_vehiculo_carga_url']);
        }
        
        $entrega_id = $this->Entrega_model->insert($entrega_data);
        
        if (!$entrega_id) {
            $this->response_library->error('Error al registrar entrega');
        }
        
        $detalles_inserted = $this->Entrega_model->insert_detalles($entrega_id, $data['productos']);
        
        if (!$detalles_inserted) {
            $this->response_library->error('Error al agregar detalles de entrega');
        }
        
        $this->Contrato_model->cambiar_estado($data['contrato_id'], 'entregado');
        
        $this->log_audit('entregas', $entrega_id, 'INSERT', null, $entrega_data, 
            'Entrega registrada por almacenero');
        
        $this->response_library->success([
            'entrega_id' => $entrega_id
        ], 'Entrega registrada exitosamente. Pendiente de validación.', 201);
    }
    
    public function validar($id) {
        $this->authenticate();
        $this->check_permission('entregas.validar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $entrega = $this->Entrega_model->get_by_id($id);
        
        if (!$entrega) {
            $this->response_library->not_found('Entrega no encontrada');
        }
        
        if ($entrega->estado !== 'registrado') {
            $this->response_library->error('La entrega ya fue validada o rechazada');
        }
        
        $data = $this->get_json_input();
        
        $observaciones = isset($data['observaciones_validacion']) ? $data['observaciones_validacion'] : null;
        
        $result = $this->Entrega_model->validar($id, $this->user_data['user_id'], $observaciones);
        
        if (!$result['success']) {
            $this->response_library->error($result['mensaje']);
        }
        
        $this->log_audit('entregas', $id, 'VALIDATE', 
            ['estado' => 'registrado'], 
            ['estado' => 'validado'], 
            'Entrega validada por secretaria');
        
        $this->response_library->success(null, 'Entrega validada correctamente. Inventario actualizado.');
    }
}
