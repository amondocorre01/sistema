<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reportes extends MY_Controller {

    private $has_garantia_monto = false;

    public function __construct() {
        parent::__construct();
        $this->load->model('Contrato_model');
        $this->load->model('Pago_model');
        $this->load->model('Inventario_model');
        $this->load->model('Entrega_model');
        $this->load->model('Devolucion_model');

        // Compatibilidad: algunos entornos tienen garantia_monto en contratos.
        try {
            $this->has_garantia_monto = $this->db->field_exists('garantia_monto', 'contratos');
        } catch (Exception $e) {
            $this->has_garantia_monto = false;
        }
    }

    private function require_any_permission($permissions = []) {
        if (!$this->user_data) {
            $this->authenticate();
        }

        if (($this->user_data['rol'] ?? '') === 'administrador') {
            return true;
        }

        $perms = $this->user_data['permisos'] ?? [];
        foreach ($permissions as $p) {
            if (in_array($p, $perms)) {
                return true;
            }
        }

        $this->response_library->forbidden('No tiene permisos para esta acción');
    }

    private function get_contratos_con_saldo($filters = []) {
        $estados = isset($filters['estados']) ? $filters['estados'] : null;
        $only_with_debt = isset($filters['only_with_debt']) ? (bool) $filters['only_with_debt'] : false;
        $order_by = isset($filters['order_by']) ? $filters['order_by'] : 'c.fecha_fin_alquiler ASC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;

        $garantia_select = $this->has_garantia_monto ? 'IFNULL(c.garantia_monto, 0)' : '0';
        $this->db->select("c.id, c.numero_contrato, c.estado, c.fecha_contrato, c.fecha_inicio_alquiler, c.fecha_fin_alquiler, c.total, {$garantia_select} as garantia_monto, cl.razon_social as cliente_nombre", false);
        $this->db->select("IFNULL(pp.pagado, 0) as pagado, (c.total + {$garantia_select} - IFNULL(pp.pagado, 0)) as saldo", false);
        $this->db->from('contratos c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('(SELECT contrato_id, SUM((monto - IFNULL(descuento_aplicado,0))) as pagado FROM pagos GROUP BY contrato_id) pp', 'pp.contrato_id = c.id', 'left', false);
        $this->db->where('c.deleted_at IS NULL');
        $this->db->where('c.estado !=', 'cancelado');

        if (is_array($estados) && !empty($estados)) {
            $this->db->where_in('c.estado', $estados);
        } elseif (is_string($estados) && $estados !== '') {
            $this->db->where('c.estado', $estados);
        }

        if ($only_with_debt) {
            $this->db->having('saldo >', 0.01);
        }

        if ($order_by) {
            $parts = preg_split('/\s+/', trim((string) $order_by));
            if (count($parts) >= 1) {
                $col = $parts[0];
                $dir = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                if (!in_array($dir, ['ASC', 'DESC'])) $dir = 'ASC';
                $this->db->order_by($col, $dir);
            }
        }

        if ($limit > 0) {
            $this->db->limit($limit);
        }

        return $this->db->get()->result();
    }

    public function contratos_nuevos() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'contratos.leer']);

        $desde = trim((string) $this->input->get('desde'));
        $hasta = trim((string) $this->input->get('hasta'));

        if ($desde === '' || $hasta === '') {
            $this->response_library->error('Debe enviar desde y hasta', 400);
        }

        $this->db->select("c.id, c.numero_contrato, c.tipo_documento, c.estado, c.fecha_contrato, c.fecha_inicio_alquiler, c.fecha_fin_alquiler, c.total, c.created_at");
        $this->db->select("cl.razon_social as cliente_nombre, s.nombre as sucursal_nombre, CONCAT_WS(' ', u.nombres, u.apellidos) as creado_por_nombre", false);
        $this->db->from('contratos c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('sucursales s', 's.id = c.sucursal_venta_id');
        $this->db->join('usuarios u', 'u.id = c.created_by');
        $this->db->where('c.deleted_at IS NULL');
        $this->db->where('c.tipo_documento', 'contrato');
        $this->db->where('c.fecha_contrato >=', $desde);
        $this->db->where('c.fecha_contrato <=', $hasta);
        $this->db->order_by('c.fecha_contrato', 'DESC');
        $this->db->order_by('c.created_at', 'DESC');

        $rows = $this->db->get()->result();
        $this->response_library->success($rows);
    }

    public function clientes_nuevos() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'clientes.leer']);

        $desde = trim((string) $this->input->get('desde'));
        $hasta = trim((string) $this->input->get('hasta'));

        if ($desde === '' || $hasta === '') {
            $this->response_library->error('Debe enviar desde y hasta', 400);
        }

        $desde_dt = $desde . ' 00:00:00';
        $hasta_dt = $hasta . ' 23:59:59';

        $this->db->select('c.id, c.tipo_documento, c.numero_documento, c.razon_social, c.nombre_comercial, c.telefono, c.email, c.activo, c.created_at');
        $this->db->select("CONCAT_WS(' ', u.nombres, u.apellidos) as creado_por_nombre", false);
        $this->db->from('clientes c');
        $this->db->join('usuarios u', 'u.id = c.created_by', 'left');
        $this->db->where('c.deleted_at IS NULL');
        $this->db->where('c.created_at >=', $desde_dt);
        $this->db->where('c.created_at <=', $hasta_dt);
        $this->db->order_by('c.created_at', 'DESC');

        $rows = $this->db->get()->result();
        $this->response_library->success($rows);
    }

    public function movimiento_material() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'inventario.leer']);

        $desde = trim((string) $this->input->get('desde'));
        $hasta = trim((string) $this->input->get('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->response_library->error('Debe enviar desde y hasta', 400);
        }

        $desde_dt = $desde . ' 00:00:00';
        $hasta_dt = $hasta . ' 23:59:59';

        $movs = $this->Inventario_model->get_movimientos([
            'fecha_desde' => $desde_dt,
            'fecha_hasta' => $hasta_dt,
            'limit' => 5000
        ]);

        $this->db->select('p.id as producto_id, p.codigo, p.nombre as producto_nombre');
        $this->db->select("SUM(CASE WHEN im.tipo_movimiento LIKE 'entrada%' THEN im.cantidad ELSE 0 END) as total_entradas", false);
        $this->db->select("SUM(CASE WHEN im.tipo_movimiento LIKE 'salida%' THEN im.cantidad ELSE 0 END) as total_salidas", false);
        $this->db->select("SUM(CASE WHEN im.tipo_movimiento LIKE 'ajuste_%' THEN im.cantidad ELSE 0 END) as total_ajustes", false);
        $this->db->from('inventario_movimientos im');
        $this->db->join('productos p', 'p.id = im.producto_id');
        $this->db->where('im.fecha_movimiento >=', $desde_dt);
        $this->db->where('im.fecha_movimiento <=', $hasta_dt);
        $this->db->group_by('p.id');
        $this->db->order_by('p.nombre', 'ASC');
        $resumen = $this->db->get()->result();

        $this->response_library->success([
            'periodo' => ['desde' => $desde_dt, 'hasta' => $hasta_dt],
            'resumen' => $resumen,
            'movimientos' => $movs
        ]);
    }

    public function cuentas_pendientes() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'pagos.ver_historial', 'pagos.registrar']);

        $garantia_select = $this->has_garantia_monto ? 'IFNULL(c.garantia_monto, 0)' : '0';

        $this->db->select("c.id, c.numero_contrato, c.estado, c.fecha_contrato, c.fecha_inicio_alquiler, c.fecha_fin_alquiler, c.total, {$garantia_select} as garantia_monto, cl.razon_social as cliente_nombre", false);
        $this->db->select("IFNULL(pp.pagado, 0) as pagado, (c.total + {$garantia_select} - IFNULL(pp.pagado, 0)) as saldo", false);
        $this->db->select('DATEDIFF(c.fecha_fin_alquiler, CURDATE()) as dias_para_vencer', false);
        $this->db->from('contratos c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('(SELECT contrato_id, SUM((monto - IFNULL(descuento_aplicado,0))) as pagado FROM pagos GROUP BY contrato_id) pp', 'pp.contrato_id = c.id', 'left', false);
        $this->db->where('c.deleted_at IS NULL');
        $this->db->where('c.estado !=', 'cancelado');
        $this->db->having('saldo >', 0.01);
        $this->db->order_by('dias_para_vencer', 'ASC');
        $this->db->limit(2000);
        $rows = $this->db->get()->result();

        foreach ($rows as $r) {
            $d = (int) ($r->dias_para_vencer ?? 0);
            $semaforo = '';
            if ($d < 0) {
                $semaforo = 'rojo';
            } elseif ($d <= 5) {
                $semaforo = 'amarillo';
            }
            $r->semaforo = $semaforo;
        }

        $this->response_library->success($rows);
    }

    public function contratos_vencidos_plazo() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'contratos.leer']);

        $garantia_select = $this->has_garantia_monto ? 'IFNULL(c.garantia_monto, 0)' : '0';

        $this->db->select("c.id, c.numero_contrato, c.estado, c.fecha_contrato, c.fecha_inicio_alquiler, c.fecha_fin_alquiler, c.total, {$garantia_select} as garantia_monto, cl.razon_social as cliente_nombre", false);
        $this->db->select("IFNULL(pp.pagado, 0) as pagado, (c.total + {$garantia_select} - IFNULL(pp.pagado, 0)) as saldo", false);
        $this->db->select('DATEDIFF(CURDATE(), c.fecha_fin_alquiler) as dias_vencido', false);
        $this->db->from('contratos c');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('(SELECT contrato_id, SUM((monto - IFNULL(descuento_aplicado,0))) as pagado FROM pagos GROUP BY contrato_id) pp', 'pp.contrato_id = c.id', 'left', false);
        $this->db->where('c.deleted_at IS NULL');
        $this->db->where_in('c.estado', ['en_curso', 'parcialmente_devuelto', 'aprobado', 'listo_entrega', 'entregado']);
        $this->db->where('c.fecha_fin_alquiler < CURDATE()', null, false);
        $this->db->order_by('c.fecha_fin_alquiler', 'ASC');
        $this->db->limit(2000);
        $rows = $this->db->get()->result();

        $this->response_library->success($rows);
    }

    public function inventario_stock() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'inventario.leer']);

        $sucursal_id = (int) $this->get_sucursal_activa(true);
        $almacen_id = $this->input->get('almacen_id');

        $filters = ['sucursal_id' => $sucursal_id];
        if ($almacen_id !== null && $almacen_id !== '') {
            $filters['almacen_id'] = (int) $almacen_id;
        }

        $rows = $this->Inventario_model->get_all($filters);
        $this->response_library->success($rows);
    }

    public function dashboard() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'contratos.leer', 'pagos.ver_historial', 'pagos.registrar']);

        $contratos_activos = (int) $this->db->from('contratos')->where_in('estado', ['en_curso', 'parcialmente_devuelto'])->where('deleted_at IS NULL')->count_all_results();

        // Cantidad de contratos cuyo plazo vence en los próximos 5 días (incluye hoy)
        $contratos_por_vencer_row = $this->db->select('COUNT(DISTINCT c.id) as total', false)
            ->from('contratos c')
            ->where_in('c.estado', ['en_curso', 'parcialmente_devuelto'])
            ->where('c.deleted_at IS NULL')
            ->where('DATEDIFF(c.fecha_fin_alquiler, CURDATE()) BETWEEN 0 AND 5', null, false)
            ->get()
            ->row();
        $contratos_por_vencer = $contratos_por_vencer_row ? (int) $contratos_por_vencer_row->total : 0;

        $ingresos_mes_actual_row = $this->db->select('COALESCE(SUM(monto - IFNULL(descuento_aplicado,0)),0) as total', false)
            ->from('pagos')
            ->where('YEAR(fecha_pago) = YEAR(CURDATE())', null, false)
            ->where('MONTH(fecha_pago) = MONTH(CURDATE())', null, false)
            ->get()
            ->row();
        $ingresos_mes_actual = $ingresos_mes_actual_row ? (float) $ingresos_mes_actual_row->total : 0.0;

        $cxc_rows = $this->get_contratos_con_saldo([
            'only_with_debt' => true,
            'order_by' => 'c.fecha_contrato DESC',
            'limit' => 500
        ]);
        $cuentas_por_cobrar = 0.0;
        foreach ($cxc_rows as $r) {
            $cuentas_por_cobrar += (float) ($r->saldo ?? 0);
        }

        $productos_bajo_stock = (int) $this->db->from('inventario')->where('cantidad_disponible < stock_minimo', null, false)->count_all_results();

        $entregas_pendientes_validacion = (int) $this->db->from('entregas')->where('estado', 'registrado')->count_all_results();
        $devoluciones_pendientes_validacion = (int) $this->db->from('devoluciones')->where('estado', 'registrado')->count_all_results();

        $contratos_a_entregar = $this->get_contratos_con_saldo([
            'estados' => ['aprobado', 'listo_entrega', 'entregado'],
            'only_with_debt' => false,
            'order_by' => 'c.fecha_inicio_alquiler ASC',
            'limit' => 50
        ]);

        $contratos_en_proceso = $this->get_contratos_con_saldo([
            'estados' => ['en_curso', 'parcialmente_devuelto'],
            'only_with_debt' => false,
            'order_by' => 'c.fecha_fin_alquiler ASC',
            'limit' => 50
        ]);

        $cuentas_por_cobrar_list = $this->get_contratos_con_saldo([
            'only_with_debt' => true,
            'order_by' => 'saldo DESC',
            'limit' => 50
        ]);

        $data = [
            'contratos_activos' => $contratos_activos,
            'contratos_por_vencer' => $contratos_por_vencer,
            'ingresos_mes_actual' => $ingresos_mes_actual,
            'cuentas_por_cobrar' => (float) $cuentas_por_cobrar,
            'productos_bajo_stock' => $productos_bajo_stock,
            'entregas_pendientes_validacion' => $entregas_pendientes_validacion,
            'devoluciones_pendientes_validacion' => $devoluciones_pendientes_validacion,
            'contratos_a_entregar' => $contratos_a_entregar,
            'contratos_en_proceso' => $contratos_en_proceso,
            'cuentas_por_cobrar_list' => $cuentas_por_cobrar_list
        ];

        $this->response_library->success($data);
    }

    public function contratos_activos() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'contratos.leer']);

        $rows = $this->get_contratos_con_saldo([
            'estados' => ['en_curso', 'parcialmente_devuelto'],
            'order_by' => 'c.fecha_fin_alquiler ASC',
            'limit' => 200
        ]);

        $this->response_library->success($rows);
    }

    public function cuentas_por_cobrar() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'pagos.ver_historial', 'pagos.registrar']);

        $rows = $this->get_contratos_con_saldo([
            'only_with_debt' => true,
            'order_by' => 'saldo DESC',
            'limit' => 200
        ]);

        $this->response_library->success($rows);
    }

    public function inventario() {
        $this->authenticate();
        $this->require_any_permission(['reportes.ver', 'inventario.leer']);

        $rows = $this->Inventario_model->get_all(['bajo_stock' => 1]);
        $this->response_library->success($rows);
    }
}
