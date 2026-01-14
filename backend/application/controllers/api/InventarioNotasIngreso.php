<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class InventarioNotasIngreso extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Inventario_model');
        $this->load->model('Inventario_nota_ingreso_model');
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $filters = [
            'almacen_id' => $this->input->get('almacen_id'),
            'estado' => $this->input->get('estado'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta'),
            'limit' => $this->input->get('limit')
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $rows = $this->Inventario_nota_ingreso_model->get_all($filters);
        $this->response_library->success($rows);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        if (!$nota) {
            $this->response_library->not_found('Nota de ingreso no encontrada');
        }

        $this->response_library->success($nota);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('inventario.ajustar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'almacen_id' => 'Almacén',
            'fecha' => 'Fecha'
        ], $data);

        $insert = [
            'almacen_id' => (int) $data['almacen_id'],
            'fecha' => (string) $data['fecha'],
            'estado' => 'borrador',
            'observaciones' => isset($data['observaciones']) && trim((string) $data['observaciones']) !== '' ? trim((string) $data['observaciones']) : null,
            'created_by' => (int) ($this->user_data['user_id'] ?? 0)
        ];

        $detalles = isset($data['detalles']) && is_array($data['detalles']) ? $data['detalles'] : [];

        $id = $this->Inventario_nota_ingreso_model->insert($insert, $detalles);
        if (!$id) {
            $this->response_library->error('Error al crear nota de ingreso');
        }

        $this->log_audit('inventario_notas_ingreso', (int) $id, 'INSERT', null, $insert, 'Nota de ingreso creada');

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        $this->response_library->success($nota, 'Nota de ingreso creada', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('inventario.ajustar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        if (!$actual) {
            $this->response_library->not_found('Nota de ingreso no encontrada');
        }

        if ($actual->estado !== 'borrador') {
            $this->response_library->error('Solo se puede editar una nota en estado borrador', 400);
        }

        $data = $this->get_json_input();

        $update = [];
        if (isset($data['almacen_id'])) {
            $update['almacen_id'] = (int) $data['almacen_id'];
        }
        if (isset($data['fecha'])) {
            $update['fecha'] = (string) $data['fecha'];
        }
        if (array_key_exists('observaciones', $data)) {
            $update['observaciones'] = $data['observaciones'] !== null && trim((string) $data['observaciones']) !== '' ? trim((string) $data['observaciones']) : null;
        }

        $detalles = null;
        if (array_key_exists('detalles', $data)) {
            $detalles = is_array($data['detalles']) ? $data['detalles'] : [];
        }

        if (empty($update) && $detalles === null) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $ok = $this->Inventario_nota_ingreso_model->update((int) $id, $update, $detalles);
        if (!$ok) {
            $this->response_library->error('Error al actualizar nota de ingreso');
        }

        $this->log_audit('inventario_notas_ingreso', (int) $id, 'UPDATE', (array) $actual, $update, 'Nota de ingreso actualizada');

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        $this->response_library->success($nota, 'Nota de ingreso actualizada');
    }

    public function registrar($id) {
        $this->authenticate();
        $this->check_permission('inventario.ajustar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        if (!$nota) {
            $this->response_library->not_found('Nota de ingreso no encontrada');
        }

        if ($nota->estado !== 'borrador') {
            $this->response_library->error('La nota debe estar en borrador para registrarse', 400);
        }

        if (empty($nota->detalles)) {
            $this->response_library->error('La nota no tiene detalles para registrar', 400);
        }

        $usuario_id = (int) ($this->user_data['user_id'] ?? 0);
        $this->db->trans_begin();

        foreach ($nota->detalles as $d) {
            $cantidad = (float) $d->cantidad;
            if ($cantidad <= 0) {
                continue;
            }

            $ok = $this->Inventario_model->registrar_entrada(
                (int) $d->producto_id,
                (int) $nota->almacen_id,
                $cantidad,
                'Nota de ingreso ' . (string) $nota->numero,
                $usuario_id
            );

            if (!$ok) {
                $this->db->trans_rollback();
                $this->response_library->error('Error al registrar entrada de inventario');
            }
        }

        $ok_estado = $this->Inventario_nota_ingreso_model->set_estado((int) $id, [
            'estado' => 'registrado',
            'fecha_registro' => date('Y-m-d H:i:s'),
            'registrado_por' => $usuario_id
        ]);

        if (!$ok_estado) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al actualizar estado de la nota');
        }

        $this->db->trans_commit();

        $this->log_audit('inventario_notas_ingreso', (int) $id, 'UPDATE', ['estado' => $nota->estado], ['estado' => 'registrado'], 'Nota de ingreso registrada');

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        $this->response_library->success($nota, 'Nota de ingreso registrada');
    }

    public function anular($id) {
        $this->authenticate();
        $this->check_permission('inventario.ajustar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        if (!$nota) {
            $this->response_library->not_found('Nota de ingreso no encontrada');
        }

        if ($nota->estado !== 'borrador') {
            $this->response_library->error('Solo se puede anular una nota en borrador', 400);
        }

        $usuario_id = (int) ($this->user_data['user_id'] ?? 0);
        $ok = $this->Inventario_nota_ingreso_model->set_estado((int) $id, [
            'estado' => 'anulado',
            'fecha_anulado' => date('Y-m-d H:i:s'),
            'anulado_por' => $usuario_id
        ]);

        if (!$ok) {
            $this->response_library->error('Error al anular la nota');
        }

        $this->log_audit('inventario_notas_ingreso', (int) $id, 'UPDATE', ['estado' => $nota->estado], ['estado' => 'anulado'], 'Nota de ingreso anulada');

        $nota = $this->Inventario_nota_ingreso_model->get_by_id((int) $id);
        $this->response_library->success($nota, 'Nota de ingreso anulada');
    }
}
