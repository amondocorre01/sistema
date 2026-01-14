<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Usuario_model');
        $this->load->model('Auditoria_model');
    }
    
    public function login() {
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'username' => 'Usuario',
            'password' => 'Contraseña'
        ], $data);
        
        $username = trim((string) $data['username']);
        $password = (string) $data['password'];
        
        // Evita fallos por espacios invisibles (por ejemplo, copiar/pegar)
        $password = trim($password);
        
        $usuario = $this->Usuario_model->get_by_username($username);
        
        if (!$usuario) {
            $this->response_library->error('Credenciales inválidas', 401);
        }
        
        if (!$usuario->activo) {
            $this->response_library->error('Usuario inactivo', 403);
        }
        
        if ($usuario->bloqueado_hasta && strtotime($usuario->bloqueado_hasta) > time()) {
            $this->response_library->error('Usuario bloqueado temporalmente', 403);
        }
        
        if (!password_verify($password, $usuario->password_hash)) {
            $this->Usuario_model->increment_failed_attempts($usuario->id);
            
            $intentos = $usuario->intentos_fallidos + 1;
            if ($intentos >= 5) {
                $this->Usuario_model->block_user($usuario->id, 15);
                $this->response_library->error('Usuario bloqueado por múltiples intentos fallidos', 403);
            }
            
            $this->response_library->error('Credenciales inválidas', 401);
        }
        
        $this->Usuario_model->reset_failed_attempts($usuario->id);
        $this->Usuario_model->update_last_access($usuario->id, $this->input->ip_address());
        
        // Obtener sucursales asignadas al usuario
        $sucursales = $this->Usuario_model->get_user_sucursales($usuario->id);
        
        if (empty($sucursales)) {
            $this->response_library->error('Usuario sin sucursales asignadas. Contacte al administrador.', 403);
        }
        
        // Si tiene una sola sucursal, asignarla automáticamente
        if (count($sucursales) === 1) {
            $sucursal_activa = $sucursales[0];
            
            $permisos = $this->Usuario_model->get_user_permissions($usuario->id);
            
            $token_payload = [
                'user_id' => $usuario->id,
                'username' => $usuario->username,
                'rol' => $usuario->rol_nombre,
                'rol_id' => $usuario->rol_id,
                'sucursal_id' => $usuario->sucursal_id,
                'sucursal_activa_id' => $sucursal_activa->id,
                'permisos' => $permisos
            ];
            
            $token = $this->jwt_library->encode($token_payload);
            
            $refresh_token = bin2hex(random_bytes(32));
            $this->Usuario_model->save_refresh_token(
                $usuario->id,
                $refresh_token,
                $this->input->ip_address(),
                $this->input->user_agent(),
                $sucursal_activa->id
            );
            
            $this->Auditoria_model->insert([
                'tabla_afectada' => 'usuarios',
                'registro_id' => $usuario->id,
                'accion' => 'LOGIN',
                'usuario_id' => $usuario->id,
                'rol_usuario' => $usuario->rol_nombre,
                'ip_address' => $this->input->ip_address(),
                'user_agent' => $this->input->user_agent(),
                'fecha_accion' => date('Y-m-d H:i:s'),
                'modulo' => 'auth',
                'descripcion' => 'Inicio de sesión exitoso - Sucursal: ' . $sucursal_activa->nombre
            ]);
            
            $this->response_library->success([
                'token' => $token,
                'refresh_token' => $refresh_token,
                'user' => [
                    'id' => $usuario->id,
                    'username' => $usuario->username,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol_nombre,
                    'sucursal_id' => $usuario->sucursal_id,
                    'sucursal_nombre' => $usuario->sucursal_nombre,
                    'sucursal_activa' => [
                        'id' => $sucursal_activa->id,
                        'codigo' => $sucursal_activa->codigo,
                        'nombre' => $sucursal_activa->nombre
                    ],
                    'permisos' => $permisos
                ]
            ], 'Inicio de sesión exitoso');
        } else {
            // Múltiples sucursales: retornar lista para que el usuario seleccione
            $this->Auditoria_model->insert([
                'tabla_afectada' => 'usuarios',
                'registro_id' => $usuario->id,
                'accion' => 'LOGIN_PENDING_SUCURSAL',
                'usuario_id' => $usuario->id,
                'rol_usuario' => $usuario->rol_nombre,
                'ip_address' => $this->input->ip_address(),
                'user_agent' => $this->input->user_agent(),
                'fecha_accion' => date('Y-m-d H:i:s'),
                'modulo' => 'auth',
                'descripcion' => 'Login exitoso - Pendiente selección de sucursal'
            ]);
            
            // Generar token temporal solo con info básica (sin sucursal_activa)
            $temp_token_payload = [
                'user_id' => $usuario->id,
                'username' => $usuario->username,
                'temp' => true,
                'exp' => time() + 300 // 5 minutos para seleccionar sucursal
            ];
            
            $temp_token = $this->jwt_library->encode($temp_token_payload);
            
            $this->response_library->success([
                'requires_sucursal_selection' => true,
                'temp_token' => $temp_token,
                'user' => [
                    'id' => $usuario->id,
                    'username' => $usuario->username,
                    'nombres' => $usuario->nombres,
                    'apellidos' => $usuario->apellidos,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol_nombre
                ],
                'sucursales' => array_map(function($s) {
                    return [
                        'id' => $s->id,
                        'codigo' => $s->codigo,
                        'nombre' => $s->nombre,
                        'es_principal' => (bool) $s->es_principal
                    ];
                }, $sucursales)
            ], 'Seleccione una sucursal para continuar');
        }
    }
    
    public function refresh() {
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        if (!isset($data['refresh_token'])) {
            $this->response_library->error('Refresh token requerido', 400);
        }
        
        $refresh_token = $data['refresh_token'];
        
        $token_data = $this->Usuario_model->validate_refresh_token($refresh_token);
        
        if (!$token_data) {
            $this->response_library->error('Refresh token inválido o expirado', 401);
        }
        
        $usuario = $this->Usuario_model->get_by_id($token_data->usuario_id);
        
        if (!$usuario || !$usuario->activo) {
            $this->response_library->error('Usuario inválido', 401);
        }
        
        $permisos = $this->Usuario_model->get_user_permissions($usuario->id);
        
        // Mantener sucursal_activa_id del token anterior si existe
        $sucursal_activa_id = $token_data->sucursal_activa_id ?? null;
        
        $token_payload = [
            'user_id' => $usuario->id,
            'username' => $usuario->username,
            'rol' => $usuario->rol_nombre,
            'rol_id' => $usuario->rol_id,
            'sucursal_id' => $usuario->sucursal_id,
            'permisos' => $permisos
        ];
        
        // Incluir sucursal_activa_id si estaba presente en el token anterior
        if ($sucursal_activa_id) {
            $token_payload['sucursal_activa_id'] = $sucursal_activa_id;
        }
        
        $new_token = $this->jwt_library->encode($token_payload);
        
        $this->response_library->success([
            'token' => $new_token
        ], 'Token renovado exitosamente');
    }
    
    public function logout() {
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $this->authenticate();
        
        $data = $this->get_json_input();
        
        if (isset($data['refresh_token'])) {
            $this->Usuario_model->revoke_refresh_token($data['refresh_token']);
        }
        
        $this->Auditoria_model->insert([
            'tabla_afectada' => 'usuarios',
            'registro_id' => $this->user_data['user_id'],
            'accion' => 'LOGOUT',
            'usuario_id' => $this->user_data['user_id'],
            'rol_usuario' => $this->user_data['rol'],
            'ip_address' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
            'fecha_accion' => date('Y-m-d H:i:s'),
            'modulo' => 'auth',
            'descripcion' => 'Cierre de sesión'
        ]);
        
        $this->response_library->success(null, 'Sesión cerrada exitosamente');
    }
    
    public function me() {
        if ($this->input->method() !== 'get') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $this->authenticate();
        
        $usuario = $this->Usuario_model->get_by_id($this->user_data['user_id']);
        
        if (!$usuario) {
            $this->response_library->error('Usuario no encontrado', 404);
        }
        
        $permisos = $this->Usuario_model->get_user_permissions($usuario->id);
        $sucursales = $this->Usuario_model->get_user_sucursales($usuario->id);
        
        $sucursal_activa = null;
        if (isset($this->user_data['sucursal_activa_id'])) {
            foreach ($sucursales as $s) {
                if ($s->id == $this->user_data['sucursal_activa_id']) {
                    $sucursal_activa = [
                        'id' => $s->id,
                        'codigo' => $s->codigo,
                        'nombre' => $s->nombre
                    ];
                    break;
                }
            }
        }
        
        $this->response_library->success([
            'id' => $usuario->id,
            'username' => $usuario->username,
            'nombres' => $usuario->nombres,
            'apellidos' => $usuario->apellidos,
            'email' => $usuario->email,
            'rol' => $usuario->rol_nombre,
            'sucursal_id' => $usuario->sucursal_id,
            'sucursal_nombre' => $usuario->sucursal_nombre,
            'sucursal_activa' => $sucursal_activa,
            'sucursales' => array_map(function($s) {
                return [
                    'id' => $s->id,
                    'codigo' => $s->codigo,
                    'nombre' => $s->nombre,
                    'es_principal' => (bool) $s->es_principal
                ];
            }, $sucursales),
            'permisos' => $permisos
        ]);
    }
    
    /**
     * Selecciona la sucursal activa después del login (cuando tiene múltiples sucursales)
     */
    public function set_sucursal_activa() {
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        // Validar token temporal o token completo
        $auth_header = $this->input->get_request_header('Authorization');
        if (!$auth_header || !preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
            $this->response_library->unauthorized('Token no proporcionado');
        }
        
        $token = $matches[1];
        $decoded = $this->jwt_library->decode($token);
        
        if (!$decoded) {
            $this->response_library->unauthorized('Token inválido');
        }
        
        $user_id = $decoded['user_id'];
        
        $this->validate_required([
            'sucursal_id' => 'Sucursal'
        ], $data);
        
        $sucursal_id = (int) $data['sucursal_id'];
        
        // Validar que el usuario tenga acceso a esta sucursal
        if (!$this->Usuario_model->user_has_access_to_sucursal($user_id, $sucursal_id)) {
            $this->response_library->error('No tiene acceso a esta sucursal', 403);
        }
        
        // Obtener datos del usuario
        $usuario = $this->Usuario_model->get_by_id($user_id);
        if (!$usuario || !$usuario->activo) {
            $this->response_library->error('Usuario inválido', 401);
        }
        
        $permisos = $this->Usuario_model->get_user_permissions($user_id);
        $sucursales = $this->Usuario_model->get_user_sucursales($user_id);
        
        $sucursal_seleccionada = null;
        foreach ($sucursales as $s) {
            if ($s->id == $sucursal_id) {
                $sucursal_seleccionada = $s;
                break;
            }
        }
        
        if (!$sucursal_seleccionada) {
            $this->response_library->error('Sucursal no encontrada', 404);
        }
        
        // Generar token completo con sucursal_activa
        $token_payload = [
            'user_id' => $usuario->id,
            'username' => $usuario->username,
            'rol' => $usuario->rol_nombre,
            'rol_id' => $usuario->rol_id,
            'sucursal_id' => $usuario->sucursal_id,
            'sucursal_activa_id' => $sucursal_id,
            'permisos' => $permisos
        ];
        
        $new_token = $this->jwt_library->encode($token_payload);
        
        $refresh_token = bin2hex(random_bytes(32));
        $this->Usuario_model->save_refresh_token(
            $usuario->id,
            $refresh_token,
            $this->input->ip_address(),
            $this->input->user_agent(),
            $sucursal_id
        );
        
        $this->Auditoria_model->insert([
            'tabla_afectada' => 'usuarios',
            'registro_id' => $usuario->id,
            'accion' => 'SET_SUCURSAL_ACTIVA',
            'usuario_id' => $usuario->id,
            'rol_usuario' => $usuario->rol_nombre,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
            'fecha_accion' => date('Y-m-d H:i:s'),
            'modulo' => 'auth',
            'descripcion' => 'Sucursal activa establecida: ' . $sucursal_seleccionada->nombre
        ]);
        
        $this->response_library->success([
            'token' => $new_token,
            'refresh_token' => $refresh_token,
            'user' => [
                'id' => $usuario->id,
                'username' => $usuario->username,
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'email' => $usuario->email,
                'rol' => $usuario->rol_nombre,
                'sucursal_id' => $usuario->sucursal_id,
                'sucursal_nombre' => $usuario->sucursal_nombre,
                'sucursal_activa' => [
                    'id' => $sucursal_seleccionada->id,
                    'codigo' => $sucursal_seleccionada->codigo,
                    'nombre' => $sucursal_seleccionada->nombre
                ],
                'permisos' => $permisos
            ]
        ], 'Sucursal activa establecida exitosamente');
    }
    
    /**
     * Cambia la sucursal activa durante la sesión (para usuarios con múltiples sucursales)
     */
    public function cambiar_sucursal() {
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $this->authenticate();
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'sucursal_id' => 'Sucursal'
        ], $data);
        
        $sucursal_id = (int) $data['sucursal_id'];
        $user_id = $this->user_data['user_id'];
        
        // Validar que el usuario tenga acceso a esta sucursal
        if (!$this->Usuario_model->user_has_access_to_sucursal($user_id, $sucursal_id)) {
            $this->response_library->error('No tiene acceso a esta sucursal', 403);
        }
        
        $usuario = $this->Usuario_model->get_by_id($user_id);
        $permisos = $this->Usuario_model->get_user_permissions($user_id);
        $sucursales = $this->Usuario_model->get_user_sucursales($user_id);
        
        $sucursal_seleccionada = null;
        foreach ($sucursales as $s) {
            if ($s->id == $sucursal_id) {
                $sucursal_seleccionada = $s;
                break;
            }
        }
        
        if (!$sucursal_seleccionada) {
            $this->response_library->error('Sucursal no encontrada', 404);
        }
        
        // Generar nuevo token con la nueva sucursal_activa
        $token_payload = [
            'user_id' => $usuario->id,
            'username' => $usuario->username,
            'rol' => $usuario->rol_nombre,
            'rol_id' => $usuario->rol_id,
            'sucursal_id' => $usuario->sucursal_id,
            'sucursal_activa_id' => $sucursal_id,
            'permisos' => $permisos
        ];
        
        $new_token = $this->jwt_library->encode($token_payload);
        
        $this->Auditoria_model->insert([
            'tabla_afectada' => 'usuarios',
            'registro_id' => $usuario->id,
            'accion' => 'CAMBIAR_SUCURSAL',
            'usuario_id' => $usuario->id,
            'rol_usuario' => $usuario->rol_nombre,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
            'fecha_accion' => date('Y-m-d H:i:s'),
            'modulo' => 'auth',
            'descripcion' => 'Cambio de sucursal a: ' . $sucursal_seleccionada->nombre
        ]);
        
        $refresh_token = bin2hex(random_bytes(32));
        $this->Usuario_model->save_refresh_token(
            $usuario->id,
            $refresh_token,
            $this->input->ip_address(),
            $this->input->user_agent(),
            $sucursal_id
        );

        $this->response_library->success([
            'token' => $new_token,
            'refresh_token' => $refresh_token,
            'sucursal_activa' => [
                'id' => $sucursal_seleccionada->id,
                'codigo' => $sucursal_seleccionada->codigo,
                'nombre' => $sucursal_seleccionada->nombre
            ]
        ], 'Sucursal cambiada exitosamente');
    }
}
