<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Usuario_model extends CI_Model {
    
    private $table = 'usuarios';
    
    public function get_all($filters = []) {
        $this->db->select('u.*, r.nombre as rol_nombre, s.nombre as sucursal_nombre');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.rol_id');
        $this->db->join('sucursales s', 's.id = u.sucursal_id');
        $this->db->where('u.deleted_at IS NULL');
        
        if (isset($filters['activo'])) {
            $this->db->where('u.activo', $filters['activo']);
        }
        
        if (isset($filters['rol_id'])) {
            $this->db->where('u.rol_id', $filters['rol_id']);
        }
        
        if (isset($filters['sucursal_id'])) {
            $this->db->where('u.sucursal_id', $filters['sucursal_id']);
        }
        
        $this->db->order_by('u.created_at', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function get_all_with_details() {
        return $this->get_all();
    }
    
    public function get_by_id($id) {
        $this->db->select('u.*, r.nombre as rol_nombre, s.nombre as sucursal_nombre');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.rol_id');
        $this->db->join('sucursales s', 's.id = u.sucursal_id');
        $this->db->where('u.id', $id);
        $this->db->where('u.deleted_at IS NULL');
        
        return $this->db->get()->row();
    }
    
    public function get_by_username($username) {
        $username = trim((string) $username);

        $this->db->select('u.*, r.nombre as rol_nombre, s.nombre as sucursal_nombre');
        $this->db->from($this->table . ' u');
        $this->db->join('roles r', 'r.id = u.rol_id');
        $this->db->join('sucursales s', 's.id = u.sucursal_id');
        // Comparación tolerante a espacios en la BD (por ejemplo, 'admin ')
        $this->db->where('TRIM(u.username) = ' . $this->db->escape($username), null, false);
        $this->db->where('u.deleted_at IS NULL');
        
        return $this->db->get()->row();
    }
    
    public function insert($data) {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        unset($data['password']);
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
    
    public function update($id, $data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($data['password']);
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    public function delete($id) {
        $data = ['deleted_at' => date('Y-m-d H:i:s')];
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    public function get_user_permissions($user_id) {
        $this->db->select('p.codigo');
        $this->db->from('usuarios u');
        $this->db->join('rol_permisos rp', 'rp.rol_id = u.rol_id');
        $this->db->join('permisos p', 'p.id = rp.permiso_id');
        $this->db->where('u.id', $user_id);
        
        $result = $this->db->get()->result();
        
        return array_column($result, 'codigo');
    }
    
    public function increment_failed_attempts($user_id) {
        $this->db->set('intentos_fallidos', 'intentos_fallidos + 1', FALSE);
        $this->db->where('id', $user_id);
        return $this->db->update($this->table);
    }
    
    public function reset_failed_attempts($user_id) {
        $data = [
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null
        ];
        $this->db->where('id', $user_id);
        return $this->db->update($this->table, $data);
    }
    
    public function block_user($user_id, $minutes) {
        $data = [
            'bloqueado_hasta' => date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"))
        ];
        $this->db->where('id', $user_id);
        return $this->db->update($this->table, $data);
    }
    
    public function update_last_access($user_id, $ip) {
        $data = [
            'ultimo_acceso' => date('Y-m-d H:i:s'),
            'ultimo_ip' => $ip
        ];
        $this->db->where('id', $user_id);
        return $this->db->update($this->table, $data);
    }
    
    public function save_refresh_token($user_id, $token, $ip, $user_agent, $sucursal_activa_id = null) {
        $expiration = $this->config->item('refresh_token_expiration');
        
        $data = [
            'usuario_id' => $user_id,
            'sucursal_activa_id' => $sucursal_activa_id !== null ? (int) $sucursal_activa_id : null,
            'token' => $token,
            'expira_en' => date('Y-m-d H:i:s', time() + $expiration),
            'ip_address' => $ip,
            'user_agent' => $user_agent,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('refresh_tokens', $data);
        return $this->db->insert_id();
    }
    
    public function validate_refresh_token($token) {
        $this->db->where('token', $token);
        $this->db->where('revocado', 0);
        $this->db->where('expira_en >', date('Y-m-d H:i:s'));
        
        return $this->db->get('refresh_tokens')->row();
    }
    
    public function revoke_refresh_token($token) {
        $this->db->where('token', $token);
        return $this->db->update('refresh_tokens', ['revocado' => 1]);
    }
    
    /**
     * Obtiene todas las sucursales asignadas a un usuario
     * @param int $user_id ID del usuario
     * @param bool $solo_activas Si true, solo retorna sucursales activas
     * @return array Lista de sucursales
     */
    public function get_user_sucursales($user_id, $solo_activas = true) {
        $this->db->select('s.id, s.codigo, s.nombre, s.direccion, s.telefono, s.email, s.es_galpon_principal, us.es_principal, us.rol_sucursal, us.fecha_asignacion');
        $this->db->from('usuario_sucursal us');
        $this->db->join('sucursales s', 's.id = us.sucursal_id');
        $this->db->where('us.usuario_id', $user_id);
        
        if ($solo_activas) {
            $this->db->where('us.activo', 1);
            $this->db->where('s.activo', 1);
        }
        
        $this->db->order_by('us.es_principal', 'DESC');
        $this->db->order_by('s.nombre', 'ASC');
        
        return $this->db->get()->result();
    }
    
    /**
     * Obtiene la sucursal principal del usuario
     * @param int $user_id ID del usuario
     * @return object|null Sucursal principal o null
     */
    public function get_user_sucursal_principal($user_id) {
        $this->db->select('s.id, s.codigo, s.nombre, s.direccion, s.telefono, s.email, s.es_galpon_principal');
        $this->db->from('usuario_sucursal us');
        $this->db->join('sucursales s', 's.id = us.sucursal_id');
        $this->db->where('us.usuario_id', $user_id);
        $this->db->where('us.es_principal', 1);
        $this->db->where('us.activo', 1);
        $this->db->where('s.activo', 1);
        
        return $this->db->get()->row();
    }
    
    /**
     * Valida si un usuario tiene acceso a una sucursal específica
     * @param int $user_id ID del usuario
     * @param int $sucursal_id ID de la sucursal
     * @return bool True si tiene acceso, false si no
     */
    public function user_has_access_to_sucursal($user_id, $sucursal_id) {
        $this->db->where('usuario_id', $user_id);
        $this->db->where('sucursal_id', $sucursal_id);
        $this->db->where('activo', 1);
        
        $result = $this->db->get('usuario_sucursal')->row();
        return $result !== null;
    }
    
    /**
     * Asigna un usuario a una sucursal
     * @param int $user_id ID del usuario
     * @param int $sucursal_id ID de la sucursal
     * @param bool $es_principal Si es la sucursal principal
     * @param string|null $rol_sucursal Rol específico en esta sucursal
     * @return bool True si se asignó correctamente
     */
    public function asignar_sucursal($user_id, $sucursal_id, $es_principal = false, $rol_sucursal = null) {
        // Verificar si ya existe
        $this->db->where('usuario_id', $user_id);
        $this->db->where('sucursal_id', $sucursal_id);
        $existe = $this->db->get('usuario_sucursal')->row();
        
        if ($existe) {
            // Actualizar
            $data = [
                'es_principal' => $es_principal ? 1 : 0,
                'rol_sucursal' => $rol_sucursal,
                'activo' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->db->where('id', $existe->id);
            $result = $this->db->update('usuario_sucursal', $data);
        } else {
            // Insertar
            $data = [
                'usuario_id' => $user_id,
                'sucursal_id' => $sucursal_id,
                'es_principal' => $es_principal ? 1 : 0,
                'rol_sucursal' => $rol_sucursal,
                'activo' => 1,
                'fecha_asignacion' => date('Y-m-d H:i:s')
            ];
            $result = $this->db->insert('usuario_sucursal', $data);
        }
        
        // Si es principal, quitar el flag de las demás
        if ($es_principal && $result) {
            $this->db->where('usuario_id', $user_id);
            $this->db->where('sucursal_id !=', $sucursal_id);
            $this->db->update('usuario_sucursal', ['es_principal' => 0]);
        }
        
        return $result;
    }
    
    /**
     * Remueve el acceso de un usuario a una sucursal
     * @param int $user_id ID del usuario
     * @param int $sucursal_id ID de la sucursal
     * @return bool True si se removió correctamente
     */
    public function remover_sucursal($user_id, $sucursal_id) {
        // Contar cuántas sucursales activas tiene
        $this->db->where('usuario_id', $user_id);
        $this->db->where('activo', 1);
        $count = $this->db->count_all_results('usuario_sucursal');
        
        // No permitir remover si es la única
        if ($count <= 1) {
            return false;
        }
        
        // Desactivar la asignación
        $this->db->where('usuario_id', $user_id);
        $this->db->where('sucursal_id', $sucursal_id);
        return $this->db->update('usuario_sucursal', [
            'activo' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
