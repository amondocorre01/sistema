<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class OpcionPago_model extends CI_Model {

    private $table = 'opciones_pago';

    public function get_all($filters = []) {
        $this->db->from($this->table);

        if (isset($filters['activo'])) {
            $this->db->where('activo', (int) $filters['activo']);
        }

        if (isset($filters['tipo']) && trim((string) $filters['tipo']) !== '') {
            $this->db->where('tipo', strtoupper(trim((string) $filters['tipo'])));
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $this->db->group_start();
            $this->db->like('nombre', $search);
            $this->db->or_like('descripcion', $search);
            $this->db->group_end();
        }

        $this->db->order_by('nombre', 'ASC');
        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        return $this->db->get_where($this->table, ['id' => (int) $id])->row();
    }

    public function get_by_nombre($nombre, $exclude_id = null) {
        $this->db->from($this->table);
        $this->db->where('nombre', trim((string) $nombre));
        if ($exclude_id !== null) {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return $this->db->get()->row();
    }

    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, $data);
    }

    public function disable($id) {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, ['activo' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
