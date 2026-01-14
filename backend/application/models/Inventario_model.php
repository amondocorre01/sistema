<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario_model extends CI_Model {
    
    private $table = 'inventario';
    
    public function get_all($filters = []) {
        $this->db->select('i.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida,
                          a.nombre as almacen_nombre, s.nombre as sucursal_nombre,
                          c.nombre as categoria_nombre');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.producto_id');
        $this->db->join('almacenes a', 'a.id = i.almacen_id');
        $this->db->join('sucursales s', 's.id = a.sucursal_id', 'left');
        $this->db->join('categorias_producto c', 'c.id = p.categoria_id', 'left');
        
        if (isset($filters['almacen_id'])) {
            $this->db->where('i.almacen_id', $filters['almacen_id']);
        }
        
        if (isset($filters['sucursal_id'])) {
            $this->db->where('s.id', $filters['sucursal_id']);
        }
        
        if (isset($filters['producto_id'])) {
            $this->db->where('i.producto_id', $filters['producto_id']);
        }
        
        if (isset($filters['categoria_id'])) {
            $this->db->where('p.categoria_id', $filters['categoria_id']);
        }
        
        if (isset($filters['bajo_stock']) && $filters['bajo_stock']) {
            $this->db->where('i.cantidad_disponible < i.stock_minimo');
        }
        
        $this->db->order_by('p.nombre', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_producto_almacen($producto_id, $almacen_id) {
        $this->db->select('i.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida,
                          a.nombre as almacen_nombre');
        $this->db->from($this->table . ' i');
        $this->db->join('productos p', 'p.id = i.producto_id');
        $this->db->join('almacenes a', 'a.id = i.almacen_id');
        $this->db->where('i.producto_id', $producto_id);
        $this->db->where('i.almacen_id', $almacen_id);
        
        return $this->db->get()->row();
    }
    
    public function get_movimientos($filters = []) {
        $this->db->select('im.*, p.codigo, p.nombre as producto_nombre,
                          a.nombre as almacen_nombre, u.nombres as usuario_nombre,
                          c.numero_contrato');
        $this->db->from('inventario_movimientos im');
        $this->db->join('productos p', 'p.id = im.producto_id');
        $this->db->join('almacenes a', 'a.id = im.almacen_id');
        $this->db->join('usuarios u', 'u.id = im.usuario_id');
        $this->db->join('contratos c', 'c.id = im.contrato_id', 'left');
        
        if (isset($filters['producto_id'])) {
            $this->db->where('im.producto_id', $filters['producto_id']);
        }
        
        if (isset($filters['almacen_id'])) {
            $this->db->where('im.almacen_id', $filters['almacen_id']);
        }
        
        if (isset($filters['tipo_movimiento'])) {
            $this->db->where('im.tipo_movimiento', $filters['tipo_movimiento']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('im.fecha_movimiento >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('im.fecha_movimiento <=', $filters['fecha_hasta']);
        }
        
        $this->db->order_by('im.fecha_movimiento', 'DESC');
        $this->db->limit(isset($filters['limit']) ? $filters['limit'] : 100);
        
        return $this->db->get()->result();
    }
    
    public function get_kardex($producto_id, $almacen_id, $fecha_desde = null, $fecha_hasta = null) {
        $this->db->select('im.*, u.nombres as usuario_nombre, c.numero_contrato');
        $this->db->from('inventario_movimientos im');
        $this->db->join('usuarios u', 'u.id = im.usuario_id');
        $this->db->join('contratos c', 'c.id = im.contrato_id', 'left');
        $this->db->where('im.producto_id', $producto_id);
        $this->db->where('im.almacen_id', $almacen_id);
        
        if ($fecha_desde) {
            $this->db->where('im.fecha_movimiento >=', $fecha_desde);
        }
        
        if ($fecha_hasta) {
            $this->db->where('im.fecha_movimiento <=', $fecha_hasta);
        }
        
        $this->db->order_by('im.fecha_movimiento', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function insert_movimiento($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert('inventario_movimientos', $data);
        return $this->db->insert_id();
    }

    public function registrar_entrada($producto_id, $almacen_id, $cantidad, $motivo, $usuario_id) {
        $producto_id = (int) $producto_id;
        $almacen_id = (int) $almacen_id;
        $cantidad = (float) $cantidad;
        $usuario_id = (int) $usuario_id;

        if ($producto_id <= 0 || $almacen_id <= 0 || $cantidad <= 0 || $usuario_id <= 0) {
            return false;
        }

        $inventario = $this->get_by_producto_almacen($producto_id, $almacen_id);
        if (!$inventario) {
            $this->db->insert('inventario', [
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'cantidad_total' => 0,
                'cantidad_disponible' => 0,
                'cantidad_reservada' => 0,
                'cantidad_alquilada' => 0,
                'cantidad_en_reparacion' => 0,
                'cantidad_perdida' => 0,
                'stock_minimo' => 0,
                'stock_maximo' => 0
            ]);

            $inventario = $this->get_by_producto_almacen($producto_id, $almacen_id);
            if (!$inventario) {
                return false;
            }
        }

        $cantidad_anterior = (float) $inventario->cantidad_disponible;
        $cantidad_nueva = $cantidad_anterior + $cantidad;

        $this->db->set('cantidad_disponible', 'cantidad_disponible + ' . $cantidad, FALSE);
        $this->db->set('cantidad_total', 'cantidad_total + ' . $cantidad, FALSE);
        $this->db->where('producto_id', $producto_id);
        $this->db->where('almacen_id', $almacen_id);
        $ok = $this->db->update($this->table);

        if (!$ok) {
            return false;
        }

        $movimiento_data = [
            'producto_id' => $producto_id,
            'almacen_id' => $almacen_id,
            'tipo_movimiento' => 'entrada',
            'cantidad' => $cantidad,
            'cantidad_anterior' => $cantidad_anterior,
            'cantidad_nueva' => $cantidad_nueva,
            'motivo' => $motivo,
            'usuario_id' => $usuario_id,
            'fecha_movimiento' => date('Y-m-d H:i:s')
        ];

        $mov_id = $this->insert_movimiento($movimiento_data);

        return (bool) $mov_id;
    }
    
    public function ajustar_inventario($producto_id, $almacen_id, $tipo_ajuste, $cantidad, $motivo, $usuario_id) {
        $this->db->trans_start();
        
        $inventario = $this->get_by_producto_almacen($producto_id, $almacen_id);
        
        if (!$inventario) {
            $this->db->trans_rollback();
            return ['success' => false, 'mensaje' => 'Inventario no encontrado'];
        }
        
        $cantidad_anterior = $inventario->cantidad_disponible;
        
        if ($tipo_ajuste === 'positivo') {
            $nueva_cantidad = $cantidad_anterior + $cantidad;
            $this->db->set('cantidad_disponible', 'cantidad_disponible + ' . $cantidad, FALSE);
            $this->db->set('cantidad_total', 'cantidad_total + ' . $cantidad, FALSE);
        } else {
            if ($cantidad_anterior < $cantidad) {
                $this->db->trans_rollback();
                return ['success' => false, 'mensaje' => 'Cantidad insuficiente en inventario'];
            }
            $nueva_cantidad = $cantidad_anterior - $cantidad;
            $this->db->set('cantidad_disponible', 'cantidad_disponible - ' . $cantidad, FALSE);
            $this->db->set('cantidad_total', 'cantidad_total - ' . $cantidad, FALSE);
        }
        
        $this->db->where('producto_id', $producto_id);
        $this->db->where('almacen_id', $almacen_id);
        $this->db->update($this->table);
        
        $movimiento_data = [
            'producto_id' => $producto_id,
            'almacen_id' => $almacen_id,
            'tipo_movimiento' => 'ajuste_' . $tipo_ajuste,
            'cantidad' => $cantidad,
            'cantidad_anterior' => $cantidad_anterior,
            'cantidad_nueva' => $nueva_cantidad,
            'motivo' => $motivo,
            'usuario_id' => $usuario_id,
            'fecha_movimiento' => date('Y-m-d H:i:s')
        ];
        
        $this->insert_movimiento($movimiento_data);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return ['success' => false, 'mensaje' => 'Error al ajustar inventario'];
        }
        
        return ['success' => true, 'mensaje' => 'Inventario ajustado correctamente'];
    }
    
    public function verificar_disponibilidad($producto_id, $almacen_id, $cantidad) {
        $this->db->select('cantidad_disponible');
        $this->db->from($this->table);
        $this->db->where('producto_id', $producto_id);
        $this->db->where('almacen_id', $almacen_id);
        
        $result = $this->db->get()->row();
        
        if (!$result) {
            return false;
        }
        
        return $result->cantidad_disponible >= $cantidad;
    }
}
