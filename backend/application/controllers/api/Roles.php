<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roles extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $this->authenticate();
        $this->check_permission('usuarios.leer');
        
        $this->db->select('id, nombre, descripcion');
        $this->db->from('roles');
        $this->db->where('activo', 1);
        $this->db->order_by('nombre', 'ASC');
        
        $roles = $this->db->get()->result();
        
        $this->response_library->success($roles);
    }
}
