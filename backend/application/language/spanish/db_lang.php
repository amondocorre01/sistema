<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$lang['db_invalid_connection_str'] = 'No se pudo determinar la configuración de base de datos a partir de la cadena de conexión enviada.';
$lang['db_unable_to_connect'] = 'No se pudo conectar al servidor de base de datos con la configuración proporcionada.';
$lang['db_unable_to_select'] = 'No se pudo seleccionar la base de datos especificada: %s';
$lang['db_unable_to_create'] = 'No se pudo crear la base de datos especificada: %s';
$lang['db_invalid_query'] = 'La consulta enviada no es válida.';
$lang['db_must_set_table'] = 'Debe establecer la tabla de base de datos a utilizar en la consulta.';
$lang['db_must_use_set'] = 'Debe usar el método "set" para actualizar un registro.';
$lang['db_must_use_index'] = 'Debe especificar un índice para las actualizaciones por lote.';
$lang['db_batch_missing_index'] = 'Una o más filas enviadas para actualización por lote no incluyen el índice especificado.';
$lang['db_must_use_where'] = 'No se permiten actualizaciones si no contienen una cláusula "where".';
$lang['db_del_must_use_where'] = 'No se permiten eliminaciones si no contienen una cláusula "where" o "like".';
$lang['db_field_param_missing'] = 'Para obtener campos se requiere el nombre de la tabla como parámetro.';
$lang['db_unsupported_function'] = 'Esta función no está disponible para la base de datos que está usando.';
$lang['db_transaction_failure'] = 'Falla de transacción: se realizó rollback.';
$lang['db_unable_to_drop'] = 'No se pudo eliminar la base de datos especificada.';
$lang['db_unsupported_feature'] = 'Característica no soportada por la plataforma de base de datos utilizada.';
$lang['db_unsupported_compression'] = 'El formato de compresión elegido no está soportado por el servidor.';
$lang['db_filepath_error'] = 'No se pudo escribir datos en la ruta de archivo indicada.';
$lang['db_invalid_cache_path'] = 'La ruta de caché indicada no es válida o no tiene permisos de escritura.';
$lang['db_table_name_required'] = 'Se requiere un nombre de tabla para esta operación.';
$lang['db_column_name_required'] = 'Se requiere un nombre de columna para esta operación.';
$lang['db_column_definition_required'] = 'Se requiere una definición de columna para esta operación.';
$lang['db_unable_to_set_charset'] = 'No se pudo establecer el juego de caracteres de la conexión: %s';
$lang['db_error_heading'] = 'Ocurrió un error de base de datos';
