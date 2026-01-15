<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Response_Library {
    
    private $CI;
    
    public function __construct() {
        $this->CI =& get_instance();
    }
    
    public function success($data = null, $message = 'Operación exitosa', $code = 200) {
        $this->output($code, [
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    public function error($message = 'Error en la operación', $code = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->output($code, $response);
    }
    
    public function unauthorized($message = 'No autorizado') {
        $this->output(401, [
            'success' => false,
            'message' => $message
        ]);
    }
    
    public function forbidden($message = 'Acceso denegado') {
        $this->output(403, [
            'success' => false,
            'message' => $message
        ]);
    }
    
    public function not_found($message = 'Recurso no encontrado') {
        $this->output(404, [
            'success' => false,
            'message' => $message
        ]);
    }
    
    public function validation_error($errors, $message = 'Errores de validación') {
        $this->output(422, [
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
    }
    
    private function output($code, $data) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';

        header('Access-Control-Allow-Origin: '.$origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        $this->CI->output
            ->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
            ->_display();
        exit;
    }
}
