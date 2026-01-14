<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Inventario_model');
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $filters = [
            'almacen_id' => $this->input->get('almacen_id'),
            'sucursal_id' => $this->input->get('sucursal_id'),
            'categoria_id' => $this->input->get('categoria_id'),
            'producto_id' => $this->input->get('producto_id')
        ];

        $bajo_stock = $this->input->get('bajo_stock');
        if ($bajo_stock !== null && $bajo_stock !== '') {
            $filters['bajo_stock'] = ($bajo_stock === '1' || $bajo_stock === 'true' || $bajo_stock === 'on');
        }

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $rows = $this->Inventario_model->get_all($filters);
        $this->response_library->success($rows);
    }

    public function show($producto_id, $almacen_id) {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $row = $this->Inventario_model->get_by_producto_almacen((int) $producto_id, (int) $almacen_id);
        if (!$row) {
            $this->response_library->not_found('Inventario no encontrado');
        }

        $this->response_library->success($row);
    }

    public function movimientos() {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $filters = [
            'producto_id' => $this->input->get('producto_id'),
            'almacen_id' => $this->input->get('almacen_id'),
            'tipo_movimiento' => $this->input->get('tipo_movimiento'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta'),
            'limit' => $this->input->get('limit')
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $rows = $this->Inventario_model->get_movimientos($filters);
        $this->response_library->success($rows);
    }

    public function kardex($producto_id) {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $almacen_id = (int) $this->input->get('almacen_id');
        if (!$almacen_id) {
            $this->response_library->error('almacen_id es requerido', 400);
        }

        $fecha_desde = $this->input->get('fecha_desde');
        $fecha_hasta = $this->input->get('fecha_hasta');

        $rows = $this->Inventario_model->get_kardex((int) $producto_id, $almacen_id, $fecha_desde ?: null, $fecha_hasta ?: null);
        $this->response_library->success($rows);
    }

    public function ajustar() {
        $this->authenticate();
        $this->check_permission('inventario.ajustar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'producto_id' => 'Producto',
            'almacen_id' => 'Almacén',
            'tipo_ajuste' => 'Tipo de ajuste',
            'cantidad' => 'Cantidad',
            'motivo' => 'Motivo'
        ], $data);

        $producto_id = (int) $data['producto_id'];
        $almacen_id = (int) $data['almacen_id'];
        $tipo_ajuste = strtolower(trim((string) $data['tipo_ajuste']));
        $cantidad = (float) $data['cantidad'];
        $motivo = trim((string) $data['motivo']);

        if (!in_array($tipo_ajuste, ['positivo', 'negativo'], true)) {
            $this->response_library->error('tipo_ajuste inválido. Use: positivo | negativo', 400);
        }

        if ($cantidad <= 0) {
            $this->response_library->error('La cantidad debe ser mayor a 0', 400);
        }

        if ($motivo === '') {
            $this->response_library->error('El motivo es requerido', 400);
        }

        $inv = $this->Inventario_model->get_by_producto_almacen($producto_id, $almacen_id);
        if (!$inv && $tipo_ajuste === 'positivo') {
            $insert = [
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'cantidad_total' => 0,
                'cantidad_disponible' => 0,
                'cantidad_reservada' => 0,
                'cantidad_alquilada' => 0,
                'cantidad_en_reparacion' => 0,
                'cantidad_perdida' => 0,
                'stock_minimo' => 0,
                'stock_maximo' => 0
            ];

            $this->db->insert('inventario', $insert);
        }

        $result = $this->Inventario_model->ajustar_inventario(
            $producto_id,
            $almacen_id,
            $tipo_ajuste,
            $cantidad,
            $motivo,
            (int) ($this->user_data['user_id'] ?? 0)
        );

        if (empty($result['success'])) {
            $this->response_library->error($result['mensaje'] ?? 'Error al ajustar inventario', 400);
        }

        $this->response_library->success(null, $result['mensaje'] ?? 'Inventario ajustado correctamente');
    }
}
