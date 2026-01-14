<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cliente_referencia_model extends CI_Model {

    private $table = 'cliente_referencias';

    public function insert_many($cliente_id, $referencias) {
        if (empty($referencias)) {
            return true;
        }

        $batch = [];
        foreach ($referencias as $ref) {
            $nombre = isset($ref['nombre']) ? trim((string) $ref['nombre']) : '';
            $telefono = isset($ref['telefono']) ? trim((string) $ref['telefono']) : '';
            $cargo = isset($ref['cargo']) ? trim((string) $ref['cargo']) : '';
            $observaciones = isset($ref['observaciones']) ? trim((string) $ref['observaciones']) : '';

            if ($nombre === '' || $telefono === '') {
                continue;
            }

            $batch[] = [
                'cliente_id' => (int) $cliente_id,
                'nombre' => mb_substr($nombre, 0, 100),
                'telefono' => mb_substr($telefono, 0, 20),
                'cargo' => $cargo !== '' ? mb_substr($cargo, 0, 100) : null,
                'observaciones' => $observaciones !== '' ? mb_substr($observaciones, 0, 255) : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        if (empty($batch)) {
            return true;
        }

        return $this->db->insert_batch($this->table, $batch);
    }

    public function get_by_cliente($cliente_id) {
        $this->db->where('cliente_id', (int) $cliente_id);
        $this->db->order_by('id', 'ASC');
        return $this->db->get($this->table)->result();
    }
}
