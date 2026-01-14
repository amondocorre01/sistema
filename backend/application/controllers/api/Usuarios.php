<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Usuarios extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->model('Usuario_model');
        $this->load->model('Auditoria_model');
    }
    
    public function index() {
        $this->authenticate();
        $this->check_permission('usuarios.leer');
        
        $usuarios = $this->Usuario_model->get_all_with_details();
        
        $this->response_library->success($usuarios);
    }
    
    public function show($id) {
        $this->authenticate();
        $this->check_permission('usuarios.leer');
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response_library->not_found('Usuario no encontrado');
        }
        
        $this->response_library->success($usuario);
    }
    
    public function create() {
        $this->authenticate();
        $this->check_permission('usuarios.crear');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'username' => 'Usuario',
            'password' => 'Contraseña',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'email' => 'Email',
            'rol_id' => 'Rol'
        ], $data);
        
        $username = trim($data['username']);
        $password = $data['password'];
        $nombres = trim($data['nombres']);
        $apellidos = trim($data['apellidos']);
        $email = trim($data['email']);
        $telefono = isset($data['telefono']) ? trim($data['telefono']) : null;
        $rol_id = (int) $data['rol_id'];
        $activo = isset($data['activo']) ? (int) $data['activo'] : 1;
        
        if (strlen($password) < 6) {
            $this->response_library->error('La contraseña debe tener al menos 6 caracteres', 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response_library->error('Email inválido', 400);
        }
        
        $this->db->where('username', $username);
        $this->db->where('deleted_at IS NULL');
        $existe = $this->db->get('usuarios')->row();
        
        if ($existe) {
            $this->response_library->error('El nombre de usuario ya existe', 400);
        }
        
        $this->db->where('email', $email);
        $this->db->where('deleted_at IS NULL');
        $existe_email = $this->db->get('usuarios')->row();
        
        if ($existe_email) {
            $this->response_library->error('El email ya está registrado', 400);
        }
        
        $sucursal_activa = $this->get_sucursal_activa();
        
        $usuario_data = [
            'username' => $username,
            'password' => $password,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email' => $email,
            'telefono' => $telefono,
            'rol_id' => $rol_id,
            'sucursal_id' => $sucursal_activa,
            'activo' => $activo
        ];

        if ($this->has_column('usuarios', 'created_by')) {
            $usuario_data['created_by'] = $this->user_data['user_id'];
        }
        
        $this->db->trans_begin();
        
        $usuario_id = $this->Usuario_model->insert($usuario_data);
        
        if (!$usuario_id) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al crear usuario');
        }
        
        $asignado = $this->Usuario_model->asignar_sucursal($usuario_id, $sucursal_activa, true);
        
        if (!$asignado) {
            $this->db->trans_rollback();
            $this->response_library->error('Error al asignar sucursal al usuario');
        }
        
        $this->db->trans_commit();
        
        $this->log_audit('usuarios', $usuario_id, 'INSERT', null, $usuario_data, 'Usuario creado');
        
        $this->response_library->success(['id' => $usuario_id], 'Usuario creado exitosamente', 201);
    }
    
    public function update($id) {
        $this->authenticate();
        $this->check_permission('usuarios.actualizar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response_library->not_found('Usuario no encontrado');
        }
        
        $data = $this->get_json_input();
        
        $update_data = [];
        
        if (isset($data['nombres'])) {
            $update_data['nombres'] = trim($data['nombres']);
        }
        
        if (isset($data['apellidos'])) {
            $update_data['apellidos'] = trim($data['apellidos']);
        }
        
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->response_library->error('Email inválido', 400);
            }
            
            $this->db->where('email', $email);
            $this->db->where('id !=', $id);
            $this->db->where('deleted_at IS NULL');
            $existe_email = $this->db->get('usuarios')->row();
            
            if ($existe_email) {
                $this->response_library->error('El email ya está registrado', 400);
            }
            
            $update_data['email'] = $email;
        }
        
        if (isset($data['telefono'])) {
            $update_data['telefono'] = trim($data['telefono']);
        }
        
        if (isset($data['password']) && !empty(trim($data['password']))) {
            if (strlen($data['password']) < 6) {
                $this->response_library->error('La contraseña debe tener al menos 6 caracteres', 400);
            }
            $update_data['password'] = trim($data['password']);
        }
        
        if (isset($data['rol_id'])) {
            $update_data['rol_id'] = (int) $data['rol_id'];
        }
        
        if (isset($data['activo'])) {
            $update_data['activo'] = (int) $data['activo'];
        }
        
        if (empty($update_data)) {
            $this->response_library->error('No hay datos para actualizar', 400);
        }
        
        if ($this->has_column('usuarios', 'updated_by')) {
            $update_data['updated_by'] = $this->user_data['user_id'];
        }
        
        $updated = $this->Usuario_model->update($id, $update_data);
        
        if (!$updated) {
            $this->response_library->error('Error al actualizar usuario');
        }
        
        $this->log_audit('usuarios', $id, 'UPDATE', (array)$usuario, $update_data, 'Usuario actualizado');
        
        $this->response_library->success(null, 'Usuario actualizado exitosamente');
    }
    
    public function delete($id) {
        $this->authenticate();
        $this->check_permission('usuarios.eliminar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response_library->not_found('Usuario no encontrado');
        }
        
        if ($usuario->id == $this->user_data['user_id']) {
            $this->response_library->error('No puede eliminar su propio usuario', 400);
        }
        
        $deleted = $this->Usuario_model->delete($id);
        
        if (!$deleted) {
            $this->response_library->error('Error al eliminar usuario');
        }
        
        $this->log_audit('usuarios', $id, 'DELETE', (array)$usuario, null, 'Usuario eliminado');
        
        $this->response_library->success(null, 'Usuario eliminado exitosamente');
    }
    
    public function get_sucursales($id) {
        $this->authenticate();
        $this->check_permission('usuarios.leer');
        
        $sucursales = $this->Usuario_model->get_user_sucursales($id);
        
        $this->response_library->success($sucursales);
    }
    
    public function asignar_sucursal($id) {
        $this->authenticate();
        $this->check_permission('usuarios.actualizar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response_library->not_found('Usuario no encontrado');
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'sucursal_id' => 'Sucursal'
        ], $data);
        
        $sucursal_id = (int) $data['sucursal_id'];
        $es_principal = isset($data['es_principal']) ? (bool) $data['es_principal'] : false;
        $rol_sucursal = isset($data['rol_sucursal']) ? trim($data['rol_sucursal']) : null;
        
        $this->db->where('id', $sucursal_id);
        $this->db->where('activo', 1);
        $sucursal = $this->db->get('sucursales')->row();
        
        if (!$sucursal) {
            $this->response_library->error('Sucursal no encontrada o inactiva', 404);
        }
        
        $asignado = $this->Usuario_model->asignar_sucursal($id, $sucursal_id, $es_principal, $rol_sucursal);
        
        if (!$asignado) {
            $this->response_library->error('Error al asignar sucursal');
        }
        
        $this->log_audit('usuario_sucursal', $id, 'INSERT', null, [
            'usuario_id' => $id,
            'sucursal_id' => $sucursal_id,
            'es_principal' => $es_principal,
            'rol_sucursal' => $rol_sucursal
        ], "Sucursal {$sucursal->nombre} asignada al usuario");
        
        $this->response_library->success(null, 'Sucursal asignada exitosamente');
    }
    
    public function remover_sucursal($id) {
        $this->authenticate();
        $this->check_permission('usuarios.actualizar');
        
        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }
        
        $usuario = $this->Usuario_model->get_by_id($id);
        
        if (!$usuario) {
            $this->response_library->not_found('Usuario no encontrado');
        }
        
        $data = $this->get_json_input();
        
        $this->validate_required([
            'sucursal_id' => 'Sucursal'
        ], $data);
        
        $sucursal_id = (int) $data['sucursal_id'];
        
        $removido = $this->Usuario_model->remover_sucursal($id, $sucursal_id);
        
        if (!$removido) {
            $this->response_library->error('No se puede remover la sucursal. El usuario debe tener al menos una sucursal asignada.', 400);
        }
        
        $this->log_audit('usuario_sucursal', $id, 'DELETE', null, [
            'usuario_id' => $id,
            'sucursal_id' => $sucursal_id
        ], "Sucursal removida del usuario");
        
        $this->response_library->success(null, 'Sucursal removida exitosamente');
    }
}
