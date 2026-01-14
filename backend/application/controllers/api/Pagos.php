<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pagos extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Pago_model');
        $this->load->model('Contrato_model');
        $this->load->model('OpcionPago_model');
        $this->load->model('Caja_model');
        $this->load->model('Devolucion_model');
    }

    public function index() {
        $this->authenticate();
        $this->check_permission('pagos.ver_historial');

        $filters = [
            'contrato_id' => $this->input->get('contrato_id'),
            'fecha_desde' => $this->input->get('fecha_desde'),
            'fecha_hasta' => $this->input->get('fecha_hasta')
        ];

        $filters = array_filter($filters, function($v) {
            return $v !== null && $v !== '';
        });

        $pagos = $this->Pago_model->get_all($filters);
        $this->response_library->success($pagos);
    }

    public function por_contrato($contrato_id) {
        $this->authenticate();
        $this->check_permission('pagos.ver_historial');

        $contrato = $this->Contrato_model->get_by_id((int) $contrato_id);
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }

        $pagos = $this->Pago_model->get_by_contrato((int) $contrato_id);
        
        $total_pagado = 0;
        foreach ($pagos as $p) {
            $monto = (float) $p->monto;
            $descuento = (float) ($p->descuento_aplicado ?? 0);
            $total_pagado += ($monto - $descuento);
        }

        $total_contrato = (float) $contrato->total;
        $saldo = $total_contrato - $total_pagado;

        $data = [
            'contrato' => $contrato,
            'pagos' => $pagos,
            'resumen' => [
                'total_contrato' => $total_contrato,
                'total_pagado' => $total_pagado,
                'saldo_pendiente' => $saldo,
                'estado_pago' => $saldo <= 0 ? 'pagado' : ($total_pagado > 0 ? 'pagado_parcial' : 'pendiente')
            ]
        ];

        $this->response_library->success($data);
    }

    public function registrar() {
        $this->authenticate();
        $this->check_permission('pagos.registrar');

        $sucursal_id = (int) $this->get_sucursal_activa(true);
        $sesion_abierta = $this->Caja_model->get_sesion_abierta($sucursal_id);
        if (!$sesion_abierta) {
            $this->response_library->error('No puede registrar pagos: no existe un turno/caja abierto en la sucursal activa', 409);
        }

        if ($this->input->method() !== 'post') {
            $this->response_library->error('Método no permitido', 405);
        }

        $data = $this->get_json_input();

        $this->validate_required([
            'contrato_id' => 'Contrato',
            'monto' => 'Monto',
            'opcion_pago_id' => 'Método de pago',
            'referencia' => 'Referencia',
            'fecha_pago' => 'Fecha de pago'
        ], $data);

        $contrato = $this->Contrato_model->get_by_id((int) $data['contrato_id']);
        if (!$contrato) {
            $this->response_library->not_found('Contrato no encontrado');
        }

        if ($contrato->tipo_documento === 'proforma') {
            $this->response_library->error('No se puede registrar pagos en una proforma. Conviértala a contrato primero.');
        }

        $opcion_pago = $this->OpcionPago_model->get_by_id((int) $data['opcion_pago_id']);
        if (!$opcion_pago) {
            $this->response_library->error('Opción de pago no encontrada');
        }

        if ((int) $opcion_pago->activo !== 1) {
            $this->response_library->error('La opción de pago seleccionada no está activa');
        }

        $monto = (float) $data['monto'];
        if ($monto <= 0) {
            $this->response_library->error('El monto debe ser mayor a cero');
        }

        $descuento = isset($data['descuento_aplicado']) ? (float) $data['descuento_aplicado'] : 0;
        if ($descuento < 0) {
            $this->response_library->error('El descuento no puede ser negativo');
        }

        if ($descuento > ($monto + 0.01)) {
            $this->response_library->error('El descuento no puede exceder el monto');
        }

        $pagos_previos = $this->Pago_model->get_by_contrato((int) $data['contrato_id']);
        $total_pagado = 0;
        foreach ($pagos_previos as $p) {
            $total_pagado += ((float) $p->monto - (float) ($p->descuento_aplicado ?? 0));
        }

        $total_contrato = (float) $contrato->total;
        $saldo = $total_contrato - $total_pagado;

        $devolucion_id = isset($data['devolucion_id']) ? (int) $data['devolucion_id'] : 0;
        $is_cobro_devolucion = $devolucion_id > 0;

        if ($is_cobro_devolucion) {
            $devolucion = $this->Devolucion_model->get_by_id($devolucion_id);
            if (!$devolucion) {
                $this->response_library->not_found('Devolución no encontrada');
            }

            if ((int) $devolucion->contrato_id !== (int) $data['contrato_id']) {
                $this->response_library->error('La devolución no corresponde al contrato enviado');
            }

            $total_pendiente = (float) $this->Devolucion_model->calcular_total_cargos_pendientes($devolucion_id);
            if ($total_pendiente <= 0.01) {
                $this->response_library->error('La devolución no tiene cargos pendientes de cobro');
            }

            // Para permitir ajustar el valor cobrado antes de generar el recibo, usamos descuento_aplicado.
            // Reglas:
            // - monto (base) debe ser igual al total pendiente
            // - neto (monto - descuento) debe ser > 0
            if (abs($monto - $total_pendiente) > 0.01) {
                $this->response_library->error('El monto base debe ser igual al total de cargos pendientes de la devolución');
            }

            $neto = (float) ($monto - $descuento);
            if ($neto <= 0.01) {
                $this->response_library->error('El monto neto a cobrar debe ser mayor a cero');
            }
        } else {
            if (($monto - $descuento) > ($saldo + 0.01)) {
                $this->response_library->error('El monto a pagar excede el saldo pendiente del contrato');
            }
        }

        $pago_data = [
            'contrato_id' => (int) $data['contrato_id'],
            'tipo_pago' => $is_cobro_devolucion ? 'otro' : (isset($data['tipo_pago']) ? $data['tipo_pago'] : 'alquiler'),
            'monto' => $monto,
            'opcion_pago_id' => (int) $data['opcion_pago_id'],
            'referencia' => trim((string) $data['referencia']),
            'fecha_pago' => $data['fecha_pago'],
            'registrado_por' => $this->user_data['user_id'],
            'observaciones' => $is_cobro_devolucion
                ? trim('Cobro por devolución #' . $devolucion_id . ' | ' . (isset($data['observaciones']) ? trim((string) $data['observaciones']) : ''))
                : (isset($data['observaciones']) ? trim((string) $data['observaciones']) : null),
            'descuento_aplicado' => $descuento
        ];

        if (strtoupper($opcion_pago->tipo) === 'MIXTO') {
            $monto_efectivo = isset($data['monto_efectivo']) ? (float) $data['monto_efectivo'] : 0;
            $monto_qr = isset($data['monto_qr']) ? (float) $data['monto_qr'] : 0;

            if ($monto_efectivo < 0 || $monto_qr < 0) {
                $this->response_library->error('Los montos de efectivo y QR no pueden ser negativos');
            }

            $neto = (float) ($monto - $descuento);
            if (abs(($monto_efectivo + $monto_qr) - $neto) > 0.01) {
                $this->response_library->error('La suma de efectivo y QR debe ser igual al monto neto');
            }

            $pago_data['monto_efectivo'] = $monto_efectivo;
            $pago_data['monto_qr'] = $monto_qr;
        }

        $pago_id = $this->Pago_model->insert($pago_data);
        if (!$pago_id) {
            $this->response_library->error('Error al registrar pago');
        }

        if ($is_cobro_devolucion) {
            $this->Devolucion_model->marcar_cargos_pagados($devolucion_id);
        }

        $this->log_audit('pagos', (int) $pago_id, 'INSERT', null, $pago_data, 'Pago registrado');

        $pago = $this->Pago_model->get_by_id((int) $pago_id);
        $this->response_library->success($pago, 'Pago registrado exitosamente', 201);
    }
}
