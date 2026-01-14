<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Personal_referencia_model extends CI_Model {

    private $table = 'personal_referencias';

    public function get_by_personal($personal_id) {
        $this->db->from($this->table);
        $this->db->where('personal_id', (int) $personal_id);
        $this->db->order_by('id', 'ASC');
        return $this->db->get()->result_array();
    }

    public function replace_many($personal_id, $referencias) {
        $personal_id = (int) $personal_id;

        $this->db->where('personal_id', $personal_id);
        $this->db->delete($this->table);

        if (empty($referencias) || !is_array($referencias)) {
            return true;
        }

        $batch = [];
        foreach ($referencias as $ref) {
            $nombre = isset($ref['nombre']) ? trim((string) $ref['nombre']) : '';
            $parentesco = isset($ref['parentesco']) ? trim((string) $ref['parentesco']) : '';
            $telefono = isset($ref['telefono']) ? trim((string) $ref['telefono']) : '';

            if ($nombre === '' || $telefono === '') {
                continue;
            }

            $batch[] = [
                'personal_id' => $personal_id,
                'nombre' => mb_substr($nombre, 0, 100),
                'parentesco' => $parentesco !== '' ? mb_substr($parentesco, 0, 50) : null,
                'telefono' => mb_substr($telefono, 0, 20),
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        if (empty($batch)) {
            return true;
        }

        return $this->db->insert_batch($this->table, $batch);
    }
}
