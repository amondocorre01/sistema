<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Clientes extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Cliente_model');
        $this->load->model('Cliente_referencia_model');
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('clientes.leer');

        $filters = [
            'activo' => $this->input->get('activo'),
            'tipo_documento' => $this->input->get('tipo_documento'),
            'search' => $this->input->get('search')
        ];

        $clientes = $this->Cliente_model->get_all(array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        }));

        $this->response_library->success($clientes);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('clientes.leer');

        $cliente = $this->Cliente_model->get_by_id((int) $id);

        if (!$cliente) {
            $this->response_library->not_found('Cliente no encontrado');
        }

        $cliente->referencias = $this->Cliente_referencia_model->get_by_cliente($cliente->id);

        $this->response_library->success($cliente);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('clientes.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'tipo_documento' => 'Tipo de documento',
            'numero_documento' => 'Número de documento',
            'razon_social' => 'Razón social'
        ], $data);

        $tipo_documento = strtoupper(trim((string) $data['tipo_documento']));
        $numero_documento = trim((string) $data['numero_documento']);

        $cliente_existente = $this->Cliente_model->get_by_documento($tipo_documento, $numero_documento);
        if ($cliente_existente) {
            $this->response_library->error('Ya existe un cliente con ese documento', 400);
        }

        $cliente_data = $this->map_payload_to_cliente($data);

        if ($this->has_column('clientes', 'created_by')) {
            $cliente_data['created_by'] = $this->user_data['user_id'];
        }

        $this->db->trans_begin();

        $cliente_id = $this->Cliente_model->insert($cliente_data);

        if (!$cliente_id) {
            $this->db->trans_rollback();
            $this->response_library->error('No se pudo crear el cliente');
        }

        if (!empty($data['referencias']) && is_array($data['referencias'])) {
            $this->Cliente_referencia_model->insert_many($cliente_id, $data['referencias']);
        }

        $this->db->trans_commit();

        $this->log_audit('clientes', $cliente_id, 'INSERT', null, $cliente_data, 'Cliente creado');

        $this->response_library->success(['id' => $cliente_id], 'Cliente creado exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('clientes.actualizar');

        if ($this->input->method() !== 'post' && $this->input->method() !== 'put') {
            $this->response_library->error('Método no permitido', 405);
        }

        $cliente = $this->Cliente_model->get_by_id((int) $id);

        if (!$cliente) {
            $this->response_library->not_found('Cliente no encontrado');
        }

        $data = $this->get_json_input();

        if (isset($data['numero_documento']) || isset($data['tipo_documento'])) {
            $tipo = isset($data['tipo_documento']) ? strtoupper(trim((string) $data['tipo_documento'])) : $cliente->tipo_documento;
            $numero = isset($data['numero_documento']) ? trim((string) $data['numero_documento']) : $cliente->numero_documento;

            $duplicado = $this->Cliente_model->get_by_documento($tipo, $numero);
            if ($duplicado && (int) $duplicado->id !== (int) $cliente->id) {
                $this->response_library->error('Otro cliente ya usa ese documento', 400);
            }
        }

        $update_data = $this->map_payload_to_cliente($data, false);

        if (empty($update_data)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        if ($this->has_column('clientes', 'updated_by')) {
            $update_data['updated_by'] = $this->user_data['user_id'];
        }

        $this->db->trans_begin();

        $this->Cliente_model->update((int) $id, $update_data);

        if (isset($data['referencias']) && is_array($data['referencias'])) {
            $this->db->where('cliente_id', (int) $id)->delete('cliente_referencias');
            $this->Cliente_referencia_model->insert_many((int) $id, $data['referencias']);
        }

        $this->db->trans_commit();

        $this->log_audit('clientes', (int) $id, 'UPDATE', (array) $cliente, $update_data, 'Cliente actualizado');

        $this->response_library->success(null, 'Cliente actualizado exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('clientes.eliminar');

        if ($this->input->method() !== 'post' && $this->input->method() !== 'delete') {
            $this->response_library->error('Método no permitido', 405);
        }

        $cliente = $this->Cliente_model->get_by_id((int) $id);

        if (!$cliente) {
            $this->response_library->not_found('Cliente no encontrado');
        }

        $this->Cliente_model->delete((int) $id);

        $this->log_audit('clientes', (int) $id, 'DELETE', (array) $cliente, null, 'Cliente eliminado');

        $this->response_library->success(null, 'Cliente eliminado exitosamente');
    }

    private function map_payload_to_cliente(array $data, $include_required = true) {
        $map = [];

        $fields = [
            'cliente_tipo',
            'cliente_calificacion_id',
            'tipo_documento',
            'numero_documento',
            'razon_social',
            'nombre_comercial',
            'profesion',
            'fecha_nacimiento',
            'direccion',
            'telefono',
            'email',
            'contacto_nombre',
            'contacto_telefono',
            'rep_ci_numero',
            'observaciones'
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                $map[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('activo', $data)) {
            $map['activo'] = (int) (bool) $data['activo'];
        }

        if (array_key_exists('lat', $data)) {
            $map['lat'] = is_numeric($data['lat']) ? (float) $data['lat'] : null;
        }

        if (array_key_exists('lng', $data)) {
            $map['lng'] = is_numeric($data['lng']) ? (float) $data['lng'] : null;
        }

        return $map;
    }
}

