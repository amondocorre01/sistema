<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Personal extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Personal_model');
        $this->load->model('Personal_referencia_model');
        $this->load->model('Auditoria_model');
    }

    private function parse_date($value) {
        $value = trim((string) $value);
        if ($value === '') return null;
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            $this->response_library->error('Fecha inválida. Formato requerido: YYYY-MM-DD', 400);
        }
        return $value;
    }

    private function parse_lat_lng($data) {
        $lat = isset($data['domicilio_lat']) ? $data['domicilio_lat'] : null;
        $lng = isset($data['domicilio_lng']) ? $data['domicilio_lng'] : null;

        if ($lat === null || $lat === '') $lat = null;
        if ($lng === null || $lng === '') $lng = null;

        if ($lat !== null && $lng !== null) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $this->response_library->error('Coordenadas inválidas', 400);
            }
            return [$lat, $lng];
        }

        return [null, null];
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('usuarios.leer');

        $filters = [
            'activo' => $this->input->get('activo'),
            'search' => $this->input->get('search')
        ];

        $personal = $this->Personal_model->get_all($filters);
        $this->response_library->success($personal);
    }

    public function show($id) {
        $this->authenticate();
        $this->check_permission('usuarios.leer');

        $row = $this->Personal_model->get_by_id($id);
        if (!$row) {
            $this->response_library->not_found('Registro de personal no encontrado');
        }

        $row->referencias = $this->Personal_referencia_model->get_by_personal($id);
        $this->response_library->success($row);
    }

    public function create() {
        $this->authenticate();
        $this->check_permission('usuarios.crear');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'nombre_completo' => 'Nombre',
            'ci_numero' => 'N° de Carnet de Identidad'
        ], $data);

        $nombre_completo = trim((string) $data['nombre_completo']);
        $ci_numero = trim((string) $data['ci_numero']);

        $exist = $this->Personal_model->get_by_ci($ci_numero);
        if ($exist) {
            $this->response_library->error('Ya existe un empleado con ese N° de CI', 409);
        }

        $usuario_id = isset($data['usuario_id']) && $data['usuario_id'] !== '' ? (int) $data['usuario_id'] : null;
        if ($usuario_id !== null) {
            $u = $this->db->get_where('usuarios', ['id' => $usuario_id, 'deleted_at' => null])->row();
            if (!$u) {
                $this->response_library->error('Usuario asociado no encontrado', 404);
            }
        }

        $celular = isset($data['celular']) ? trim((string) $data['celular']) : null;
        $fecha_nacimiento = isset($data['fecha_nacimiento']) ? $this->parse_date($data['fecha_nacimiento']) : null;
        $fecha_ingreso = isset($data['fecha_ingreso']) ? $this->parse_date($data['fecha_ingreso']) : null;
        $fecha_retiro = isset($data['fecha_retiro']) ? $this->parse_date($data['fecha_retiro']) : null;
        $domicilio_direccion = isset($data['domicilio_direccion']) ? trim((string) $data['domicilio_direccion']) : null;

        list($dom_lat, $dom_lng) = $this->parse_lat_lng($data);

        $activo = isset($data['activo']) ? (int) (bool) $data['activo'] : 1;
        $referencias = isset($data['referencias']) && is_array($data['referencias']) ? $data['referencias'] : [];

        $insert = [
            'usuario_id' => $usuario_id,
            'nombre_completo' => $nombre_completo,
            'ci_numero' => $ci_numero,
            'celular' => $celular !== '' ? $celular : null,
            'fecha_nacimiento' => $fecha_nacimiento,
            'fecha_ingreso' => $fecha_ingreso,
            'fecha_retiro' => $fecha_retiro,
            'domicilio_direccion' => $domicilio_direccion !== '' ? $domicilio_direccion : null,
            'domicilio_lat' => $dom_lat,
            'domicilio_lng' => $dom_lng,
            'activo' => $activo,
            'created_by' => $this->user_data['user_id'] ?? null,
            'updated_by' => $this->user_data['user_id'] ?? null
        ];

        $this->db->trans_begin();

        $id = $this->Personal_model->insert($insert);
        if (!$id) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al crear empleado');
        }

        $ok_refs = $this->Personal_referencia_model->replace_many($id, $referencias);
        if ($ok_refs === false) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al guardar referencias');
        }

        $this->db->trans_commit();

        $this->log_audit('personal', $id, 'INSERT', null, $insert, 'Empleado creado');

        $row = $this->Personal_model->get_by_id($id);
        $row->referencias = $this->Personal_referencia_model->get_by_personal($id);
        $this->response_library->success($row, 'Empleado creado exitosamente', 201);
    }

    public function update($id) {
        $this->authenticate();
        $this->check_permission('usuarios.actualizar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->Personal_model->get_by_id($id);
        if (!$actual) {
            $this->response_library->not_found('Registro de personal no encontrado');
        }

        $data = $this->get_json_input();
        $update = [];

        if (isset($data['nombre_completo'])) {
            $update['nombre_completo'] = trim((string) $data['nombre_completo']);
        }
        if (isset($data['ci_numero'])) {
            $ci = trim((string) $data['ci_numero']);
            $exist = $this->Personal_model->get_by_ci($ci, $id);
            if ($exist) {
                $this->response_library->error('Ya existe un empleado con ese N° de CI', 409);
            }
            $update['ci_numero'] = $ci;
        }
        if (array_key_exists('usuario_id', $data)) {
            $usuario_id = $data['usuario_id'] !== null && $data['usuario_id'] !== '' ? (int) $data['usuario_id'] : null;
            if ($usuario_id !== null) {
                $u = $this->db->get_where('usuarios', ['id' => $usuario_id, 'deleted_at' => null])->row();
                if (!$u) {
                    $this->response_library->error('Usuario asociado no encontrado', 404);
                }
            }
            $update['usuario_id'] = $usuario_id;
        }
        if (array_key_exists('celular', $data)) {
            $cel = $data['celular'] !== null ? trim((string) $data['celular']) : null;
            $update['celular'] = ($cel !== null && $cel !== '') ? $cel : null;
        }
        if (array_key_exists('fecha_nacimiento', $data)) {
            $update['fecha_nacimiento'] = $data['fecha_nacimiento'] !== null ? $this->parse_date($data['fecha_nacimiento']) : null;
        }
        if (array_key_exists('fecha_ingreso', $data)) {
            $update['fecha_ingreso'] = $data['fecha_ingreso'] !== null ? $this->parse_date($data['fecha_ingreso']) : null;
        }
        if (array_key_exists('fecha_retiro', $data)) {
            $update['fecha_retiro'] = $data['fecha_retiro'] !== null ? $this->parse_date($data['fecha_retiro']) : null;
        }
        if (array_key_exists('domicilio_direccion', $data)) {
            $dir = $data['domicilio_direccion'] !== null ? trim((string) $data['domicilio_direccion']) : null;
            $update['domicilio_direccion'] = ($dir !== null && $dir !== '') ? $dir : null;
        }
        if (array_key_exists('domicilio_lat', $data) || array_key_exists('domicilio_lng', $data)) {
            list($dom_lat, $dom_lng) = $this->parse_lat_lng($data);
            $update['domicilio_lat'] = $dom_lat;
            $update['domicilio_lng'] = $dom_lng;
        }
        if (isset($data['activo'])) {
            $update['activo'] = (int) (bool) $data['activo'];
        }

        $update['updated_by'] = $this->user_data['user_id'] ?? null;

        $referencias = null;
        if (isset($data['referencias']) && is_array($data['referencias'])) {
            $referencias = $data['referencias'];
        }

        if (empty($update) && $referencias === null) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }

        $this->db->trans_begin();

        if (!empty($update)) {
            $ok = $this->Personal_model->update($id, $update);
            if (!$ok) {
                $this->db->trans_rollback();
                $this->response_library->error('Error al actualizar empleado');
            }
        }

        if ($referencias !== null) {
            $ok_refs = $this->Personal_referencia_model->replace_many($id, $referencias);
            if ($ok_refs === false) {
                $this->db->trans_rollback();
                $this->response_library->error('Error al guardar referencias');
            }
        }

        $this->db->trans_commit();

        $this->log_audit('personal', $id, 'UPDATE', (array) $actual, $update, 'Empleado actualizado');

        $row = $this->Personal_model->get_by_id($id);
        $row->referencias = $this->Personal_referencia_model->get_by_personal($id);
        $this->response_library->success($row, 'Empleado actualizado exitosamente');
    }

    public function delete($id) {
        $this->authenticate();
        $this->check_permission('usuarios.eliminar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $actual = $this->Personal_model->get_by_id($id);
        if (!$actual) {
            $this->response_library->not_found('Registro de personal no encontrado');
        }

        $ok = $this->Personal_model->delete($id);
        if (!$ok) {
            $this->response_library->error('Error al eliminar empleado');
        }

        $this->log_audit('personal', $id, 'DELETE', (array) $actual, null, 'Empleado eliminado');
        $this->response_library->success(null, 'Empleado eliminado exitosamente');
    }
}
