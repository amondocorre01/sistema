<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caja extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Caja_model');
    }

    private function require_any_permission($permissions = []) {
        if (!$this->user_data) {
            $this->authenticate();
        }

        if (($this->user_data['rol'] ?? '') === 'administrador') {
            return true;
        }

        $perms = $this->user_data['permisos'] ?? [];
        foreach ($permissions as $p) {
            if (in_array($p, $perms)) {
                return true;
            }
        }

        $this->response_library->forbidden('No tiene permisos para esta acción');
    }

    public function sesion_abierta() {
        $this->authenticate();
        $this->check_permission('caja.ver');

        $sucursal_id = (int) $this->get_sucursal_activa(true);
        $sesion = $this->Caja_model->get_sesion_abierta($sucursal_id);
        
        $this->response_library->success($sesion);
    }

    public function sesiones() {
        $this->authenticate();
        $this->require_any_permission(['caja.ver', 'reportes.ver']);

        $sucursal_id = (int) $this->get_sucursal_activa(true);

        $desde = $this->input->get('desde');
        $hasta = $this->input->get('hasta');
        $desde_cierre = $this->input->get('desde_cierre');
        $hasta_cierre = $this->input->get('hasta_cierre');
        $limit = $this->input->get('limit');

        $filters = [
            'desde' => $desde ? (string) $desde : null,
            'hasta' => $hasta ? (string) $hasta : null,
            'cierre_desde' => $desde_cierre ? (string) $desde_cierre : null,
            'cierre_hasta' => $hasta_cierre ? (string) $hasta_cierre : null,
            'limit' => $limit !== null ? (int) $limit : 100
        ];

        $rows = $this->Caja_model->get_sesiones($sucursal_id, $filters);
        $this->response_library->success($rows);
    }

    public function abrir() {
        $this->authenticate();
        $this->check_permission('caja.abrir');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();
        $this->validate_required([
            'monto_apertura' => 'Monto de apertura'
        ], $data);

        $sucursal_id = (int) $this->get_sucursal_activa(true);

        $actual = $this->Caja_model->get_sesion_abierta($sucursal_id);
        if ($actual) {
            $this->response_library->error('Ya existe una caja abierta para esta sucursal', 409);
        }

        $monto_apertura = (float) $data['monto_apertura'];
        if ($monto_apertura < 0) {
            $this->response_library->error('El monto de apertura no puede ser negativo', 400);
        }

        $insert = [
            'sucursal_id' => $sucursal_id,
            'usuario_apertura_id' => (int) ($this->user_data['user_id'] ?? 0),
            'fecha_apertura' => date('Y-m-d H:i:s'),
            'monto_apertura' => $monto_apertura,
            'observaciones' => isset($data['observaciones']) ? trim((string) $data['observaciones']) : null,
            'estado' => 'abierta'
        ];

        $id = $this->Caja_model->abrir($insert);
        if (!$id) {
            $this->response_library->error('Error al abrir caja', 500);
        }

        $this->log_audit('caja_sesiones', (int) $id, 'INSERT', null, $insert, 'Apertura de caja');

        $sesion = $this->Caja_model->get_by_id((int) $id);
        $this->response_library->success($sesion, 'Caja abierta correctamente', 201);
    }

    public function cerrar($sesion_id) {
        $this->authenticate();
        $this->check_permission('caja.cerrar');

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $sesion = $this->Caja_model->get_by_id((int) $sesion_id);
        if (!$sesion) {
            $this->response_library->not_found('Sesión de caja no encontrada');
        }

        $this->validate_sucursal_match((int) $sesion->sucursal_id, 'No puede cerrar una caja de otra sucursal');

        if ($sesion->estado !== 'abierta') {
            $this->response_library->error('La caja ya está cerrada', 409);
        }

        $data = $this->get_json_input();
        $this->validate_required([
            'monto_cierre_efectivo' => 'Monto efectivo de cierre'
        ], $data);

        $monto_cierre_efectivo = (float) $data['monto_cierre_efectivo'];
        if ($monto_cierre_efectivo < 0) {
            $this->response_library->error('El monto de cierre no puede ser negativo', 400);
        }

        $update = [
            'usuario_cierre_id' => (int) ($this->user_data['user_id'] ?? 0),
            'fecha_cierre' => date('Y-m-d H:i:s'),
            'monto_cierre_efectivo' => $monto_cierre_efectivo,
            'observaciones' => isset($data['observaciones']) ? trim((string) $data['observaciones']) : ($sesion->observaciones ?? null),
            'estado' => 'cerrada'
        ];

        $ok = $this->Caja_model->cerrar((int) $sesion_id, $update);
        if (!$ok) {
            $this->response_library->error('Error al cerrar caja', 500);
        }

        $this->log_audit('caja_sesiones', (int) $sesion_id, 'UPDATE', $sesion, $update, 'Cierre de caja');

        $resumen = $this->Caja_model->get_resumen((int) $sesion_id);
        $this->response_library->success($resumen, 'Caja cerrada correctamente');
    }

    public function resumen($sesion_id) {
        $this->authenticate();
        $this->require_any_permission(['caja.ver', 'reportes.ver']);

        $sesion = $this->Caja_model->get_by_id((int) $sesion_id);
        if (!$sesion) {
            $this->response_library->not_found('Sesión de caja no encontrada');
        }

        $this->validate_sucursal_match((int) $sesion->sucursal_id, 'No puede ver una caja de otra sucursal');

        $resumen = $this->Caja_model->get_resumen((int) $sesion_id);
        if (!$resumen) {
            $this->response_library->error('No se pudo generar el resumen', 500);
        }

        $this->response_library->success($resumen);
    }
}
