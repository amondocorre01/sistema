<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Entrega_model extends CI_Model {
    
    private $table = 'entregas';
    
    public function get_all($filters = []) {
        $this->db->select('e.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as entregado_por_nombre,
                          u2.nombres as validado_por_nombre');
        $this->db->from($this->table . ' e');
        $this->db->join('contratos c', 'c.id = e.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = e.almacen_id');
        $this->db->join('usuarios u', 'u.id = e.entregado_por');
        $this->db->join('usuarios u2', 'u2.id = e.validado_por', 'left');
        
        if (isset($filters['estado'])) {
            $this->db->where('e.estado', $filters['estado']);
        }
        
        if (isset($filters['contrato_id'])) {
            $this->db->where('e.contrato_id', $filters['contrato_id']);
        }
        
        if (isset($filters['almacen_id'])) {
            $this->db->where('e.almacen_id', $filters['almacen_id']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('e.fecha_entrega >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('e.fecha_entrega <=', $filters['fecha_hasta']);
        }
        
        $this->db->order_by('e.fecha_entrega', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_id($id) {
        $this->db->select('e.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as entregado_por_nombre,
                          u2.nombres as validado_por_nombre');
        $this->db->from($this->table . ' e');
        $this->db->join('contratos c', 'c.id = e.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = e.almacen_id');
        $this->db->join('usuarios u', 'u.id = e.entregado_por');
        $this->db->join('usuarios u2', 'u2.id = e.validado_por', 'left');
        $this->db->where('e.id', $id);
        
        return $this->db->get()->row();
    }
    
    public function get_detalles($entrega_id) {
        $this->db->select('ed.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida');
        $this->db->from('entrega_detalles ed');
        $this->db->join('productos p', 'p.id = ed.producto_id');
        $this->db->where('ed.entrega_id', $entrega_id);
        
        return $this->db->get()->result();
    }
    
    public function get_pendientes() {
        $this->db->select('e.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as entregado_por_nombre');
        $this->db->from($this->table . ' e');
        $this->db->join('contratos c', 'c.id = e.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = e.almacen_id');
        $this->db->join('usuarios u', 'u.id = e.entregado_por');
        $this->db->where('e.estado', 'registrado');
        $this->db->order_by('e.fecha_entrega', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
    
    public function insert_detalles($entrega_id, $productos) {
        $this->db->trans_start();
        
        foreach ($productos as $producto) {
            $data = [
                'entrega_id' => $entrega_id,
                'producto_id' => $producto['producto_id'],
                'cantidad_programada' => $producto['cantidad_programada'],
                'cantidad_entregada' => $producto['cantidad_entregada'],
                'observaciones' => isset($producto['observaciones']) ? $producto['observaciones'] : null
            ];
            
            $this->db->insert('entrega_detalles', $data);
        }
        
        $this->db->trans_complete();
        
        return $this->db->trans_status() !== FALSE;
    }
    
    public function validar($entrega_id, $usuario_id, $observaciones) {
        $this->db->trans_start();
        
        $query = "CALL sp_validar_entrega(?, ?, ?, @resultado, @success)";
        $this->db->query($query, [$entrega_id, $usuario_id, $observaciones]);
        
        $result = $this->db->query("SELECT @resultado as resultado, @success as success")->row();
        
        $this->db->trans_complete();
        
        return [
            'success' => (bool)$result->success,
            'mensaje' => $result->resultado
        ];
    }
}
