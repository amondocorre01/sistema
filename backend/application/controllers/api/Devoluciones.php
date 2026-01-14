<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Devoluciones extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Devolucion_model');
        $this->load->model('Contrato_model');
    }
    
    public function index() {
        $this->authenticate();
        $this->check_permission('devoluciones.ver_historial');
        
        $filters = [
            'estado' => $this->input->get('estado'),
            'contrato_id' => $this->input->get('contrato_id'),
            'almacen_id' => $this->input->get('almacen_id'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta')
        ];
        
        $filters = array_filter($filters);
        
        $devoluciones = $this->Devolucion_model->get_all($filters);
        
        $this->response_library->success($devoluciones);
    }
    
    public function show($id) {
        $this->authenticate();
        $this->check_permission('devoluciones.ver_historial');
        
        $devolucion = $this->Devolucion_model->get_by_id($id);
        
        if (!$devolucion) {
            $this->response_library->not_found('Devolución no encontrada');
        }
        
        $detalles = $this->Devolucion_model->get_detalles($id);
        
        $data = [
            'devolucion' => $devolucion,
            'detalles' => $detalles
        ];
        
        $this->response_library->success($data);
    }
    
    public function pendientes() {
        $this->authenticate();
        $this->check_permission('devoluciones.validar');
        
        $devoluciones = $this->Devolucion_model->get_pendientes();
        
        $this->response_library->success($devoluciones);
    }
    
    public function productos_recepcionados() {
        $this->authenticate();
        $this->check_permission('devoluciones.ver_historial');
        
        $filters = [
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta'),
            'contrato_id' => $this->input->get('contrato_id'),
            'cliente_id' => $this->input->get('cliente_id'),
            'producto_id' => $this->input->get('producto_id'),
            'estado' => $this->input->get('estado')
        ];
        
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });
        
        $productos = $this->Devolucion_model->get_productos_recepcionados($filters);
        
        $this->response_library->success($productos);
    }
    
    public function registrar() {
        $this->authenticate();
        $this->check_permission('devoluciones.registrar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'contrato_id' => 'Contrato',
            'almacen_id' => 'Almacén',
            'tipo_devolucion' => 'Tipo de devolución',
            'fecha_devolucion' => 'Fecha de devolución',
            'productos' => 'Productos'
        ], $data);
        
        if (empty($data['productos']) || !is_array($data['productos'])) {
            $this->response_library->error('Debe agregar al menos un producto');
        }
        
        $contrato = $this->Contrato_model->get_by_id($data['contrato_id']);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if (!in_array($contrato->estado, ['en_curso', 'parcialmente_devuelto'])) {
            $this->response_library->error('El contrato debe estar en curso para registrar devoluciones');
        }

        $tipo_devolucion = isset($data['tipo_devolucion']) ? (string) $data['tipo_devolucion'] : '';
        $contrato_extension_id = null;

        if ($tipo_devolucion === 'extension') {
            if (!isset($data['extension']) || !is_array($data['extension'])) {
                $this->response_library->error('Debe enviar los datos de extensión');
            }

            $this->validate_required([
                'fecha_inicio_alquiler' => 'Fecha inicio extensión',
                'fecha_fin_alquiler' => 'Fecha fin extensión',
                'dias_alquiler' => 'Días extensión',
                'productos' => 'Productos extensión'
            ], $data['extension']);

            if (empty($data['extension']['productos']) || !is_array($data['extension']['productos'])) {
                $this->response_library->error('Debe enviar los productos de extensión');
            }

            $cp_list = $this->Contrato_model->get_productos((int) $data['contrato_id']);
            $cp_map = [];
            foreach ($cp_list as $cp) {
                $cp_map[(int) $cp->producto_id] = $cp;
            }

            $dev_sums = [];
            $perd_sums = [];
            foreach ($data['productos'] as $p) {
                $pid = (int) ($p['producto_id'] ?? 0);
                $qty = (float) ($p['cantidad_devuelta'] ?? 0);
                $estado_mat = isset($p['estado_material']) ? (string) $p['estado_material'] : 'bueno';
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }

                if ($estado_mat === 'perdido') {
                    if (!isset($perd_sums[$pid])) $perd_sums[$pid] = 0;
                    $perd_sums[$pid] += $qty;
                } else {
                    if (!isset($dev_sums[$pid])) $dev_sums[$pid] = 0;
                    $dev_sums[$pid] += $qty;
                }
            }

            $remaining = [];
            foreach ($cp_map as $pid => $cp) {
                $entregado = isset($cp->cantidad_entregada) ? (float) $cp->cantidad_entregada : 0;
                if ($entregado <= 0 && isset($cp->cantidad_contratada)) {
                    $entregado = (float) $cp->cantidad_contratada;
                }
                $ya_devuelto = isset($cp->cantidad_devuelta) ? (float) $cp->cantidad_devuelta : 0;
                $ya_perdido = isset($cp->cantidad_perdida) ? (float) $cp->cantidad_perdida : 0;
                $dev_now = isset($dev_sums[$pid]) ? (float) $dev_sums[$pid] : 0;
                $perd_now = isset($perd_sums[$pid]) ? (float) $perd_sums[$pid] : 0;

                $saldo = $entregado - ($ya_devuelto + $ya_perdido + $dev_now + $perd_now);
                if ($saldo > 0.0001) {
                    $remaining[$pid] = $saldo;
                }
            }

            if (empty($remaining)) {
                $this->response_library->error('No existe saldo en obra para generar una extensión');
            }

            $ext_productos_payload = $data['extension']['productos'];
            $ext_map = [];
            foreach ($ext_productos_payload as $p) {
                $pid = (int) ($p['producto_id'] ?? 0);
                if ($pid <= 0) continue;
                $ext_map[$pid] = $p;
            }

            foreach ($remaining as $pid => $saldo_qty) {
                if (!isset($ext_map[$pid])) {
                    $this->response_library->error('Los productos de extensión no coinciden con el saldo en obra');
                }
                $qty_sent = (float) ($ext_map[$pid]['cantidad'] ?? 0);
                if (abs($qty_sent - (float) $saldo_qty) > 0.01) {
                    $this->response_library->error('Las cantidades de extensión no coinciden con el saldo en obra');
                }
            }

            foreach ($ext_map as $pid => $p) {
                if (!isset($remaining[$pid])) {
                    $this->response_library->error('Los productos de extensión no coinciden con el saldo en obra');
                }
            }

            $subtotal = 0;
            foreach ($ext_productos_payload as $p) {
                $subtotal += isset($p['subtotal']) ? (float) $p['subtotal'] : 0;
            }

            $contrato_ext_data = [
                'cliente_id' => $contrato->cliente_id,
                'sucursal_venta_id' => $contrato->sucursal_venta_id,
                'almacen_despacho_id' => $contrato->almacen_despacho_id,
                'tipo_alquiler' => $contrato->tipo_alquiler,
                'fecha_contrato' => date('Y-m-d'),
                'fecha_inicio_alquiler' => $data['extension']['fecha_inicio_alquiler'],
                'fecha_fin_alquiler' => $data['extension']['fecha_fin_alquiler'],
                'dias_alquiler' => (int) $data['extension']['dias_alquiler'],
                'subtotal' => $subtotal,
                'descuento_porcentaje' => 0,
                'descuento_monto' => 0,
                'total' => $subtotal,
                'tipo_documento' => 'contrato',
                'estado' => 'borrador',
                'contrato_origen_id' => (int) $contrato->id,
                'observaciones' => 'Extensión de contrato: ' . (string) ($contrato->numero_contrato ?? $contrato->id),
                'created_by' => $this->user_data['user_id']
            ];

            $copy_fields = [
                'contacto_obra_nombre',
                'contacto_obra_celular',
                'dueno_obra_nombre',
                'dueno_obra_celular',
                'direccion_entrega_descripcion',
                'direccion_entrega_lat',
                'direccion_entrega_lng',
                'transporte_es_propio',
                'transporte_id'
            ];

            foreach ($copy_fields as $f) {
                if (property_exists($contrato, $f)) {
                    $contrato_ext_data[$f] = $contrato->{$f};
                }
            }

            $contrato_extension_id = $this->Contrato_model->insert($contrato_ext_data);
            if (!$contrato_extension_id) {
                $this->response_library->error('Error al generar contrato de extensión');
            }

            $ok_prod = $this->Contrato_model->insert_productos((int) $contrato_extension_id, $ext_productos_payload);
            if (!$ok_prod) {
                $this->response_library->error('Error al generar productos del contrato de extensión');
            }
        }
        
        $devolucion_data = [
            'contrato_id' => $data['contrato_id'],
            'almacen_id' => $data['almacen_id'],
            'tipo_devolucion' => $data['tipo_devolucion'],
            'fecha_devolucion' => $data['fecha_devolucion'],
            'recibido_por' => $this->user_data['user_id'],
            'estado' => 'registrado',
            'observaciones_almacen' => isset($data['observaciones_almacen']) ? $data['observaciones_almacen'] : null
        ];

        if ($contrato_extension_id !== null) {
            $devolucion_data['contrato_extension_id'] = (int) $contrato_extension_id;
        }
        
        $devolucion_id = $this->Devolucion_model->insert($devolucion_data);
        
        if (!$devolucion_id) {
            $this->response_library->error('Error al registrar devolución');
        }
        
        $detalles_inserted = $this->Devolucion_model->insert_detalles($devolucion_id, $data['productos']);
        
        if (!$detalles_inserted) {
            $this->response_library->error('Error al agregar detalles de devolución');
        }

        $total_cargos = $this->Devolucion_model->calcular_total_cargos($devolucion_id);
        
        $this->log_audit('devoluciones', $devolucion_id, 'INSERT', null, $devolucion_data, 
            'Devolución registrada por almacenero');
        
        $this->response_library->success([
            'devolucion_id' => $devolucion_id,
            'contrato_extension_id' => $contrato_extension_id,
            'total_cargos' => (float) $total_cargos
        ], 'Devolución registrada exitosamente. Pendiente de validación.', 201);
    }
    
    public function validar($id) {
        $this->authenticate();
        $this->check_permission('devoluciones.validar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $devolucion = $this->Devolucion_model->get_by_id($id);
        
        if (!$devolucion) {
            $this->response_library->not_found('Devolución no encontrada');
        }
        
        if ($devolucion->estado !== 'registrado') {
            $this->response_library->error('La devolución ya fue validada o rechazada');
        }
        
        $data = $this->get_json_input();
        
        $observaciones = isset($data['observaciones_validacion']) ? $data['observaciones_validacion'] : null;
        
        $ok = $this->Devolucion_model->validar($id, $this->user_data['user_id'], $observaciones);
        
        if (!$ok) {
            $this->response_library->error('No se pudo validar la devolución');
        }
        
        $this->log_audit('devoluciones', $id, 'VALIDATE', 
            ['estado' => 'registrado'], 
            ['estado' => 'validado'], 
            'Devolución validada por secretaria');
        
        $this->response_library->success(null, 'Devolución validada correctamente. Inventario actualizado.');
    }
    
    public function productos_contrato($contrato_id) {
        $this->authenticate();
        $this->check_permission('devoluciones.registrar');
        
        $productos = $this->Devolucion_model->get_productos_contrato($contrato_id);
        
        $this->response_library->success($productos);
    }
    
    public function subir_foto() {
        $this->authenticate();
        $this->check_permission('devoluciones.subir_fotos');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        if (empty($_FILES['foto'])) {
            $this->response_library->error('No se recibió ninguna foto');
        }
        
        $devolucion_id = $this->input->post('devolucion_id');
        $devolucion_detalle_id = $this->input->post('devolucion_detalle_id');
        $producto_id = $this->input->post('producto_id');
        $tipo_foto = $this->input->post('tipo_foto') ?: 'general';
        $descripcion = $this->input->post('descripcion');
        
        if (!$devolucion_id) {
            $this->response_library->error('ID de devolución requerido');
        }
        
        $devolucion = $this->Devolucion_model->get_by_id($devolucion_id);
        if (!$devolucion) {
            $this->response_library->not_found('Devolución no encontrada');
        }
        
        $upload_path = './uploads/devoluciones/';
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        $original_name = isset($_FILES['foto']['name']) ? (string) $_FILES['foto']['name'] : '';
        $client_mime = isset($_FILES['foto']['type']) ? (string) $_FILES['foto']['type'] : '';
        $tmp_name = isset($_FILES['foto']['tmp_name']) ? (string) $_FILES['foto']['tmp_name'] : '';
        $file_error = isset($_FILES['foto']['error']) ? (int) $_FILES['foto']['error'] : UPLOAD_ERR_NO_FILE;
        $file_size = isset($_FILES['foto']['size']) ? (int) $_FILES['foto']['size'] : 0;
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if ($file_error !== UPLOAD_ERR_OK) {
            $this->response_library->error('Error al subir archivo. Código: ' . $file_error);
        }

        if (!$tmp_name || !is_uploaded_file($tmp_name)) {
            $this->response_library->error('No se pudo procesar el archivo subido');
        }

        if ($file_size <= 0 || $file_size > (10 * 1024 * 1024)) {
            $this->response_library->error('El archivo supera el tamaño permitido (máx 10MB)');
        }

        $img_info = @getimagesize($tmp_name);
        if ($img_info === false || empty($img_info['mime'])) {
            $this->response_library->error('El archivo subido no es una imagen válida. Formatos permitidos: JPG, JPEG, PNG, WEBP, GIF. Archivo: ' . $original_name . ' (' . $ext . ') MIME: ' . $client_mime);
        }

        $mime = strtolower((string) $img_info['mime']);
        $mime_to_ext = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!isset($mime_to_ext[$mime])) {
            $this->response_library->error('El tipo de archivo que intentas subir no está permitido. Formatos permitidos: JPG, JPEG, PNG, WEBP, GIF. Archivo: ' . $original_name . ' (' . $ext . ') MIME: ' . $mime);
        }

        $final_ext = $mime_to_ext[$mime];
        $random_name = bin2hex(random_bytes(16)) . '.' . $final_ext;
        $dest_path = rtrim($upload_path, '/\\') . '/' . $random_name;

        if (!@move_uploaded_file($tmp_name, $dest_path)) {
            $this->response_library->error('No se pudo guardar la imagen en el servidor');
        }

        $foto_url = 'uploads/devoluciones/' . $random_name;
        
        $foto_data = [
            'devolucion_id' => $devolucion_id,
            'devolucion_detalle_id' => $devolucion_detalle_id ?: null,
            'producto_id' => $producto_id ?: null,
            'foto_url' => $foto_url,
            'tipo_foto' => $tipo_foto,
            'descripcion' => $descripcion,
            'usuario_id' => $this->user_data['user_id']
        ];
        
        $foto_id = $this->Devolucion_model->insert_foto($foto_data);
        
        if (!$foto_id) {
            @unlink($dest_path);
            $this->response_library->error('Error al guardar la foto en la base de datos');
        }
        
        $this->response_library->success([
            'foto_id' => $foto_id,
            'foto_url' => $foto_url
        ], 'Foto subida correctamente', 201);
    }
    
    public function fotos($devolucion_id) {
        $this->authenticate();
        $this->check_permission('devoluciones.ver_historial');
        
        $fotos = $this->Devolucion_model->get_fotos($devolucion_id);
        
        $this->response_library->success($fotos);
    }
    
    public function cargos($devolucion_id) {
        $this->authenticate();
        // Para cobros por devolución, secretaría necesita ver cargos.
        // Aceptamos cualquiera de los permisos: ver historial o registrar pagos.
        $has_perm = false;
        if (($this->user_data['rol'] ?? '') === 'administrador') {
            $has_perm = true;
        } else {
            $perms = $this->user_data['permisos'] ?? [];
            if (in_array('devoluciones.ver_historial', $perms) || in_array('pagos.registrar', $perms)) {
                $has_perm = true;
            }
        }
        if (!$has_perm) {
            $this->response_library->forbidden('No tiene permisos para esta acción');
        }
        
        $cargos = $this->Devolucion_model->get_cargos($devolucion_id);
        
        $this->response_library->success($cargos);
    }
}
