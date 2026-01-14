<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transporte_model extends CI_Model {

    private $table = 'transportes';

    public function get_all($filters = []) {
        $this->db->from($this->table);

        if (isset($filters['activo'])) {
            $this->db->where('activo', (int) $filters['activo']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $this->db->group_start();
            $this->db->like('nombre_completo', $search);
            $this->db->or_like('ci', $search);
            $this->db->or_like('placa', $search);
            $this->db->group_end();
        }

        $this->db->order_by('nombre_completo', 'ASC');
        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        return $this->db->get_where($this->table, ['id' => (int) $id])->row();
    }

    public function get_by_ci($ci, $exclude_id = null) {
        $this->db->from($this->table);
        $this->db->where('ci', trim((string) $ci));
        if ($exclude_id !== null) {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return $this->db->get()->row();
    }

    public function get_by_placa($placa, $exclude_id = null) {
        $this->db->from($this->table);
        $this->db->where('placa', trim((string) $placa));
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
