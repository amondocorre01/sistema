<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Almacenes extends MY_Controller {

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('inventario.ver');

        $this->db->select('a.id, a.sucursal_id, a.codigo, a.nombre, a.descripcion, a.activo, s.nombre as sucursal_nombre, s.direccion as sucursal_direccion, s.telefono as sucursal_telefono');
        $this->db->from('almacenes a');
        $this->db->join('sucursales s', 's.id = a.sucursal_id');
        $this->db->where('a.activo', 1);

        $sucursal_id = $this->input->get('sucursal_id');
        if ($sucursal_id !== null && $sucursal_id !== '') {
            $this->db->where('a.sucursal_id', (int) $sucursal_id);
        }

        $this->db->order_by('a.nombre', 'ASC');
        $rows = $this->db->get()->result();

        $this->response_library->success($rows);
    }
}
