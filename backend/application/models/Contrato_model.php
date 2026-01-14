<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Contrato_model extends CI_Model {
    
    private $table = 'contratos';
    
    public function get_all($filters = []) {
        $this->db->select('c.*, cl.razon_social as cliente_nombre, 
                          s.nombre as sucursal_nombre, a.nombre as almacen_nombre,
                          u.nombres as creado_por_nombre,
                          IFNULL(pp.pagado, 0) AS total_pagado,
                          (c.total - IFNULL(pp.pagado, 0)) AS saldo_pendiente', false);
        $this->db->from($this->table . ' c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('sucursales s', 's.id = c.sucursal_venta_id');
        $this->db->join('almacenes a', 'a.id = c.almacen_despacho_id');
        $this->db->join('usuarios u', 'u.id = c.created_by');
        $this->db->join('(SELECT contrato_id, SUM(monto - IFNULL(descuento_aplicado, 0)) AS pagado FROM pagos GROUP BY contrato_id) pp', 'pp.contrato_id = c.id', 'left', false);
        $this->db->where('c.deleted_at IS NULL');
        
        if (isset($filters['estado'])) {
            if (is_array($filters['estado'])) {
                $this->db->where_in('c.estado', $filters['estado']);
            } else {
                $this->db->where('c.estado', $filters['estado']);
            }
        }
        
        if (isset($filters['cliente_id'])) {
            $this->db->where('c.cliente_id', $filters['cliente_id']);
        }
        
        if (isset($filters['sucursal_id'])) {
            $this->db->where('c.sucursal_venta_id', $filters['sucursal_id']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('c.fecha_contrato >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('c.fecha_contrato <=', $filters['fecha_hasta']);
        }
        
        $this->db->order_by('c.created_at', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_id($id) {
        $this->db->select('c.*, cl.razon_social as cliente_nombre, cl.numero_documento,
                          s.nombre as sucursal_nombre, a.nombre as almacen_nombre,
                          u.nombres as creado_por_nombre');
        $this->db->from($this->table . ' c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('sucursales s', 's.id = c.sucursal_venta_id');
        $this->db->join('almacenes a', 'a.id = c.almacen_despacho_id');
        $this->db->join('usuarios u', 'u.id = c.created_by');
        $this->db->where('c.id', $id);
        $this->db->where('c.deleted_at IS NULL');
        
        return $this->db->get()->row();
    }
    
    public function get_productos($contrato_id) {
        $this->db->select('cp.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida, p.imagen_url');
        $this->db->from('contrato_productos cp');
        $this->db->join('productos p', 'p.id = cp.producto_id');
        $this->db->where('cp.contrato_id', $contrato_id);
        
        return $this->db->get()->result();
    }
    
    public function insert($data) {
        $es_proforma = isset($data['tipo_documento']) && $data['tipo_documento'] === 'proforma';
        
        if ($es_proforma) {
            $data['proforma_numero'] = $this->generate_proforma_numero();
            // Para mantener compatibilidad visual (columna "Número"),
            // en proformas también guardamos un código en numero_contrato.
            $data['numero_contrato'] = $data['proforma_numero'];
        } else {
            $data['numero_contrato'] = $this->generate_numero_contrato();
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->trans_start();
        
        $this->db->insert($this->table, $data);
        $contrato_id = $this->db->insert_id();
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return false;
        }
        
        return $contrato_id;
    }
    
    public function insert_productos($contrato_id, $productos) {
        $this->db->trans_start();
        
        foreach ($productos as $producto) {
            $data = [
                'contrato_id' => $contrato_id,
                'producto_id' => $producto['producto_id'],
                'cantidad_contratada' => $producto['cantidad'],
                'precio_unitario' => $producto['precio_unitario'],
                'subtotal' => $producto['subtotal']
            ];

            if (array_key_exists('fecha_salida', $producto)) {
                $data['fecha_salida'] = $producto['fecha_salida'] !== null && $producto['fecha_salida'] !== '' ? $producto['fecha_salida'] : null;
            }
            if (array_key_exists('fecha_devolucion', $producto)) {
                $data['fecha_devolucion'] = $producto['fecha_devolucion'] !== null && $producto['fecha_devolucion'] !== '' ? $producto['fecha_devolucion'] : null;
            }
            if (array_key_exists('dias_cobrados', $producto)) {
                $data['dias_cobrados'] = $producto['dias_cobrados'] !== null && $producto['dias_cobrados'] !== '' ? (int) $producto['dias_cobrados'] : null;
            }
            if (array_key_exists('aplicar_descuento_domingos_feriados', $producto)) {
                $data['aplicar_descuento_domingos_feriados'] = (int) (bool) $producto['aplicar_descuento_domingos_feriados'];
            }
            
            $this->db->insert('contrato_productos', $data);
        }

        $this->db->trans_complete();
        return $this->db->trans_status() !== FALSE;
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
    
    public function cambiar_estado($id, $nuevo_estado) {
        $data = [
            'estado' => $nuevo_estado,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    public function aprobar($id, $usuario_id) {
        $data = [
            'estado' => 'aprobado',
            'approved_by' => $usuario_id,
            'fecha_aprobacion' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    private function generate_numero_contrato() {
        $year = date('Y');
        $month = date('m');
        
        $this->db->select('COUNT(*) as total');
        $this->db->from($this->table);
        $this->db->where('YEAR(created_at)', $year);
        $this->db->where('MONTH(created_at)', $month);
        $this->db->where('tipo_documento', 'contrato');
        $this->db->where('numero_contrato IS NOT NULL');
        $result = $this->db->get()->row();
        
        $count = $result ? $result->total + 1 : 1;
        
        return sprintf('CTR-%s%s-%04d', $year, $month, $count);
    }
    
    private function generate_proforma_numero() {
        $year = date('Y');
        
        $this->db->select('COUNT(*) as total');
        $this->db->from($this->table);
        $this->db->where('YEAR(created_at)', $year);
        $this->db->where('tipo_documento', 'proforma');
        $result = $this->db->get()->row();
        
        $count = $result ? $result->total + 1 : 1;
        
        return sprintf('PRO-%s-%05d', $year, $count);
    }
    
    public function convertir_proforma_a_contrato($id) {
        $this->db->trans_start();
        
        $numero_contrato = $this->generate_numero_contrato();
        
        $update_data = [
            'tipo_documento' => 'contrato',
            'numero_contrato' => $numero_contrato,
            'estado' => 'borrador',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', (int) $id);
        $this->db->update($this->table, $update_data);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return ['success' => false, 'mensaje' => 'Error al convertir proforma'];
        }
        
        return ['success' => true, 'numero_contrato' => $numero_contrato];
    }
    
    public function reservar_stock($contrato_id, $almacen_id) {
        $this->db->trans_start();
        
        $query = "CALL sp_reservar_stock(?, ?, @resultado, @success)";
        $this->db->query($query, [$contrato_id, $almacen_id]);
        
        $result = $this->db->query("SELECT @resultado as resultado, @success as success")->row();
        
        $this->db->trans_complete();
        
        return [
            'success' => (bool)$result->success,
            'mensaje' => $result->resultado
        ];
    }
    
    public function liberar_stock($contrato_id, $almacen_id) {
        $this->db->trans_start();
        
        $query = "CALL sp_liberar_stock(?, ?, @resultado, @success)";
        $this->db->query($query, [$contrato_id, $almacen_id]);
        
        $result = $this->db->query("SELECT @resultado as resultado, @success as success")->row();
        
        $this->db->trans_complete();
        
        return [
            'success' => (bool)$result->success,
            'mensaje' => $result->resultado
        ];
    }
}
