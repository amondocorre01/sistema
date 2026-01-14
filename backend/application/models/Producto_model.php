<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Producto_model extends CI_Model {
    
    private $table = 'productos';
    
    public function get_all($filters = []) {
        $this->db->select('p.*, c.nombre as categoria_nombre');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias_producto c', 'c.id = p.categoria_id', 'left');
        
        if (isset($filters['activo'])) {
            $this->db->where('p.activo', $filters['activo']);
        }
        
        if (isset($filters['categoria_id'])) {
            $this->db->where('p.categoria_id', $filters['categoria_id']);
        }
        
        if (isset($filters['tipo'])) {
            $this->db->where('p.tipo', $filters['tipo']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $this->db->group_start();
            $this->db->like('p.codigo', $search);
            $this->db->or_like('p.nombre', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('p.nombre', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_id($id) {
        $this->db->select('p.*, c.nombre as categoria_nombre');
        $this->db->from($this->table . ' p');
        $this->db->join('categorias_producto c', 'c.id = p.categoria_id', 'left');
        $this->db->where('p.id', $id);
        
        return $this->db->get()->row();
    }
    
    public function get_componentes($producto_id) {
        $this->db->select('pc.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida');
        $this->db->from('producto_componentes pc');
        $this->db->join('productos p', 'p.id = pc.producto_hijo_id');
        $this->db->where('pc.producto_padre_id', $producto_id);
        
        return $this->db->get()->result();
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
        $this->db->where('id', $id);
        return $this->db->delete($this->table);
    }
}
