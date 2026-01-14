<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sucursales extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $this->authenticate();
        $this->check_permission('sucursales.leer');
        
        $this->db->select('id, codigo, nombre, direccion, telefono, email, es_galpon_principal, activo');
        $this->db->from('sucursales');
        $this->db->order_by('nombre', 'ASC');
        
        $sucursales = $this->db->get()->result();
        
        $this->response_library->success($sucursales);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('sucursales.leer');

        $this->db->select('id, codigo, nombre, direccion, telefono, email, es_galpon_principal, activo');
        $this->db->from('sucursales');
        $this->db->where('id', (int) $id);
        $sucursal = $this->db->get()->row();

        if (!$sucursal) {
            $this->response_library->not_found('Sucursal no encontrada');
        }

        $this->response_library->success($sucursal);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('sucursales.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'codigo' => 'Código',
            'nombre' => 'Nombre'
        ], $data);

        $codigo = trim((string) $data['codigo']);
        $nombre = trim((string) $data['nombre']);

        if ($codigo === '' || strlen($codigo) > 20) {
            $this->response_library->error('Código inválido', 400);
        }

        if ($nombre === '' || strlen($nombre) > 100) {
            $this->response_library->error('Nombre inválido', 400);
        }

        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response_library->error('Email inválido', 400);
        }

        $this->db->where('codigo', $codigo);
        $existe = $this->db->get('sucursales')->row();
        if ($existe) {
            $this->response_library->error('El código de sucursal ya existe', 400);
        }

        $insert = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'direccion' => isset($data['direccion']) && trim((string) $data['direccion']) !== '' ? trim((string) $data['direccion']) : null,
            'telefono' => isset($data['telefono']) && trim((string) $data['telefono']) !== '' ? trim((string) $data['telefono']) : null,
            'email' => $email !== '' ? $email : null,
            'es_galpon_principal' => isset($data['es_galpon_principal']) ? (int) (bool) $data['es_galpon_principal'] : 0,
            'activo' => isset($data['activo']) ? (int) (bool) $data['activo'] : 1
        ];

        $this->db->insert('sucursales', $insert);
        $id = $this->db->insert_id();

        if (!$id) {
            $this->response_library->error('Error al crear sucursal');
        }

        $this->log_audit('sucursales', $id, 'INSERT', null, $insert, 'Sucursal creada');

        $this->response_library->success(['id' => $id], 'Sucursal creada exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('sucursales.actualizar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $this->db->where('id', (int) $id);
        $actual = $this->db->get('sucursales')->row();

        if (!$actual) {
            $this->response_library->not_found('Sucursal no encontrada');
        }

        $data = $this->get_json_input();
        $update = [];

        if (isset($data['codigo'])) {
            $codigo = trim((string) $data['codigo']);
            if ($codigo === '' || strlen($codigo) > 20) {
                $this->response_library->error('Código inválido', 400);
            }
            $this->db->where('codigo', $codigo);
            $this->db->where('id !=', (int) $id);
            $existe = $this->db->get('sucursales')->row();
            if ($existe) {
                $this->response_library->error('El código de sucursal ya existe', 400);
            }
            $update['codigo'] = $codigo;
        }

        if (isset($data['nombre'])) {
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 100) {
                $this->response_library->error('Nombre inválido', 400);
            }
            $update['nombre'] = $nombre;
        }

        if (array_key_exists('direccion', $data)) {
            $update['direccion'] = trim((string) $data['direccion']) !== '' ? trim((string) $data['direccion']) : null;
        }

        if (array_key_exists('telefono', $data)) {
            $update['telefono'] = trim((string) $data['telefono']) !== '' ? trim((string) $data['telefono']) : null;
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->response_library->error('Email inválido', 400);
            }
            $update['email'] = $email !== '' ? $email : null;
        }

        if (isset($data['es_galpon_principal'])) {
            $update['es_galpon_principal'] = (int) (bool) $data['es_galpon_principal'];
        }

        if (isset($data['activo'])) {
            $update['activo'] = (int) (bool) $data['activo'];
        }

        if (empty($update)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $this->db->where('id', (int) $id);
        $ok = $this->db->update('sucursales', $update);

        if (!$ok) {
            $this->response_library->error('Error al actualizar sucursal');
        }

        $this->log_audit('sucursales', (int) $id, 'UPDATE', (array) $actual, $update, 'Sucursal actualizada');

        $this->response_library->success(null, 'Sucursal actualizada exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('sucursales.eliminar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $this->db->where('id', (int) $id);
        $actual = $this->db->get('sucursales')->row();

        if (!$actual) {
            $this->response_library->not_found('Sucursal no encontrada');
        }

        $this->db->where('id', (int) $id);
        $ok = $this->db->update('sucursales', ['activo' => 0]);

        if (!$ok) {
            $this->response_library->error('Error al desactivar sucursal');
        }

        $this->log_audit('sucursales', (int) $id, 'DELETE', (array) $actual, ['activo' => 0], 'Sucursal desactivada');

        $this->response_library->success(null, 'Sucursal desactivada exitosamente');
    }
}
