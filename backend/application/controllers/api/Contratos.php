<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Contratos extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Contrato_model');
        $this->load->model('Cliente_model');
        $this->load->model('Producto_model');
    }
    
    public function index() {
        $this->authenticate();
        $this->check_permission('contratos.leer');
        
        $filters = [
            'estado' => $this->input->get('estado'),
            'cliente_id' => $this->input->get('cliente_id'),
            'sucursal_id' => $this->input->get('sucursal_id'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta')
        ];
        
        $filters = array_filter($filters);
        
        $contratos = $this->Contrato_model->get_all($filters);
        
        $this->response_library->success($contratos);
    }
    
    public function show($id) {
        $this->authenticate();
        $this->check_permission('contratos.leer');
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        $productos = $this->Contrato_model->get_productos($id);
        
        $data = [
            'contrato' => $contrato,
            'productos' => $productos
        ];
        
        $this->response_library->success($data);
    }
    
    public function create() {
        $this->authenticate();
        $this->check_permission('contratos.crear');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'cliente_id' => 'Cliente',
            'sucursal_venta_id' => 'Sucursal',
            'almacen_despacho_id' => 'Almacén',
            'tipo_alquiler' => 'Tipo de alquiler',
            'fecha_contrato' => 'Fecha de contrato',
            'fecha_inicio_alquiler' => 'Fecha de inicio',
            'fecha_fin_alquiler' => 'Fecha de fin',
            'productos' => 'Productos'
        ], $data);
        
        if (empty($data['productos']) || !is_array($data['productos'])) {
            $this->response_library->error('Debe agregar al menos un producto');
        }
        
        $subtotal = 0;
        foreach ($data['productos'] as $producto) {
            $subtotal += isset($producto['subtotal']) ? (float) $producto['subtotal'] : 0;
        }
        
        $descuento_monto = isset($data['descuento_monto']) ? $data['descuento_monto'] : 0;
        $total = $subtotal - $descuento_monto;
        
        $tipo_documento = isset($data['tipo_documento']) ? $data['tipo_documento'] : 'contrato';
        $es_proforma = ($tipo_documento === 'proforma');
        
        $contrato_data = [
            'cliente_id' => $data['cliente_id'],
            'sucursal_venta_id' => $data['sucursal_venta_id'],
            'almacen_despacho_id' => $data['almacen_despacho_id'],
            'tipo_alquiler' => $data['tipo_alquiler'],
            'fecha_contrato' => $data['fecha_contrato'],
            'fecha_inicio_alquiler' => $data['fecha_inicio_alquiler'],
            'fecha_fin_alquiler' => $data['fecha_fin_alquiler'],
            'dias_alquiler' => $data['dias_alquiler'],
            'subtotal' => $subtotal,
            'descuento_porcentaje' => isset($data['descuento_porcentaje']) ? $data['descuento_porcentaje'] : 0,
            'descuento_monto' => $descuento_monto,
            'total' => $total,
            'tipo_documento' => $tipo_documento,
            'estado' => $es_proforma ? 'proforma' : 'borrador',
            'observaciones' => isset($data['observaciones']) ? $data['observaciones'] : null,
            'created_by' => $this->user_data['user_id']
        ];

        // Campos extra del wizard (si existen en la BD, se guardarán)
        if (array_key_exists('contacto_obra_nombre', $data)) {
            $contrato_data['contacto_obra_nombre'] = $data['contacto_obra_nombre'] !== null ? trim((string) $data['contacto_obra_nombre']) : null;
        }
        if (array_key_exists('contacto_obra_celular', $data)) {
            $contrato_data['contacto_obra_celular'] = $data['contacto_obra_celular'] !== null ? trim((string) $data['contacto_obra_celular']) : null;
        }
        if (array_key_exists('dueno_obra_nombre', $data)) {
            $contrato_data['dueno_obra_nombre'] = $data['dueno_obra_nombre'] !== null ? trim((string) $data['dueno_obra_nombre']) : null;
        }
        if (array_key_exists('dueno_obra_celular', $data)) {
            $contrato_data['dueno_obra_celular'] = $data['dueno_obra_celular'] !== null ? trim((string) $data['dueno_obra_celular']) : null;
        }
        if (array_key_exists('direccion_entrega_descripcion', $data)) {
            $contrato_data['direccion_entrega_descripcion'] = $data['direccion_entrega_descripcion'] !== null ? (string) $data['direccion_entrega_descripcion'] : null;
        }
        if (array_key_exists('direccion_entrega_lat', $data)) {
            $contrato_data['direccion_entrega_lat'] = $data['direccion_entrega_lat'] !== null && $data['direccion_entrega_lat'] !== '' ? (float) $data['direccion_entrega_lat'] : null;
        }
        if (array_key_exists('direccion_entrega_lng', $data)) {
            $contrato_data['direccion_entrega_lng'] = $data['direccion_entrega_lng'] !== null && $data['direccion_entrega_lng'] !== '' ? (float) $data['direccion_entrega_lng'] : null;
        }

        if (array_key_exists('garantia_monto', $data)) {
            $contrato_data['garantia_monto'] = $data['garantia_monto'] !== null && $data['garantia_monto'] !== '' ? (float) $data['garantia_monto'] : 0;
        }

        if (array_key_exists('transporte_es_propio', $data)) {
            $contrato_data['transporte_es_propio'] = (int) (bool) $data['transporte_es_propio'];
        }
        if (array_key_exists('transporte_id', $data)) {
            $contrato_data['transporte_id'] = $data['transporte_id'] !== null && $data['transporte_id'] !== '' ? (int) $data['transporte_id'] : null;
        }

        if (isset($contrato_data['transporte_es_propio']) && (int) $contrato_data['transporte_es_propio'] === 0) {
            if (!isset($contrato_data['transporte_id']) || empty($contrato_data['transporte_id'])) {
                $this->response_library->error('Debe seleccionar un transporte registrado');
            }
        }
        
        $contrato_id = $this->Contrato_model->insert($contrato_data);
        
        if (!$contrato_id) {
            $this->response_library->error('Error al crear contrato');
        }
        
        $productos_inserted = $this->Contrato_model->insert_productos($contrato_id, $data['productos']);
        
        if (!$productos_inserted) {
            $this->response_library->error('Error al agregar productos al contrato');
        }

        // Si viene desde el wizard con confirmación final, reservar stock automáticamente
        // para que el inventario se descuente (disponible -> reservada) y dejar el contrato aprobado.
        if (!$es_proforma && !empty($data['auto_aprobar'])) {
            $result = $this->Contrato_model->reservar_stock($contrato_id, $data['almacen_despacho_id']);
            if (!$result['success']) {
                $this->response_library->error($result['mensaje']);
            }

            $this->Contrato_model->aprobar($contrato_id, $this->user_data['user_id']);
        }
        
        $contrato = $this->Contrato_model->get_by_id($contrato_id);
        
        $msg = $es_proforma ? 'Proforma creada: ' : 'Contrato creado: ';
        $msg .= $es_proforma ? ($contrato->proforma_numero ?? $contrato_id) : $contrato->numero_contrato;
        
        $this->log_audit('contratos', $contrato_id, 'INSERT', null, $contrato_data, $msg);
        
        $this->response_library->success([
            'contrato_id' => $contrato_id,
            'numero_contrato' => $contrato->numero_contrato,
            'proforma_numero' => $contrato->proforma_numero ?? null,
            'tipo_documento' => $contrato->tipo_documento
        ], $es_proforma ? 'Proforma creada exitosamente' : 'Contrato creado exitosamente', 201);
    }
    
    public function update($id) {
        $this->authenticate();
        $this->check_permission('contratos.actualizar');
        
        if ($this->input->method() !== 'put' && $this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if ($contrato->estado !== 'borrador') {
            $this->response_library->error('Solo se pueden modificar contratos en estado borrador');
        }
        
        $data = $this->get_json_input();
        
        $update_data = array_filter([
            'observaciones' => isset($data['observaciones']) ? $data['observaciones'] : null,
            'garantia_monto' => array_key_exists('garantia_monto', $data) ? ((float) $data['garantia_monto']) : null
        ], function ($v) {
            return $v !== null;
        });
        
        if (!empty($update_data)) {
            $this->Contrato_model->update($id, $update_data);
        }
        
        $this->log_audit('contratos', $id, 'UPDATE', 
            ['estado' => $contrato->estado], 
            $update_data, 
            'Contrato actualizado');
        
        $this->response_library->success(null, 'Contrato actualizado exitosamente');
    }
    
    public function aprobar($id) {
        $this->authenticate();
        $this->check_permission('contratos.aprobar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if ($contrato->estado !== 'borrador') {
            $this->response_library->error('El contrato ya fue aprobado');
        }
        
        $result = $this->Contrato_model->reservar_stock($id, $contrato->almacen_despacho_id);
        
        if (!$result['success']) {
            $this->response_library->error($result['mensaje']);
        }
        
        $this->Contrato_model->aprobar($id, $this->user_data['user_id']);
        
        $this->log_audit('contratos', $id, 'APPROVE', 
            ['estado' => 'borrador'], 
            ['estado' => 'aprobado'], 
            'Contrato aprobado y stock reservado');
        
        $this->response_library->success(null, 'Contrato aprobado y stock reservado exitosamente');
    }

    public function autorizar_entrega($id) {
        $this->authenticate();
        $this->check_permission('contratos.autorizar_entrega');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $contrato = $this->Contrato_model->get_by_id($id);

        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }

        if ($contrato->estado !== 'aprobado') {
            $this->response_library->error('Solo se pueden autorizar contratos en estado aprobado');
        }

        $this->Contrato_model->cambiar_estado($id, 'listo_entrega');

        $this->log_audit('contratos', $id, 'UPDATE',
            ['estado' => 'aprobado'],
            ['estado' => 'listo_entrega'],
            'Contrato autorizado para entrega');

        $this->response_library->success(null, 'Contrato autorizado para entrega');
    }
    
    public function cancelar($id) {
        $this->authenticate();
        $this->check_permission('contratos.cancelar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if (!in_array($contrato->estado, ['borrador', 'aprobado', 'listo_entrega'])) {
            $this->response_library->error('No se puede cancelar un contrato en este estado');
        }
        
        if ($contrato->estado === 'aprobado' || $contrato->estado === 'listo_entrega') {
            $result = $this->Contrato_model->liberar_stock($id, $contrato->almacen_despacho_id);
            
            if (!$result['success']) {
                $this->response_library->error($result['mensaje']);
            }
        }
        
        $this->Contrato_model->cambiar_estado($id, 'cancelado');
        
        $this->log_audit('contratos', $id, 'UPDATE', 
            ['estado' => $contrato->estado], 
            ['estado' => 'cancelado'], 
            'Contrato cancelado');
        
        $this->response_library->success(null, 'Contrato cancelado exitosamente');
    }
    
    public function convertir_proforma($id) {
        $this->authenticate();
        $this->check_permission('contratos.convertir_proforma');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Proforma no encontrada');
        }
        
        if ($contrato->tipo_documento !== 'proforma') {
            $this->response_library->error('Solo se pueden convertir proformas');
        }
        
        if ($contrato->estado !== 'proforma') {
            $this->response_library->error('La proforma ya fue convertida');
        }
        
        $result = $this->Contrato_model->convertir_proforma_a_contrato($id);
        
        if (!$result['success']) {
            $this->response_library->error($result['mensaje']);
        }
        
        $this->log_audit('contratos', $id, 'UPDATE', 
            ['tipo_documento' => 'proforma', 'estado' => 'proforma'], 
            ['tipo_documento' => 'contrato', 'estado' => 'borrador'], 
            'Proforma convertida a contrato: ' . $result['numero_contrato']);
        
        $this->response_library->success([
            'numero_contrato' => $result['numero_contrato']
        ], 'Proforma convertida a contrato exitosamente');
    }
    
    public function cerrar($id) {
        $this->authenticate();
        $this->check_permission('contratos.cerrar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $contrato = $this->Contrato_model->get_by_id($id);
        
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }
        
        if ($contrato->estado !== 'almacenado' && $contrato->estado !== 'finalizado') {
            $this->response_library->error('Solo se pueden cerrar contratos en estado almacenado o finalizado');
        }
        
        $this->Contrato_model->cambiar_estado($id, 'cerrado');
        
        $this->log_audit('contratos', $id, 'UPDATE', 
            ['estado' => $contrato->estado], 
            ['estado' => 'cerrado'], 
            'Contrato cerrado manualmente');
        
        $this->response_library->success(null, 'Contrato cerrado exitosamente');
    }
}
