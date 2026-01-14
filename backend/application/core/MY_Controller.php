<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {
    
    protected $user_data = null;
    
    public function __construct() {
        parent::__construct();

        $this->load->database();
        
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        if ($this->input->method() === 'options') {
            exit(0);
        }
        
        $this->load->library('response_library');
        $this->load->library('jwt_library');
    }
    
    protected function authenticate() {
        $headers = $this->input->request_headers();
        
        if (!isset($headers['Authorization'])) {
            $this->response_library->unauthorized('Token no proporcionado');
        }
        
        $auth_header = $headers['Authorization'];
        $token = str_replace('Bearer ', '', $auth_header);
        
        $decoded = $this->jwt_library->decode($token);
        
        if (!$decoded) {
            $this->response_library->unauthorized('Token inválido o expirado');
        }
        
        $this->user_data = $decoded;
        
        return $decoded;
    }
    
    protected function check_permission($permission) {
        if (!$this->user_data) {
            $this->authenticate();
        }
        
        if ($this->user_data['rol'] === 'administrador') {
            return true;
        }
        
        if (!in_array($permission, $this->user_data['permisos'])) {
            $this->response_library->forbidden('No tiene permisos para esta acción');
        }
        
        return true;
    }
    
    protected function validate_required($fields, $data) {
        $errors = [];
        
        foreach ($fields as $field => $label) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "El campo {$label} es requerido";
            }
        }
        
        if (!empty($errors)) {
            $this->response_library->validation_error($errors);
        }
        
        return true;
    }
    
    protected function get_json_input() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    protected function log_audit($tabla, $registro_id, $accion, $datos_anteriores = null, $datos_nuevos = null, $descripcion = '') {
        $this->load->model('Auditoria_model');
        
        $audit_data = [
            'tabla_afectada' => $tabla,
            'registro_id' => $registro_id,
            'accion' => $accion,
            'usuario_id' => $this->user_data['user_id'] ?? null,
            'rol_usuario' => $this->user_data['rol'] ?? null,
            'datos_anteriores' => $datos_anteriores ? json_encode($datos_anteriores) : null,
            'datos_nuevos' => $datos_nuevos ? json_encode($datos_nuevos) : null,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
            'fecha_accion' => date('Y-m-d H:i:s'),
            'descripcion' => $descripcion
        ];
        
        $this->Auditoria_model->insert($audit_data);
    }

    /**
     * Verifica si una columna existe en una tabla dada.
     * Útil para funciones que deben ser compatibles con múltiples versiones de la BD.
     */
    protected function has_column($table, $column) {
        if (!$table || !$column) {
            return false;
        }
        return $this->db->field_exists($column, $table);
    }
    
    /**
     * Obtiene el ID de la sucursal activa del usuario desde el token JWT
     * @param bool $required Si es true, lanza error si no hay sucursal_activa
     * @return int|null ID de la sucursal activa o null
     */
    protected function get_sucursal_activa($required = true) {
        if (!$this->user_data) {
            $this->authenticate();
        }
        
        $sucursal_activa_id = $this->user_data['sucursal_activa_id'] ?? null;
        
        if ($required && !$sucursal_activa_id) {
            $this->response_library->error('No hay sucursal activa. Por favor seleccione una sucursal.', 400);
        }
        
        return $sucursal_activa_id;
    }
    
    /**
     * Valida que el usuario tenga acceso a una sucursal específica
     * @param int $sucursal_id ID de la sucursal a validar
     * @return bool True si tiene acceso
     */
    protected function validate_sucursal_access($sucursal_id) {
        if (!$this->user_data) {
            $this->authenticate();
        }
        
        $this->load->model('Usuario_model');
        
        $has_access = $this->Usuario_model->user_has_access_to_sucursal(
            $this->user_data['user_id'],
            $sucursal_id
        );
        
        if (!$has_access) {
            $this->response_library->forbidden('No tiene acceso a esta sucursal');
        }
        
        return true;
    }
    
    /**
     * Valida que la sucursal activa del usuario coincida con la sucursal del registro
     * Útil para operaciones de actualización/eliminación
     * @param int $registro_sucursal_id ID de la sucursal del registro
     * @param string $mensaje_error Mensaje personalizado de error
     * @return bool True si coincide
     */
    protected function validate_sucursal_match($registro_sucursal_id, $mensaje_error = 'No puede modificar registros de otra sucursal') {
        $sucursal_activa = $this->get_sucursal_activa(true);
        
        if ((int)$sucursal_activa !== (int)$registro_sucursal_id) {
            $this->response_library->forbidden($mensaje_error);
        }
        
        return true;
    }
}
