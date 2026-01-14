<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['api/auth/login'] = 'api/Auth/login';
$route['api/auth/refresh'] = 'api/Auth/refresh';
$route['api/auth/logout'] = 'api/Auth/logout';
$route['api/auth/me'] = 'api/Auth/me';
$route['api/auth/set-sucursal'] = 'api/Auth/set_sucursal_activa';
$route['api/auth/cambiar-sucursal'] = 'api/Auth/cambiar_sucursal';

$route['api/usuarios'] = 'api/Usuarios/index';
$route['api/usuarios/(:num)'] = 'api/Usuarios/show/$1';
$route['api/usuarios/create'] = 'api/Usuarios/create';
$route['api/usuarios/update/(:num)'] = 'api/Usuarios/update/$1';
$route['api/usuarios/delete/(:num)'] = 'api/Usuarios/delete/$1';
$route['api/usuarios/(:num)/sucursales'] = 'api/Usuarios/get_sucursales/$1';
$route['api/usuarios/(:num)/sucursales/asignar'] = 'api/Usuarios/asignar_sucursal/$1';
$route['api/usuarios/(:num)/sucursales/remover'] = 'api/Usuarios/remover_sucursal/$1';

$route['api/personal'] = 'api/Personal/index';
$route['api/personal/(:num)'] = 'api/Personal/show/$1';
$route['api/personal/create'] = 'api/Personal/create';
$route['api/personal/update/(:num)'] = 'api/Personal/update/$1';
$route['api/personal/delete/(:num)'] = 'api/Personal/delete/$1';

$route['api/roles'] = 'api/Roles/index';
$route['api/sucursales'] = 'api/Sucursales/index';
$route['api/sucursales/(:num)'] = 'api/Sucursales/show/$1';
$route['api/sucursales/create'] = 'api/Sucursales/create';
$route['api/sucursales/update/(:num)'] = 'api/Sucursales/update/$1';
$route['api/sucursales/delete/(:num)'] = 'api/Sucursales/delete/$1';

 $route['api/almacenes'] = 'api/Almacenes/index';

$route['api/clientes'] = 'api/Clientes/index';
$route['api/clientes/(:num)'] = 'api/Clientes/show/$1';
$route['api/clientes/create'] = 'api/Clientes/create';
$route['api/clientes/update/(:num)'] = 'api/Clientes/update/$1';
$route['api/clientes/delete/(:num)'] = 'api/Clientes/delete/$1';

$route['api/cliente-calificaciones'] = 'api/ClienteCalificaciones/index';

$route['api/productos'] = 'api/Productos/index';
$route['api/productos/(:num)'] = 'api/Productos/show/$1';
$route['api/productos/componentes/(:num)'] = 'api/Productos/componentes/$1';
$route['api/productos/create'] = 'api/Productos/create';
$route['api/productos/update/(:num)'] = 'api/Productos/update/$1';
$route['api/productos/delete/(:num)'] = 'api/Productos/delete/$1';

 $route['api/transportes'] = 'api/Transportes/index';
 $route['api/transportes/(:num)'] = 'api/Transportes/show/$1';
 $route['api/transportes/create'] = 'api/Transportes/create';
 $route['api/transportes/update/(:num)'] = 'api/Transportes/update/$1';
 $route['api/transportes/delete/(:num)'] = 'api/Transportes/delete/$1';

 $route['api/opciones-pago'] = 'api/OpcionesPago/index';
 $route['api/opciones-pago/(:num)'] = 'api/OpcionesPago/show/$1';
 $route['api/opciones-pago/create'] = 'api/OpcionesPago/create';
 $route['api/opciones-pago/update/(:num)'] = 'api/OpcionesPago/update/$1';
 $route['api/opciones-pago/delete/(:num)'] = 'api/OpcionesPago/delete/$1';

$route['api/inventario'] = 'api/Inventario/index';
$route['api/inventario/producto/(:num)/almacen/(:num)'] = 'api/Inventario/show/$1/$2';
$route['api/inventario/movimientos'] = 'api/Inventario/movimientos';
$route['api/inventario/kardex/(:num)'] = 'api/Inventario/kardex/$1';
$route['api/inventario/ajustar'] = 'api/Inventario/ajustar';

$route['api/inventario-notas-ingreso'] = 'api/InventarioNotasIngreso/index';
$route['api/inventario-notas-ingreso/(:num)'] = 'api/InventarioNotasIngreso/show/$1';
$route['api/inventario-notas-ingreso/create'] = 'api/InventarioNotasIngreso/create';
$route['api/inventario-notas-ingreso/update/(:num)'] = 'api/InventarioNotasIngreso/update/$1';
$route['api/inventario-notas-ingreso/registrar/(:num)'] = 'api/InventarioNotasIngreso/registrar/$1';
$route['api/inventario-notas-ingreso/anular/(:num)'] = 'api/InventarioNotasIngreso/anular/$1';

$route['api/contratos'] = 'api/Contratos/index';
$route['api/contratos/(:num)'] = 'api/Contratos/show/$1';
$route['api/contratos/create'] = 'api/Contratos/create';
$route['api/contratos/update/(:num)'] = 'api/Contratos/update/$1';
$route['api/contratos/aprobar/(:num)'] = 'api/Contratos/aprobar/$1';
$route['api/contratos/autorizar-entrega/(:num)'] = 'api/Contratos/autorizar_entrega/$1';
$route['api/contratos/cancelar/(:num)'] = 'api/Contratos/cancelar/$1';
$route['api/contratos/convertir-proforma/(:num)'] = 'api/Contratos/convertir_proforma/$1';

$route['api/entregas'] = 'api/Entregas/index';
$route['api/entregas/(:num)'] = 'api/Entregas/show/$1';
$route['api/entregas/registrar'] = 'api/Entregas/registrar';
$route['api/entregas/validar/(:num)'] = 'api/Entregas/validar/$1';
$route['api/entregas/pendientes'] = 'api/Entregas/pendientes';
 $route['api/entregas/contratos-pendientes'] = 'api/Entregas/contratos_pendientes';

$route['api/devoluciones'] = 'api/Devoluciones/index';
$route['api/devoluciones/(:num)'] = 'api/Devoluciones/show/$1';
$route['api/devoluciones/registrar'] = 'api/Devoluciones/registrar';
$route['api/devoluciones/validar/(:num)'] = 'api/Devoluciones/validar/$1';
$route['api/devoluciones/pendientes'] = 'api/Devoluciones/pendientes';

$route['api/devoluciones/productos_contrato/(:num)'] = 'api/Devoluciones/productos_contrato/$1';
$route['api/devoluciones/subir_foto'] = 'api/Devoluciones/subir_foto';
$route['api/devoluciones/fotos/(:num)'] = 'api/Devoluciones/fotos/$1';
$route['api/devoluciones/cargos/(:num)'] = 'api/Devoluciones/cargos/$1';

$route['api/transferencias'] = 'api/Transferencias/index';
$route['api/transferencias/(:num)'] = 'api/Transferencias/show/$1';
$route['api/transferencias/create'] = 'api/Transferencias/create';
$route['api/transferencias/enviar/(:num)'] = 'api/Transferencias/enviar/$1';
$route['api/transferencias/recibir/(:num)'] = 'api/Transferencias/recibir/$1';

$route['api/pagos'] = 'api/Pagos/index';
$route['api/pagos/contrato/(:num)'] = 'api/Pagos/por_contrato/$1';
$route['api/pagos/registrar'] = 'api/Pagos/registrar';

 $route['api/caja/sesion-abierta'] = 'api/Caja/sesion_abierta';
 $route['api/caja/abrir'] = 'api/Caja/abrir';
 $route['api/caja/cerrar/(:num)'] = 'api/Caja/cerrar/$1';
 $route['api/caja/resumen/(:num)'] = 'api/Caja/resumen/$1';
 $route['api/caja/sesiones'] = 'api/Caja/sesiones';

$route['api/reportes/contratos-activos'] = 'api/Reportes/contratos_activos';
$route['api/reportes/inventario'] = 'api/Reportes/inventario';
$route['api/reportes/cuentas-por-cobrar'] = 'api/Reportes/cuentas_por_cobrar';
$route['api/reportes/contratos-nuevos'] = 'api/Reportes/contratos_nuevos';
$route['api/reportes/clientes-nuevos'] = 'api/Reportes/clientes_nuevos';
$route['api/reportes/movimiento-material'] = 'api/Reportes/movimiento_material';
$route['api/reportes/cuentas-pendientes'] = 'api/Reportes/cuentas_pendientes';
$route['api/reportes/contratos-vencidos-plazo'] = 'api/Reportes/contratos_vencidos_plazo';
$route['api/reportes/inventario-stock'] = 'api/Reportes/inventario_stock';
$route['api/reportes/dashboard'] = 'api/Reportes/dashboard';
