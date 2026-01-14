<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pago_model extends CI_Model {

    private $table = 'pagos';

    public function get_all($filters = []) {
        $this->db->select("p.*, c.numero_contrato, c.tipo_documento,
                          op.nombre as metodo_pago_nombre, op.tipo as metodo_pago_tipo,
                          op.nombre as opcion_pago_nombre, op.tipo as opcion_pago_tipo,
                          CONCAT_WS(' ', u.nombres, u.apellidos) as registrado_por_nombre");
        $this->db->from($this->table . ' p');
        $this->db->join('contratos c', 'c.id = p.contrato_id', 'left');
        $this->db->join('opciones_pago op', 'op.id = p.opcion_pago_id', 'left');
        $this->db->join('usuarios u', 'u.id = p.registrado_por', 'left');

        if (isset($filters['contrato_id'])) {
            $this->db->where('p.contrato_id', (int) $filters['contrato_id']);
        }

        if (isset($filters['fecha_desde'])) {
            $this->db->where('DATE(p.fecha_pago) >=', $filters['fecha_desde']);
        }

        if (isset($filters['fecha_hasta'])) {
            $this->db->where('DATE(p.fecha_pago) <=', $filters['fecha_hasta']);
        }

        $this->db->order_by('p.fecha_pago', 'DESC');
        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        $this->db->select("p.*, c.numero_contrato, c.tipo_documento,
                          op.nombre as metodo_pago_nombre, op.tipo as metodo_pago_tipo,
                          op.nombre as opcion_pago_nombre, op.tipo as opcion_pago_tipo,
                          op.qr_imagen_url,
                          CONCAT_WS(' ', u.nombres, u.apellidos) as registrado_por_nombre");
        $this->db->from($this->table . ' p');
        $this->db->join('contratos c', 'c.id = p.contrato_id', 'left');
        $this->db->join('opciones_pago op', 'op.id = p.opcion_pago_id', 'left');
        $this->db->join('usuarios u', 'u.id = p.registrado_por', 'left');
        $this->db->where('p.id', (int) $id);
        return $this->db->get()->row();
    }

    public function get_by_contrato($contrato_id) {
        $this->db->select("p.*, op.nombre as metodo_pago_nombre, op.tipo as metodo_pago_tipo,
                          op.nombre as opcion_pago_nombre, op.tipo as opcion_pago_tipo, op.qr_imagen_url,
                          CONCAT_WS(' ', u.nombres, u.apellidos) as registrado_por_nombre", false);
        $this->db->from($this->table . ' p');
        $this->db->join('opciones_pago op', 'op.id = p.opcion_pago_id', 'left');
        $this->db->join('usuarios u', 'u.id = p.registrado_por', 'left');
        $this->db->where('p.contrato_id', (int) $contrato_id);
        $this->db->order_by('p.fecha_pago', 'DESC');
        return $this->db->get()->result();
    }

    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
}
