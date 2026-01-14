<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ClienteCalificaciones extends MY_Controller {

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('clientes.leer');

        $this->db->select('id, codigo, nombre, descripcion');
        $this->db->from('cliente_calificaciones');
        $this->db->where('activo', 1);
        $this->db->order_by('codigo', 'ASC');

        $rows = $this->db->get()->result();

        $this->response_library->success($rows);
    }
}
