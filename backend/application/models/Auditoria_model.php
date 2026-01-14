<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auditoria_model extends CI_Model {
    
    private $table = 'auditoria';
    
    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
    
    public function get_all($filters = []) {
        $this->db->select('a.*, u.nombres as usuario_nombre, u.username');
        $this->db->from($this->table . ' a');
        $this->db->join('usuarios u', 'u.id = a.usuario_id', 'left');
        
        if (isset($filters['usuario_id'])) {
            $this->db->where('a.usuario_id', $filters['usuario_id']);
        }
        
        if (isset($filters['tabla'])) {
            $this->db->where('a.tabla_afectada', $filters['tabla']);
        }
        
        if (isset($filters['accion'])) {
            $this->db->where('a.accion', $filters['accion']);
        }
        
        if (isset($filters['modulo'])) {
            $this->db->where('a.modulo', $filters['modulo']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('a.fecha_accion >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('a.fecha_accion <=', $filters['fecha_hasta']);
        }
        
        $this->db->order_by('a.fecha_accion', 'DESC');
        $this->db->limit(isset($filters['limit']) ? $filters['limit'] : 100);
        
        return $this->db->get()->result();
    }
    
    public function get_by_registro($tabla, $registro_id) {
        $this->db->select('a.*, u.nombres as usuario_nombre, u.username');
        $this->db->from($this->table . ' a');
        $this->db->join('usuarios u', 'u.id = a.usuario_id', 'left');
        $this->db->where('a.tabla_afectada', $tabla);
        $this->db->where('a.registro_id', $registro_id);
        $this->db->order_by('a.fecha_accion', 'DESC');
        
        return $this->db->get()->result();
    }
}
