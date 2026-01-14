<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Caja_model extends CI_Model {

    private $table = 'caja_sesiones';

    public function get_by_id($id) {
        $this->db->select("cs.*, s.nombre as sucursal_nombre,
                          CONCAT_WS(' ', ua.nombres, ua.apellidos) as usuario_apertura_nombre,
                          CONCAT_WS(' ', uc.nombres, uc.apellidos) as usuario_cierre_nombre");
        $this->db->from($this->table . ' cs');
        $this->db->join('sucursales s', 's.id = cs.sucursal_id', 'left');
        $this->db->join('usuarios ua', 'ua.id = cs.usuario_apertura_id', 'left');
        $this->db->join('usuarios uc', 'uc.id = cs.usuario_cierre_id', 'left');
        $this->db->where('cs.id', (int) $id);
        return $this->db->get()->row();
    }

    public function get_sesion_abierta($sucursal_id) {
        $this->db->from($this->table);
        $this->db->where('sucursal_id', (int) $sucursal_id);
        $this->db->where('estado', 'abierta');
        $this->db->order_by('fecha_apertura', 'DESC');
        $this->db->limit(1);
        return $this->db->get()->row();
    }

    public function get_sesiones($sucursal_id, $filters = []) {
        $desde = isset($filters['desde']) ? $filters['desde'] : null;
        $hasta = isset($filters['hasta']) ? $filters['hasta'] : null;
        $cierre_desde = isset($filters['cierre_desde']) ? $filters['cierre_desde'] : null;
        $cierre_hasta = isset($filters['cierre_hasta']) ? $filters['cierre_hasta'] : null;
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;

        $filtro_cierre = ($cierre_desde || $cierre_hasta) ? true : false;

        $where_cierre = "";
        $params_cierre = [];
        if ($filtro_cierre) {
            $where_cierre .= " AND cs2.fecha_cierre IS NOT NULL";
            if ($cierre_desde) {
                $where_cierre .= " AND cs2.fecha_cierre >= ?";
                $params_cierre[] = $cierre_desde;
            }
            if ($cierre_hasta) {
                $where_cierre .= " AND cs2.fecha_cierre <= ?";
                $params_cierre[] = $cierre_hasta;
            }
        }

        $where_apertura = "";
        $params_apertura = [];
        if (!$filtro_cierre) {
            if ($desde) {
                $where_apertura .= " AND cs2.fecha_apertura >= ?";
                $params_apertura[] = $desde;
            }
            if ($hasta) {
                $where_apertura .= " AND cs2.fecha_apertura <= ?";
                $params_apertura[] = $hasta;
            }
        }

        $sql_totales = "
            SELECT
              cs2.id AS sesion_id,
              SUM(CASE WHEN op.tipo = 'EFECTIVO' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS efectivo_puro,
              SUM(CASE WHEN op.tipo = 'QR' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS qr_puro,
              SUM(CASE WHEN op.tipo = 'TARJETA' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS tarjeta_puro,
              SUM(CASE WHEN op.tipo = 'MIXTO' THEN
                    (CASE
                      WHEN IFNULL(p.monto,0) = 0 THEN 0
                      ELSE (IFNULL(p.monto_efectivo,0) * ((p.monto - IFNULL(p.descuento_aplicado,0)) / p.monto))
                    END)
                  ELSE 0 END) AS efectivo_mixto,
              SUM(CASE WHEN op.tipo = 'MIXTO' THEN
                    (CASE
                      WHEN IFNULL(p.monto,0) = 0 THEN 0
                      ELSE (IFNULL(p.monto_qr,0) * ((p.monto - IFNULL(p.descuento_aplicado,0)) / p.monto))
                    END)
                  ELSE 0 END) AS qr_mixto,
              SUM((p.monto - IFNULL(p.descuento_aplicado,0))) AS total_neto,
              SUM(IFNULL(p.descuento_aplicado,0)) AS total_descuento
            FROM caja_sesiones cs2
            LEFT JOIN pagos p
              ON p.fecha_pago >= cs2.fecha_apertura
             AND p.fecha_pago <= IFNULL(cs2.fecha_cierre, NOW())
            LEFT JOIN contratos c
              ON c.id = p.contrato_id
             AND c.sucursal_venta_id = cs2.sucursal_id
            LEFT JOIN opciones_pago op
              ON op.id = p.opcion_pago_id
            WHERE cs2.sucursal_id = ?
              {$where_cierre}
              {$where_apertura}
            GROUP BY cs2.id
        ";

        $this->db->select("cs.*, s.nombre as sucursal_nombre,
                          CONCAT_WS(' ', ua.nombres, ua.apellidos) as usuario_apertura_nombre,
                          CONCAT_WS(' ', uc.nombres, uc.apellidos) as usuario_cierre_nombre");
        $this->db->select("IFNULL(t.total_descuento, 0) as total_descuento", false);
        $this->db->select("IFNULL(t.total_neto, 0) as total_ingresos", false);
        $this->db->select("(IFNULL(t.qr_puro,0) + IFNULL(t.qr_mixto,0)) as monto_qr", false);
        $this->db->select("IFNULL(t.tarjeta_puro,0) as monto_tarjeta", false);
        $this->db->select("(IFNULL(t.efectivo_puro,0) + IFNULL(t.efectivo_mixto,0)) as ingresos_efectivo", false);
        $this->db->select("CASE
            WHEN cs.monto_cierre_efectivo IS NULL THEN NULL
            ELSE (cs.monto_cierre_efectivo - (IFNULL(cs.monto_apertura,0) + (IFNULL(t.efectivo_puro,0) + IFNULL(t.efectivo_mixto,0))))
          END as diferencia_efectivo", false);
        $this->db->from($this->table . ' cs');
        $this->db->join('sucursales s', 's.id = cs.sucursal_id', 'left');
        $this->db->join('usuarios ua', 'ua.id = cs.usuario_apertura_id', 'left');
        $this->db->join('usuarios uc', 'uc.id = cs.usuario_cierre_id', 'left');
        $params = array_merge([(int) $sucursal_id], $params_cierre, $params_apertura);
        $this->db->join("({$sql_totales}) t", 't.sesion_id = cs.id', 'left', false);
        $this->db->where('cs.sucursal_id', (int) $sucursal_id);

        if ($filtro_cierre) {
            $this->db->where('cs.fecha_cierre IS NOT NULL', null, false);
            if ($cierre_desde) {
                $this->db->where('cs.fecha_cierre >=', $cierre_desde);
            }
            if ($cierre_hasta) {
                $this->db->where('cs.fecha_cierre <=', $cierre_hasta);
            }
        } else {
            if ($desde) {
                $this->db->where('cs.fecha_apertura >=', $desde);
            }
            if ($hasta) {
                $this->db->where('cs.fecha_apertura <=', $hasta);
            }
        }

        $this->db->order_by('cs.fecha_apertura', 'DESC');
        if ($limit > 0) {
            $this->db->limit($limit);
        }

        return $this->db->query($this->db->get_compiled_select(), $params)->result();
    }

    public function abrir($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function cerrar($sesion_id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $sesion_id);
        $this->db->update($this->table, $data);
        return $this->db->affected_rows() > 0;
    }

    public function get_resumen($sesion_id) {
        $sesion = $this->get_by_id((int) $sesion_id);
        if (!$sesion) return null;

        $desde = $sesion->fecha_apertura;
        $hasta = $sesion->fecha_cierre ? $sesion->fecha_cierre : date('Y-m-d H:i:s');

        // Nota: pagos no tiene sucursal_id. Se toma por contrato.sucursal_venta_id.
        $sql = "
            SELECT
              SUM(CASE WHEN op.tipo = 'EFECTIVO' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS efectivo_puro,
              SUM(CASE WHEN op.tipo = 'QR' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS qr_puro,
              SUM(CASE WHEN op.tipo = 'TARJETA' THEN (p.monto - IFNULL(p.descuento_aplicado,0)) ELSE 0 END) AS tarjeta_puro,
              SUM(CASE WHEN op.tipo = 'MIXTO' THEN
                    (CASE
                      WHEN IFNULL(p.monto,0) = 0 THEN 0
                      ELSE (IFNULL(p.monto_efectivo,0) * ((p.monto - IFNULL(p.descuento_aplicado,0)) / p.monto))
                    END)
                  ELSE 0 END) AS efectivo_mixto,
              SUM(CASE WHEN op.tipo = 'MIXTO' THEN
                    (CASE
                      WHEN IFNULL(p.monto,0) = 0 THEN 0
                      ELSE (IFNULL(p.monto_qr,0) * ((p.monto - IFNULL(p.descuento_aplicado,0)) / p.monto))
                    END)
                  ELSE 0 END) AS qr_mixto,
              SUM((p.monto - IFNULL(p.descuento_aplicado,0))) AS total_neto,
              SUM(IFNULL(p.descuento_aplicado,0)) AS total_descuento,
              COUNT(*) AS cantidad_pagos
            FROM pagos p
            JOIN contratos c ON c.id = p.contrato_id
            LEFT JOIN opciones_pago op ON op.id = p.opcion_pago_id
            WHERE c.sucursal_venta_id = ?
              AND p.fecha_pago >= ?
              AND p.fecha_pago <= ?
        ";

        $row = $this->db->query($sql, [(int) $sesion->sucursal_id, $desde, $hasta])->row();

        $efectivo_puro = (float) ($row->efectivo_puro ?? 0);
        $qr_puro = (float) ($row->qr_puro ?? 0);
        $tarjeta_puro = (float) ($row->tarjeta_puro ?? 0);
        $efectivo_mixto = (float) ($row->efectivo_mixto ?? 0);
        $qr_mixto = (float) ($row->qr_mixto ?? 0);

        $ingresos_efectivo = $efectivo_puro + $efectivo_mixto;
        $ingresos_qr = $qr_puro + $qr_mixto;
        $ingresos_tarjeta = $tarjeta_puro;
        $ingresos_total = (float) ($row->total_neto ?? 0);

        $monto_apertura = (float) ($sesion->monto_apertura ?? 0);
        $efectivo_esperado = $monto_apertura + $ingresos_efectivo;

        $monto_cierre_efectivo = $sesion->monto_cierre_efectivo !== null ? (float) $sesion->monto_cierre_efectivo : null;
        $diferencia = $monto_cierre_efectivo !== null ? ($monto_cierre_efectivo - $efectivo_esperado) : null;

        return [
            'sesion' => $sesion,
            'periodo' => [
                'desde' => $desde,
                'hasta' => $hasta
            ],
            'totales' => [
                'cantidad_pagos' => (int) ($row->cantidad_pagos ?? 0),
                'total_descuento' => (float) ($row->total_descuento ?? 0),
                'total_ingresos' => $ingresos_total,
                'efectivo' => $ingresos_efectivo,
                'qr' => $ingresos_qr,
                'tarjeta' => $ingresos_tarjeta,
                'mixto_efectivo' => $efectivo_mixto,
                'mixto_qr' => $qr_mixto
            ],
            'cuadre' => [
                'monto_apertura' => $monto_apertura,
                'efectivo_esperado' => $efectivo_esperado,
                'monto_cierre_efectivo' => $monto_cierre_efectivo,
                'diferencia' => $diferencia,
                'resultado' => $diferencia === null ? null : ($diferencia < -0.005 ? 'faltante' : ($diferencia > 0.005 ? 'sobrante' : 'cuadrado'))
            ]
        ];
    }
}
