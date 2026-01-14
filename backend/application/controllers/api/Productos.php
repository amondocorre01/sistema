<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Productos extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Producto_model');
    }

    private function validate_uso_dias($uso_dias) {
        $allowed = ['TODOS', 'LABORALES'];
        $value = strtoupper(trim((string) $uso_dias));

        if (!in_array($value, $allowed, true)) {
            $this->response_library->error('Uso días no válido. Permitidos: TODOS, LABORALES', 400);
        }

        return $value;
    }

    private function upload_foto_producto() {
        if (!isset($_FILES['foto']) || empty($_FILES['foto']['name'])) {
            return null;
        }

        $file = $_FILES['foto'];

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

        $upload_dir = FCPATH . 'uploads/productos';
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

        return 'uploads/productos/' . $file_name;
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('productos.leer');

        $filters = [
            'activo' => $this->input->get('activo'),
            'categoria_id' => $this->input->get('categoria_id'),
            'tipo' => $this->input->get('tipo'),
            'search' => $this->input->get('search')
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $productos = $this->Producto_model->get_all($filters);
        $this->response_library->success($productos);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('productos.leer');

        $producto = $this->Producto_model->get_by_id($id);
        if (!$producto) {
            $this->response_library->not_found('Producto no encontrado');
        }

        $this->response_library->success($producto);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('productos.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $is_multipart = !empty($_FILES);
        $data = $is_multipart ? $this->input->post(NULL, true) : $this->get_json_input();

        $this->validate_required([
            'nombre' => 'Nombre'
        ], $data);

        $nombre = trim((string) $data['nombre']);

        if ($nombre === '' || strlen($nombre) > 255) {
            $this->response_library->error('Nombre inválido', 400);
        }

        $precio_hora = isset($data['precio_hora']) && $data['precio_hora'] !== '' ? (float) $data['precio_hora'] : 0.0;
        $precio_dia = isset($data['precio_dia']) && $data['precio_dia'] !== '' ? (float) $data['precio_dia'] : 0.0;
        $precio_30dias = isset($data['precio_30dias']) && $data['precio_30dias'] !== '' ? (float) $data['precio_30dias'] : 0.0;
        $precio_reposicion = isset($data['precio_reposicion']) && $data['precio_reposicion'] !== '' ? (float) $data['precio_reposicion'] : 0.0;

        if ($precio_hora < 0 || $precio_dia < 0 || $precio_30dias < 0 || $precio_reposicion < 0) {
            $this->response_library->error('Los precios no pueden ser negativos', 400);
        }

        $uso_dias = isset($data['uso_dias']) ? $this->validate_uso_dias($data['uso_dias']) : 'TODOS';

        $estado = isset($data['estado']) ? strtoupper(trim((string) $data['estado'])) : null;
        $activo = 1;
        if ($estado !== null && $estado !== '') {
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
            $foto_url = $this->upload_foto_producto();
        }

        // Código automático: se asigna luego como el ID del producto.
        // Usamos un código temporal único para poder insertar (codigo es NOT NULL y UNIQUE).
        $codigo_temporal = 'TMP-' . uniqid('', true);

        $producto_data = [
            'codigo' => $codigo_temporal,
            'nombre' => $nombre,
            'descripcion' => isset($data['descripcion']) ? (string) $data['descripcion'] : null,
            'precio_hora' => $precio_hora,
            'precio_alquiler_diario' => $precio_dia,
            'precio_alquiler_mensual' => $precio_30dias,
            'precio_reposicion' => $precio_reposicion,
            'uso_dias' => $uso_dias,
            'imagen_url' => $foto_url,
            'activo' => $activo
        ];

        if (isset($data['precio_dia']) && $data['precio_dia'] !== '' && (!isset($data['precio_semanal']) || $data['precio_semanal'] === '')) {
            $producto_data['precio_alquiler_semanal'] = $precio_dia * 7;
        } elseif (isset($data['precio_semanal']) && $data['precio_semanal'] !== '') {
            $producto_data['precio_alquiler_semanal'] = (float) $data['precio_semanal'];
        }

        $this->db->trans_begin();

        $producto_id = $this->Producto_model->insert($producto_data);
        if (!$producto_id) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al crear producto');
        }

        $almacenes = $this->db->select('id')
            ->from('almacenes')
            ->where('activo', 1)
            ->get()
            ->result();

        if ($almacenes) {
            foreach ($almacenes as $a) {
                $exists = $this->db->select('id')
                    ->from('inventario')
                    ->where('producto_id', (int) $producto_id)
                    ->where('almacen_id', (int) $a->id)
                    ->get()
                    ->row();

                if (!$exists) {
                    $this->db->insert('inventario', [
                        'producto_id' => (int) $producto_id,
                        'almacen_id' => (int) $a->id,
                        'cantidad_total' => 0,
                        'cantidad_disponible' => 0,
                        'cantidad_reservada' => 0,
                        'cantidad_alquilada' => 0,
                        'cantidad_en_reparacion' => 0,
                        'cantidad_perdida' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => 0
                    ]);
                }
            }
        }

        $ok_codigo = $this->Producto_model->update($producto_id, ['codigo' => (string) $producto_id]);
        if (!$ok_codigo) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al asignar código del producto');
        }

        $this->db->trans_commit();

        $this->log_audit('productos', $producto_id, 'INSERT', null, $producto_data, 'Producto creado');

        $producto = $this->Producto_model->get_by_id($producto_id);
        $this->response_library->success($producto, 'Producto creado exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('productos.actualizar');

        if ($this->input->method() !== 'put') {
            $this->response_library->error('Método no permitido', 405);
        }

        $producto_actual = $this->Producto_model->get_by_id($id);
        if (!$producto_actual) {
            $this->response_library->not_found('Producto no encontrado');
        }

        $data = $this->get_json_input();
        $update_data = [];

        // El código es automático (igual al ID) y no se debe permitir cambiarlo.
        if (isset($data['nombre'])) {
            $update_data['nombre'] = trim((string) $data['nombre']);
        }
        if (array_key_exists('descripcion', $data)) {
            $update_data['descripcion'] = $data['descripcion'] !== null ? (string) $data['descripcion'] : null;
        }
        if (isset($data['precio_hora'])) {
            $update_data['precio_hora'] = (float) $data['precio_hora'];
        }
        if (isset($data['precio_dia'])) {
            $update_data['precio_alquiler_diario'] = (float) $data['precio_dia'];
        }
        if (isset($data['precio_30dias'])) {
            $update_data['precio_alquiler_mensual'] = (float) $data['precio_30dias'];
        }
        if (isset($data['precio_reposicion'])) {
            $update_data['precio_reposicion'] = (float) $data['precio_reposicion'];
        }
        if (isset($data['uso_dias'])) {
            $update_data['uso_dias'] = $this->validate_uso_dias($data['uso_dias']);
        }
        if (isset($data['estado'])) {
            $estado = strtoupper(trim((string) $data['estado']));
            if ($estado === 'ACTIVO') {
                $update_data['activo'] = 1;
            } elseif ($estado === 'INACTIVO') {
                $update_data['activo'] = 0;
            } else {
                $this->response_library->error('Estado no válido. Use ACTIVO o INACTIVO', 400);
            }
        } elseif (isset($data['activo'])) {
            $update_data['activo'] = (int) (bool) $data['activo'];
        }

        if (empty($update_data)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $ok = $this->Producto_model->update($id, $update_data);
        if (!$ok) {
            $this->response_library->error('Error al actualizar producto');
        }

        $this->log_audit('productos', $id, 'UPDATE', (array) $producto_actual, $update_data, 'Producto actualizado');

        $producto = $this->Producto_model->get_by_id($id);
        $this->response_library->success($producto, 'Producto actualizado exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('productos.eliminar');

        if ($this->input->method() !== 'delete') {
            $this->response_library->error('Método no permitido', 405);
        }

        $producto_actual = $this->Producto_model->get_by_id($id);
        if (!$producto_actual) {
            $this->response_library->not_found('Producto no encontrado');
        }

        $ok = $this->Producto_model->delete($id);
        if (!$ok) {
            $this->response_library->error('Error al eliminar producto');
        }

        $this->log_audit('productos', $id, 'DELETE', (array) $producto_actual, null, 'Producto eliminado');
        $this->response_library->success(null, 'Producto eliminado exitosamente');
    }
}
