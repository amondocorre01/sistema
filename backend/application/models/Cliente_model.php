<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cliente_model extends CI_Model {
    
    private $table = 'clientes';
    
    public function get_all($filters = []) {
        $this->db->select('c.*, u.nombres as creado_por_nombre, cc.codigo as calificacion_codigo, cc.nombre as calificacion_nombre, uu.nombres as actualizado_por_nombre');
        $this->db->from($this->table . ' c');
        $this->db->join('usuarios u', 'u.id = c.created_by', 'left');
        $this->db->join('usuarios uu', 'uu.id = c.updated_by', 'left');
        $this->db->join('cliente_calificaciones cc', 'cc.id = c.cliente_calificacion_id', 'left');
        $this->db->where('c.deleted_at IS NULL');
        
        if (isset($filters['activo'])) {
            $this->db->where('c.activo', $filters['activo']);
        }
        
        if (isset($filters['tipo_documento'])) {
            $this->db->where('c.tipo_documento', $filters['tipo_documento']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $this->db->group_start();
            $this->db->like('c.numero_documento', $search);
            $this->db->or_like('c.razon_social', $search);
            $this->db->or_like('c.nombre_comercial', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('c.razon_social', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_id($id) {
        $this->db->select('c.*, u.nombres as creado_por_nombre, cc.codigo as calificacion_codigo, cc.nombre as calificacion_nombre, uu.nombres as actualizado_por_nombre');
        $this->db->from($this->table . ' c');
        $this->db->join('usuarios u', 'u.id = c.created_by');
        $this->db->join('usuarios uu', 'uu.id = c.updated_by', 'left');
        $this->db->join('cliente_calificaciones cc', 'cc.id = c.cliente_calificacion_id', 'left');
        $this->db->where('c.id', $id);
        $this->db->where('c.deleted_at IS NULL');
        
        return $this->db->get()->row();
    }
    
    public function get_by_documento($tipo, $numero) {
        $this->db->where('tipo_documento', $tipo);
        $this->db->where('numero_documento', $numero);
        $this->db->where('deleted_at IS NULL');
        
        return $this->db->get($this->table)->row();
    }
    
    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    public function delete($id) {
        $data = ['deleted_at' => date('Y-m-d H:i:s')];
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
}
