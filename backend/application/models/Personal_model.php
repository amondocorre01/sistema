<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Personal_model extends CI_Model {

    private $table = 'personal';

    public function get_all($filters = []) {
        $this->db->select('p.*,
            u.username as usuario_username,
            CONCAT(u.nombres, " ", u.apellidos) as usuario_nombre_completo');
        $this->db->from($this->table . ' p');
        $this->db->join('usuarios u', 'u.id = p.usuario_id', 'left');
        $this->db->where('p.deleted_at IS NULL');

        if (isset($filters['activo'])) {
            $this->db->where('p.activo', (int) $filters['activo']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $this->db->group_start();
            $this->db->like('p.nombre_completo', $search);
            $this->db->or_like('p.ci_numero', $search);
            $this->db->group_end();
        }

        $this->db->order_by('p.nombre_completo', 'ASC');

        return $this->db->get()->result();
    }

    public function get_by_id($id) {
        $this->db->select('p.*,
            u.username as usuario_username,
            CONCAT(u.nombres, " ", u.apellidos) as usuario_nombre_completo');
        $this->db->from($this->table . ' p');
        $this->db->join('usuarios u', 'u.id = p.usuario_id', 'left');
        $this->db->where('p.id', (int) $id);
        $this->db->where('p.deleted_at IS NULL');
        return $this->db->get()->row();
    }

    public function get_by_ci($ci_numero, $exclude_id = null) {
        $this->db->from($this->table);
        $this->db->where('ci_numero', trim((string) $ci_numero));
        $this->db->where('deleted_at IS NULL');
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

    public function delete($id) {
        $this->db->where('id', (int) $id);
        return $this->db->update($this->table, [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
    }
}
