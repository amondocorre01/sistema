<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventario_nota_ingreso_model extends CI_Model {

    private $table = 'inventario_notas_ingreso';

    public function get_all($filters = []) {
        $this->db->select('n.*, a.nombre as almacen_nombre, s.nombre as sucursal_nombre, u.nombres as created_by_nombre');
        $this->db->from($this->table . ' n');
        $this->db->join('almacenes a', 'a.id = n.almacen_id');
        $this->db->join('sucursales s', 's.id = a.sucursal_id');
        $this->db->join('usuarios u', 'u.id = n.created_by');

        if (isset($filters['almacen_id'])) {
            $this->db->where('n.almacen_id', (int) $filters['almacen_id']);
        }
        if (isset($filters['estado'])) {
            $this->db->where('n.estado', (string) $filters['estado']);
        }
        if (isset($filters['fecha_desde'])) {
            $this->db->where('n.fecha >=', (string) $filters['fecha_desde']);
        }
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('n.fecha <=', (string) $filters['fecha_hasta']);
        }

        $this->db->order_by('n.id', 'DESC');
        $this->db->limit(isset($filters['limit']) ? (int) $filters['limit'] : 100);

        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        $this->db->select('n.*, a.nombre as almacen_nombre, s.nombre as sucursal_nombre, u.nombres as created_by_nombre');
        $this->db->from($this->table . ' n');
        $this->db->join('almacenes a', 'a.id = n.almacen_id');
        $this->db->join('sucursales s', 's.id = a.sucursal_id');
        $this->db->join('usuarios u', 'u.id = n.created_by');
        $this->db->where('n.id', (int) $id);
        $nota = $this->db->get()->row();

        if (!$nota) {
            return null;
        }

        $nota->detalles = $this->get_detalles((int) $id);

        return $nota;
    }

    public function get_detalles($nota_id) {
        $this->db->select('d.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida');
        $this->db->from('inventario_nota_ingreso_detalles d');
        $this->db->join('productos p', 'p.id = d.producto_id');
        $this->db->where('d.nota_ingreso_id', (int) $nota_id);
        $this->db->order_by('d.id', 'ASC');
        return $this->db->get()->result();
    }

    public function insert($data, $detalles = []) {
        $this->db->trans_begin();

        $tmp_numero = 'NI-TMP-' . uniqid('', true);
        $data['numero'] = $tmp_numero;
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert($this->table, $data);
        $id = $this->db->insert_id();

        if (!$id) {
            $this->db->trans_rollback();
            return false;
        }

        $numero = 'NI-' . date('Ymd') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $this->db->where('id', (int) $id);
        $this->db->update($this->table, ['numero' => $numero]);

        if (!empty($detalles)) {
            $ok_det = $this->replace_detalles((int) $id, $detalles);
            if (!$ok_det) {
                $this->db->trans_rollback();
                return false;
            }
        }

        $this->db->trans_commit();
        return (int) $id;
    }

    public function update($id, $data, $detalles = null) {
        $this->db->trans_begin();

        $this->db->where('id', (int) $id);
        $ok = $this->db->update($this->table, $data);

        if (!$ok) {
            $this->db->trans_rollback();
            return false;
        }

        if (is_array($detalles)) {
            $ok_det = $this->replace_detalles((int) $id, $detalles);
            if (!$ok_det) {
                $this->db->trans_rollback();
                return false;
            }
        }

        $this->db->trans_commit();
        return true;
    }

    public function replace_detalles($nota_id, $detalles) {
        $this->db->where('nota_ingreso_id', (int) $nota_id);
        $this->db->delete('inventario_nota_ingreso_detalles');

        foreach ($detalles as $d) {
            $cantidad = isset($d['cantidad']) ? (float) $d['cantidad'] : 0;
            if ($cantidad <= 0) {
                continue;
            }

            $row = [
                'nota_ingreso_id' => (int) $nota_id,
                'producto_id' => (int) ($d['producto_id'] ?? 0),
                'cantidad' => $cantidad,
                'costo_unitario' => array_key_exists('costo_unitario', $d) && $d['costo_unitario'] !== '' ? (float) $d['costo_unitario'] : null,
                'observaciones' => isset($d['observaciones']) && trim((string) $d['observaciones']) !== '' ? trim((string) $d['observaciones']) : null
            ];

            if ((int) $row['producto_id'] <= 0) {
                continue;
            }

            $this->db->insert('inventario_nota_ingreso_detalles', $row);
        }

        return true;
    }

    public function set_estado($id, $data) {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, $data);
    }
}
