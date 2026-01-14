<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Devolucion_model extends CI_Model {
    
    private $table = 'devoluciones';
    
    public function get_all($filters = []) {
        $this->db->select('d.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as recibido_por_nombre,
                          u2.nombres as validado_por_nombre');
        $this->db->from($this->table . ' d');
        $this->db->join('contratos c', 'c.id = d.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = d.almacen_id');
        $this->db->join('usuarios u', 'u.id = d.recibido_por');
        $this->db->join('usuarios u2', 'u2.id = d.validado_por', 'left');
        
        if (isset($filters['estado'])) {
            $this->db->where('d.estado', $filters['estado']);
        }
        
        if (isset($filters['contrato_id'])) {
            $this->db->where('d.contrato_id', $filters['contrato_id']);
        }
        
        if (isset($filters['almacen_id'])) {
            $this->db->where('d.almacen_id', $filters['almacen_id']);
        }
        
        if (isset($filters['fecha_desde'])) {
            $this->db->where('d.fecha_devolucion >=', $filters['fecha_desde']);
        }
        
        if (isset($filters['fecha_hasta'])) {
            $this->db->where('d.fecha_devolucion <=', $filters['fecha_hasta']);
        }
        
        $this->db->order_by('d.fecha_devolucion', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function get_by_id($id) {
        $this->db->select('d.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as recibido_por_nombre,
                          u2.nombres as validado_por_nombre');
        $this->db->from($this->table . ' d');
        $this->db->join('contratos c', 'c.id = d.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = d.almacen_id');
        $this->db->join('usuarios u', 'u.id = d.recibido_por');
        $this->db->join('usuarios u2', 'u2.id = d.validado_por', 'left');
        $this->db->where('d.id', $id);
        
        return $this->db->get()->row();
    }
    
    public function get_detalles($devolucion_id) {
        $this->db->select('dd.*, p.codigo, p.nombre as producto_nombre, p.unidad_medida');
        $this->db->from('devoluciones_detalle dd');
        $this->db->join('productos p', 'p.id = dd.producto_id');
        $this->db->where('dd.devolucion_id', $devolucion_id);
        
        return $this->db->get()->result();
    }
    
    public function get_fotos($devolucion_id, $detalle_id = null) {
        $this->db->select('df.*, p.nombre as producto_nombre, u.nombres as usuario_nombre');
        $this->db->from('devoluciones_fotos df');
        $this->db->join('productos p', 'p.id = df.producto_id', 'left');
        $this->db->join('usuarios u', 'u.id = df.usuario_id', 'left');
        $this->db->where('df.devolucion_id', $devolucion_id);
        
        if ($detalle_id !== null) {
            $this->db->where('df.devolucion_detalle_id', $detalle_id);
        }
        
        return $this->db->get()->result();
    }
    
    public function get_cargos($devolucion_id) {
        $this->db->select('dc.*, p.nombre as producto_nombre');
        $this->db->from('devoluciones_cargos dc');
        $this->db->join('devoluciones_detalle dd', 'dd.id = dc.devolucion_detalle_id');
        $this->db->join('productos p', 'p.id = dd.producto_id');
        $this->db->where('dc.devolucion_id', $devolucion_id);
        
        return $this->db->get()->result();
    }
    
    public function get_productos_recepcionados($filters = []) {
        $this->db->select('
            dd.id as detalle_id,
            dd.devolucion_id,
            dd.producto_id,
            dd.cantidad_devuelta,
            dd.cantidad_danada,
            dd.cantidad_faltante,
            dd.estado_material,
            dd.observaciones,
            d.fecha_devolucion,
            d.estado as estado_devolucion,
            d.almacen_id,
            c.numero_contrato,
            cl.razon_social as cliente_nombre,
            p.nombre as producto_nombre,
            p.codigo as producto_codigo
        ');
        $this->db->from('devoluciones_detalle dd');
        $this->db->join('devoluciones d', 'd.id = dd.devolucion_id');
        $this->db->join('productos p', 'p.id = dd.producto_id');
        $this->db->join('contratos c', 'c.id = d.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        
        if (!isset($filters['estado'])) {
            $this->db->where('d.estado', 'validado');
        } else {
            $this->db->where('d.estado', $filters['estado']);
        }
        
        if (!empty($filters['fecha_desde'])) {
            $this->db->where('d.fecha_devolucion >=', $filters['fecha_desde']);
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $this->db->where('d.fecha_devolucion <=', $filters['fecha_hasta']);
        }
        
        if (!empty($filters['contrato_id'])) {
            $this->db->where('d.contrato_id', $filters['contrato_id']);
        }
        
        if (!empty($filters['cliente_id'])) {
            $this->db->where('c.cliente_id', $filters['cliente_id']);
        }
        
        if (!empty($filters['producto_id'])) {
            $this->db->where('dd.producto_id', $filters['producto_id']);
        }
        
        $this->db->order_by('d.fecha_devolucion', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function get_productos_contrato($contrato_id) {
        $sql = "
            SELECT 
                cp.id as contrato_producto_id,
                cp.producto_id,
                cp.cantidad_contratada as cantidad_alquilada,
                p.codigo,
                p.nombre as producto_nombre,
                p.unidad_medida,
                p.precio_reposicion,
                COALESCE(SUM(dd.cantidad_devuelta), 0) as cantidad_devuelta,
                (cp.cantidad_contratada - COALESCE(SUM(dd.cantidad_devuelta), 0)) as cantidad_pendiente
            FROM contrato_productos cp
            INNER JOIN productos p ON p.id = cp.producto_id
            LEFT JOIN devoluciones d ON d.contrato_id = ?
            LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id AND dd.producto_id = cp.producto_id
            WHERE cp.contrato_id = ?
            GROUP BY cp.id, cp.producto_id, cp.cantidad_contratada, p.codigo, p.nombre, p.unidad_medida, p.precio_reposicion
            HAVING cantidad_pendiente > 0
        ";
        
        return $this->db->query($sql, [$contrato_id, $contrato_id])->result();
    }

    public function calcular_total_cargos_pendientes($devolucion_id) {
        $row = $this->db->select('COALESCE(SUM(monto), 0) as total')
            ->from('devoluciones_cargos')
            ->where('devolucion_id', (int) $devolucion_id)
            ->where('estado', 'pendiente')
            ->get()
            ->row();

        return $row ? (float) $row->total : 0.0;
    }

    public function marcar_cargos_pagados($devolucion_id) {
        $now = date('Y-m-d H:i:s');
        $this->db->where('devolucion_id', (int) $devolucion_id);
        $this->db->where('estado', 'pendiente');
        $this->db->set('estado', 'pagado');
        $this->db->set('updated_at', $now);
        $this->db->update('devoluciones_cargos');
        return $this->db->affected_rows() >= 0;
    }
    
    public function get_pendientes() {
        $this->db->select('d.*, c.numero_contrato, cl.razon_social as cliente_nombre,
                          a.nombre as almacen_nombre, u.nombres as recibido_por_nombre');
        $this->db->from($this->table . ' d');
        $this->db->join('contratos c', 'c.id = d.contrato_id');
        $this->db->join('clientes cl', 'cl.id = c.cliente_id');
        $this->db->join('almacenes a', 'a.id = d.almacen_id');
        $this->db->join('usuarios u', 'u.id = d.recibido_por');
        $this->db->where('d.estado', 'registrado');
        $this->db->order_by('d.fecha_devolucion', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function insert($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
    
    public function insert_detalle($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert('devoluciones_detalle', $data);
        return $this->db->insert_id();
    }

    private function normalize_estado_material($estado) {
        $e = strtolower(trim((string) $estado));
        if ($e === 'bueno' || $e === 'regular') return 'bueno';
        if ($e === 'danado' || $e === 'dañado' || $e === 'malo') return 'danado';
        if ($e === 'faltante' || $e === 'perdido' || $e === 'perdida') return 'faltante';
        if ($e === 'mixto') return 'mixto';
        return 'bueno';
    }

    /**
     * Inserta detalles de devolución.
     * Compatibilidad:
     * - ContratoWizard envía: producto_id, cantidad_devuelta, estado_material, requiere_reparacion
     * - ContratoDetalle envía también: contrato_producto_id, cantidad_alquilada, cantidad_danada, cantidad_faltante, observaciones, cargo_generado
     */
    public function insert_detalles($devolucion_id, $productos) {
        if (!is_array($productos) || empty($productos)) return false;

        $this->db->trans_start();

        $dev = $this->db->get_where($this->table, ['id' => (int) $devolucion_id])->row();
        $contrato_id = $dev ? (int) $dev->contrato_id : 0;
        if ($contrato_id <= 0) {
            $this->db->trans_rollback();
            return false;
        }

        $cp_rows = $this->db->get_where('contrato_productos', ['contrato_id' => $contrato_id])->result();
        $cp_map = [];
        foreach ($cp_rows as $cp) {
            $cp_map[(int) $cp->producto_id] = $cp;
        }

        $producto_ids = [];
        foreach ($productos as $p) {
            $pid = (int) ($p['producto_id'] ?? 0);
            if ($pid > 0) $producto_ids[] = $pid;
        }
        $producto_ids = array_values(array_unique($producto_ids));

        $precio_reposicion_map = [];
        if (!empty($producto_ids)) {
            $rows_prod = $this->db->select('id, precio_reposicion')
                ->from('productos')
                ->where_in('id', $producto_ids)
                ->get()
                ->result();
            foreach ($rows_prod as $r) {
                $precio_reposicion_map[(int) $r->id] = (float) ($r->precio_reposicion ?? 0);
            }
        }

        foreach ($productos as $p) {
            $producto_id = (int) ($p['producto_id'] ?? 0);
            if ($producto_id <= 0) continue;

            $cantidad_devuelta = (float) ($p['cantidad_devuelta'] ?? 0);
            if ($cantidad_devuelta <= 0) continue;

            $contrato_producto_id = (int) ($p['contrato_producto_id'] ?? 0);
            $cantidad_alquilada = isset($p['cantidad_alquilada']) ? (float) $p['cantidad_alquilada'] : null;

            if ($contrato_producto_id <= 0 || $cantidad_alquilada === null) {
                $cp = $cp_map[$producto_id] ?? null;
                if ($cp) {
                    if ($contrato_producto_id <= 0) $contrato_producto_id = (int) $cp->id;
                    if ($cantidad_alquilada === null) {
                        $cantidad_alquilada = (float) (
                            isset($cp->cantidad_contratada)
                                ? $cp->cantidad_contratada
                                : (isset($cp->cantidad) ? $cp->cantidad : 0)
                        );
                    }
                }
            }

            if ($contrato_producto_id <= 0) {
                $this->db->trans_rollback();
                return false;
            }

            $estado_material = $this->normalize_estado_material($p['estado_material'] ?? 'bueno');

            $cantidad_danada = (float) ($p['cantidad_danada'] ?? 0);
            $cantidad_faltante = (float) ($p['cantidad_faltante'] ?? 0);
            if ($cantidad_danada < 0) $cantidad_danada = 0;
            if ($cantidad_faltante < 0) $cantidad_faltante = 0;

            $precio_reposicion = isset($precio_reposicion_map[$producto_id]) ? (float) $precio_reposicion_map[$producto_id] : 0.0;
            if ($precio_reposicion < 0) $precio_reposicion = 0;

            $cargo_generado = 0.0;
            if ($precio_reposicion > 0) {
                $cargo_generado = round(($cantidad_danada + $cantidad_faltante) * $precio_reposicion, 2);
            }

            $detalle = [
                'devolucion_id' => (int) $devolucion_id,
                'contrato_producto_id' => (int) $contrato_producto_id,
                'producto_id' => (int) $producto_id,
                'cantidad_alquilada' => (float) ($cantidad_alquilada ?? 0),
                'cantidad_devuelta' => (float) $cantidad_devuelta,
                'cantidad_danada' => (float) $cantidad_danada,
                'cantidad_faltante' => (float) $cantidad_faltante,
                'estado_material' => $estado_material,
                'observaciones' => isset($p['observaciones']) ? $p['observaciones'] : null,
                'cargo_generado' => (float) $cargo_generado
            ];

            $id = $this->insert_detalle($detalle);
            if (!$id) {
                $this->db->trans_rollback();
                return false;
            }

            if ($cargo_generado > 0 && ($cantidad_danada > 0 || $cantidad_faltante > 0)) {
                if ($cantidad_danada > 0) {
                    $monto_dano = round($cantidad_danada * $precio_reposicion, 2);
                    if ($monto_dano > 0) {
                        $this->insert_cargo([
                            'devolucion_id' => (int) $devolucion_id,
                            'devolucion_detalle_id' => (int) $id,
                            'contrato_id' => (int) $contrato_id,
                            'tipo_cargo' => 'dano',
                            'descripcion' => 'Cargo por material dañado',
                            'monto' => (float) $monto_dano,
                            'estado' => 'pendiente'
                        ]);
                    }
                }

                if ($cantidad_faltante > 0) {
                    $monto_faltante = round($cantidad_faltante * $precio_reposicion, 2);
                    if ($monto_faltante > 0) {
                        $this->insert_cargo([
                            'devolucion_id' => (int) $devolucion_id,
                            'devolucion_detalle_id' => (int) $id,
                            'contrato_id' => (int) $contrato_id,
                            'tipo_cargo' => 'faltante',
                            'descripcion' => 'Cargo por material faltante',
                            'monto' => (float) $monto_faltante,
                            'estado' => 'pendiente'
                        ]);
                    }
                }
            }
        }

        $total_cargos = $this->calcular_total_cargos($devolucion_id);
        $this->update($devolucion_id, ['total_cargos' => (float) $total_cargos]);

        $this->db->trans_complete();
        return $this->db->trans_status() !== FALSE;
    }
    
    public function insert_foto($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert('devoluciones_fotos', $data);
        return $this->db->insert_id();
    }
    
    public function insert_cargo($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->insert('devoluciones_cargos', $data);
        return $this->db->insert_id();
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }
    
    public function actualizar_fotos_completas($devolucion_id, $completas) {
        return $this->db->where('id', $devolucion_id)
                        ->update($this->table, ['fotos_completas' => $completas ? 1 : 0]);
    }
    
    public function calcular_total_cargos($devolucion_id) {
        $result = $this->db->select('COALESCE(SUM(monto), 0) as total')
                           ->from('devoluciones_cargos')
                           ->where('devolucion_id', $devolucion_id)
                           ->get()
                           ->row();
        
        return $result ? $result->total : 0;
    }
    
    public function validar($devolucion_id, $usuario_id, $observaciones) {
        $this->db->trans_start();

        $dev = $this->db->get_where($this->table, ['id' => (int) $devolucion_id])->row();
        if (!$dev) {
            $this->db->trans_rollback();
            return false;
        }

        if ((string) ($dev->estado ?? '') !== 'registrado') {
            $this->db->trans_rollback();
            return false;
        }

        $contrato_id = (int) ($dev->contrato_id ?? 0);
        $almacen_id = (int) ($dev->almacen_id ?? 0);
        if ($contrato_id <= 0 || $almacen_id <= 0) {
            $this->db->trans_rollback();
            return false;
        }

        $detalles = $this->db->get_where('devoluciones_detalle', ['devolucion_id' => (int) $devolucion_id])->result();
        if (empty($detalles)) {
            $this->db->trans_rollback();
            return false;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($detalles as $dd) {
            $producto_id = (int) ($dd->producto_id ?? 0);
            if ($producto_id <= 0) continue;

            $cant_devuelta = (float) ($dd->cantidad_devuelta ?? 0);
            $cant_danada = (float) ($dd->cantidad_danada ?? 0);
            $cant_faltante = (float) ($dd->cantidad_faltante ?? 0);
            if ($cant_devuelta <= 0) continue;

            if ($cant_danada < 0) $cant_danada = 0;
            if ($cant_faltante < 0) $cant_faltante = 0;
            if (($cant_danada + $cant_faltante) > $cant_devuelta) {
                $cant_danada = max(0, min($cant_danada, $cant_devuelta));
                $cant_faltante = max(0, min($cant_faltante, $cant_devuelta - $cant_danada));
            }

            $cant_buena = max(0, $cant_devuelta - $cant_danada - $cant_faltante);
            $cant_no_alquilable = max(0, $cant_danada + $cant_faltante);

            // Inventario: todo lo devuelto sale de alquilado; solo lo bueno vuelve a disponible.
            // Lo no alquilable se contabiliza como pérdida para evitar re-alquiler.
            $inv = $this->db->get_where('inventario', ['producto_id' => $producto_id, 'almacen_id' => $almacen_id])->row();
            if (!$inv) {
                $this->db->insert('inventario', [
                    'producto_id' => $producto_id,
                    'almacen_id' => $almacen_id,
                    'cantidad_total' => 0,
                    'cantidad_disponible' => 0,
                    'cantidad_reservada' => 0,
                    'cantidad_alquilada' => 0,
                    'cantidad_en_reparacion' => 0,
                    'cantidad_perdida' => 0,
                    'stock_minimo' => 0,
                    'stock_maximo' => 0
                ]);
                $inv = $this->db->get_where('inventario', ['producto_id' => $producto_id, 'almacen_id' => $almacen_id])->row();
            }

            $alquilada_actual = (float) ($inv->cantidad_alquilada ?? 0);
            $disponible_actual = (float) ($inv->cantidad_disponible ?? 0);
            $perdida_actual = (float) ($inv->cantidad_perdida ?? 0);

            $nuevo_alquilada = max(0, $alquilada_actual - $cant_devuelta);
            $nuevo_disponible = $disponible_actual + $cant_buena;
            $nuevo_perdida = $perdida_actual + $cant_no_alquilable;

            $this->db->where('id', (int) $inv->id);
            $this->db->update('inventario', [
                'cantidad_alquilada' => $nuevo_alquilada,
                'cantidad_disponible' => $nuevo_disponible,
                'cantidad_perdida' => $nuevo_perdida
            ]);

            // Movimiento de inventario (entrada) por lo bueno (lo no alquilable se registra aparte)
            if ($cant_buena > 0) {
                $this->db->insert('inventario_movimientos', [
                    'producto_id' => $producto_id,
                    'almacen_id' => $almacen_id,
                    'tipo_movimiento' => 'entrada',
                    'cantidad' => $cant_buena,
                    'cantidad_anterior' => $disponible_actual,
                    'cantidad_nueva' => $nuevo_disponible,
                    'motivo' => 'Devolución autorizada',
                    'contrato_id' => $contrato_id,
                    'usuario_id' => $usuario_id,
                    'fecha_movimiento' => $now,
                    'observaciones' => 'Ingreso de material en buen estado'
                ]);
            }

            // Registro separado de no alquilables
            if ($cant_no_alquilable > 0) {
                $precio_reposicion = 0.0;
                $row_prod = $this->db->select('precio_reposicion')->from('productos')->where('id', $producto_id)->get()->row();
                if ($row_prod) {
                    $precio_reposicion = (float) ($row_prod->precio_reposicion ?? 0);
                }

                $this->db->insert('perdidas', [
                    'contrato_id' => $contrato_id,
                    'producto_id' => $producto_id,
                    'cantidad_perdida' => $cant_no_alquilable,
                    'valor_unitario' => $precio_reposicion,
                    'valor_total' => round($cant_no_alquilable * $precio_reposicion, 2),
                    'motivo' => $cant_danada > 0 && $cant_faltante > 0 ? 'dano_y_faltante' : ($cant_danada > 0 ? 'dano' : 'faltante'),
                    'fecha_reporte' => $now,
                    'reportado_por' => $usuario_id,
                    'aprobado_por' => null,
                    'estado' => 'reportado',
                    'observaciones' => 'Registrado al autorizar devolución (no alquilable)'
                ]);
            }

            // Contrato productos: aumenta devuelto por todo lo recepcionado; aumenta perdida por lo no alquilable.
            $cp = $this->db->get_where('contrato_productos', ['id' => (int) ($dd->contrato_producto_id ?? 0)])->row();
            if ($cp && (int) ($cp->contrato_id ?? 0) === $contrato_id) {
                $cp_devuelta = (float) ($cp->cantidad_devuelta ?? 0);
                $cp_perdida = (float) ($cp->cantidad_perdida ?? 0);
                $nuevo_cp_devuelta = $cp_devuelta + $cant_devuelta;
                $nuevo_cp_perdida = $cp_perdida + $cant_no_alquilable;
                $entregada = (float) ($cp->cantidad_entregada ?? 0);

                $nuevo_estado = (string) ($cp->estado ?? 'pendiente');
                if ($nuevo_cp_devuelta >= $entregada && $entregada > 0) {
                    $nuevo_estado = $nuevo_cp_perdida > 0.0001 ? 'con_perdida' : 'devuelto_total';
                } elseif ($nuevo_cp_devuelta > 0) {
                    $nuevo_estado = $nuevo_cp_perdida > 0.0001 ? 'con_perdida' : 'devuelto_parcial';
                }

                $this->db->where('id', (int) $cp->id);
                $this->db->update('contrato_productos', [
                    'cantidad_devuelta' => $nuevo_cp_devuelta,
                    'cantidad_perdida' => $nuevo_cp_perdida,
                    'estado' => $nuevo_estado
                ]);
            }
        }

        $tipo = (string) ($dev->tipo_devolucion ?? 'parcial');
        if ($tipo !== 'total') {
            $tipo = 'parcial';
        }

        // Contrato: al recepcionar devolución, cambia a "almacenado" (material recepcionado en almacén)
        // Si es total, finaliza; si no, pasa a almacenado.
        if ($tipo === 'total') {
            $this->db->where('id', $contrato_id);
            $this->db->update('contratos', ['estado' => 'finalizado', 'updated_at' => $now]);
        } else {
            $this->db->where('id', $contrato_id);
            $this->db->update('contratos', ['estado' => 'almacenado', 'updated_at' => $now]);
        }

        $data = [
            'estado' => 'validado',
            'validado_por' => $usuario_id,
            'fecha_validacion' => $now,
            'observaciones_validacion' => $observaciones
        ];
        $this->update($devolucion_id, $data);

        $this->db->trans_complete();

        return $this->db->trans_status() !== FALSE;
    }
    
    public function registrar_devolucion_completa($data_devolucion, $detalles, $fotos_por_detalle) {
        $this->db->trans_start();
        
        $devolucion_id = $this->insert($data_devolucion);
        
        if (!$devolucion_id) {
            $this->db->trans_rollback();
            return ['success' => false, 'mensaje' => 'Error al crear la devolución'];
        }
        
        foreach ($detalles as $detalle) {
            $detalle['devolucion_id'] = $devolucion_id;
            $detalle_id = $this->insert_detalle($detalle);
            
            if (!$detalle_id) {
                $this->db->trans_rollback();
                return ['success' => false, 'mensaje' => 'Error al insertar detalle'];
            }
            
            if (isset($fotos_por_detalle[$detalle['producto_id']])) {
                foreach ($fotos_por_detalle[$detalle['producto_id']] as $foto) {
                    $foto_data = [
                        'devolucion_id' => $devolucion_id,
                        'devolucion_detalle_id' => $detalle_id,
                        'producto_id' => $detalle['producto_id'],
                        'foto_url' => $foto['url'],
                        'tipo_foto' => $foto['tipo'],
                        'descripcion' => isset($foto['descripcion']) ? $foto['descripcion'] : null,
                        'usuario_id' => $data_devolucion['recibido_por']
                    ];
                    
                    $this->insert_foto($foto_data);
                }
            }
            
            if ($detalle['estado_material'] !== 'bueno' && $detalle['cargo_generado'] > 0) {
                $cargo_data = [
                    'devolucion_id' => $devolucion_id,
                    'devolucion_detalle_id' => $detalle_id,
                    'contrato_id' => $data_devolucion['contrato_id'],
                    'tipo_cargo' => $detalle['cantidad_faltante'] > 0 ? 'faltante' : 'dano',
                    'descripcion' => $detalle['observaciones'] ?: 'Cargo por ' . $detalle['estado_material'],
                    'monto' => $detalle['cargo_generado'],
                    'estado' => 'pendiente'
                ];
                
                $this->insert_cargo($cargo_data);
            }
        }
        
        $total_cargos = $this->calcular_total_cargos($devolucion_id);
        $this->update($devolucion_id, ['total_cargos' => $total_cargos]);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return ['success' => false, 'mensaje' => 'Error en la transacción'];
        }
        
        return ['success' => true, 'devolucion_id' => $devolucion_id];
    }
}
