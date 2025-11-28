DROP PROCEDURE IF EXISTS fnHistorialEspirasV8;

DELIMITER $$

CREATE DEFINER=`sql_vs_gvops_cl`@`localhost` PROCEDURE `fnHistorialEspirasV8`(
    IN P_FECHA_DESDE DATETIME,
    IN P_FECHA_HASTA DATETIME,
    IN P_ARREGLO_ESPIRAS VARCHAR(1000),
    IN P_DATO_CERO CHAR(1),
    IN P_CON_LAGUNA CHAR(1),
    IN P_DUMMY CHAR(1)
)
BEGIN
    DECLARE v_name VARCHAR(120);
    DECLARE v_id_tipo int;
    DECLARE v_id_dispositivo VARCHAR(120);
    DECLARE v_cod_sensor int;
    
    DECLARE v_estado BIGINT;
    DECLARE v_acumulacion BIGINT;
    declare v_created_at datetime;
    declare v_fecha_hasta datetime;
    declare v_grupo_estado int;
    
    DROP TABLE IF EXISTS tmp_espiras_vacias;
    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_espiras_vacias engine memory AS (
        select nombre, cast(cod_sensor as int) cod_sensor, CAST(CONCAT('Sensor ',cod_sensor) AS VARCHAR(20)) nombre_sensor,
        JSON_OBJECT('v', z.estado, 't', JSON_ARRAY()) objectos 
        From (
            WITH sequenceGenerator (id) AS (
                SELECT 0 AS id
                UNION ALL
                SELECT 1
                UNION ALL
                SELECT 2
                UNION ALL
                SELECT 3
                UNION ALL
                SELECT 4
                UNION ALL
                SELECT 5
                UNION ALL
                SELECT 6
            )
            SELECT nombre, JSON_EXTRACT(a.sensor, CONCAT('$[', id, ']')) AS cod_sensor
            FROM sequenceGenerator, (
                select nombre, JSON_EXTRACT(atributos, '$.sensors[*].name') nombre_sensores,
                JSON_EXTRACT(atributos, '$.sensors[*].id') sensor 
                From tbl_cot_02_dispositivos
                WHERE ID_TIPO=4 
                AND CASE WHEN P_ARREGLO_ESPIRAS ='' THEN true else FIND_IN_SET(id, P_ARREGLO_ESPIRAS) end 
            ) a                
        ) x, (
            SELECT -1 AS estado
            UNION ALL
            SELECT 1
            UNION ALL
            SELECT 0
        ) z
        where cod_sensor is not null 
        order by nombre, z.estado, cod_sensor 
    );

    CREATE INDEX IDX_tmp_espiras_vacias ON tmp_espiras_vacias(nombre, cod_sensor);

    if P_ARREGLO_ESPIRAS ='' then 
        DROP TABLE IF EXISTS tmp_datos_espiras;
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_datos_espiras engine memory AS (
            select a.id_dispositivo, a.cod_sensor, a.estado, acumulacion, a.created_at,
            ADDDATE(updated_at, INTERVAL 59 second) fecha_hasta,
            id grupo 
            from tbl_cot_07_historial_estado_dispositivos_espiras a force index(idx_created_at)
            where a.created_at >= ADDDATE(P_FECHA_DESDE, INTERVAL '-24' hour) 
            and a.created_at <= ADDDATE(P_FECHA_HASTA, INTERVAL '24' hour) 
        );
    else 
        DROP TABLE IF EXISTS tmp_datos_espiras;
        CREATE TEMPORARY TABLE IF NOT EXISTS tmp_datos_espiras engine memory AS (
            select a.id_dispositivo, a.cod_sensor, a.estado, acumulacion, a.created_at,
            ADDDATE(updated_at, INTERVAL 59 second) fecha_hasta,
            id grupo 
            from tbl_cot_07_historial_estado_dispositivos_espiras a force index(idx_created_at)
            where exists (select nombre From tmp_espiras_vacias where nombre = a.id_dispositivo) 
            and a.created_at >= ADDDATE(P_FECHA_DESDE, INTERVAL '-24' hour) 
            and a.created_at <= ADDDATE(P_FECHA_HASTA, INTERVAL '24' hour) 
        );
    end if;
    
    CREATE INDEX IDX_tmp_datos_espiras ON tmp_datos_espiras(created_at, fecha_hasta);
    
    DROP TABLE IF EXISTS Serie_estado_dispositivos;
    CREATE TEMPORARY TABLE IF NOT EXISTS Serie_estado_dispositivos engine memory AS (
        select id_dispositivo, 0 cod_sensor, 0 grupo_estado, estado, created_at fecha_min, created_at fecha_max 
        from tbl_cot_07_historial_estado_dispositivos_espiras a limit 0
    );

    delete from tmp_datos_espiras where created_at > P_FECHA_HASTA;
    delete from tmp_datos_espiras where fecha_hasta < P_FECHA_DESDE;
    
    insert into Serie_estado_dispositivos (id_dispositivo, cod_sensor, grupo_estado, estado, fecha_min, fecha_max)
    select id_dispositivo, cod_sensor, grupo, estado, created_at fecha_min, fecha_hasta fecha_max
    from tmp_datos_espiras b force index(IDX_tmp_datos_espiras)
    where (created_at >= P_FECHA_DESDE and fecha_hasta <= P_FECHA_HASTA);

    delete from tmp_datos_espiras where (created_at >= P_FECHA_DESDE and fecha_hasta <= P_FECHA_HASTA);

    insert into Serie_estado_dispositivos (id_dispositivo, cod_sensor, grupo_estado, estado, fecha_min, fecha_max)
    select id_dispositivo, cod_sensor, grupo, estado,
    if(created_at < P_FECHA_DESDE, P_FECHA_DESDE, created_at) min_real,
    if(fecha_hasta > P_FECHA_HASTA, P_FECHA_HASTA, fecha_hasta) max_real 
    from tmp_datos_espiras;

    IF P_DATO_CERO ='1' THEN 
        DROP TABLE IF EXISTS data_cero_error;
        CREATE TEMPORARY TABLE IF NOT EXISTS data_cero_error AS (
            SELECT ID_DISPOSITIVO FROM Serie_estado_dispositivos 
            WHERE ESTADO=0 
            GROUP BY ID_DISPOSITIVO 
        );
        
        delete from Serie_estado_dispositivos
        where not exists (select 1 from data_cero_error where Serie_estado_dispositivos.ID_DISPOSITIVO=ID_DISPOSITIVO);
        
        delete from tmp_espiras_vacias
        where not exists (select 1 from data_cero_error where tmp_espiras_vacias.nombre=ID_DISPOSITIVO);
    END IF;

    IF P_CON_LAGUNA ='1' THEN 
        DROP TABLE IF EXISTS data_cero_error;
        CREATE TEMPORARY TABLE IF NOT EXISTS data_cero_error AS (
            SELECT ID_DISPOSITIVO FROM Serie_estado_dispositivos 
            WHERE ESTADO=-1 
            GROUP BY ID_DISPOSITIVO 
        );
        
        delete from Serie_estado_dispositivos
        where not exists (select 1 from data_cero_error where Serie_estado_dispositivos.ID_DISPOSITIVO=ID_DISPOSITIVO);
        
        delete from tmp_espiras_vacias
        where not exists (select 1 from data_cero_error where tmp_espiras_vacias.nombre=ID_DISPOSITIVO);
    END IF;

    DROP TABLE IF EXISTS array_json;
    CREATE TEMPORARY TABLE IF NOT EXISTS array_json engine memory AS (
        select id_dispositivo, cod_sensor,
        concat('Sensor ', cod_sensor) nombre_sensor,
        JSON_OBJECT('v', estado, 't', JSON_ARRAY(UNIX_TIMESTAMP(fecha_min), UNIX_TIMESTAMP(fecha_max))) objetos 
        From Serie_estado_dispositivos
    );

    insert into array_json
    select * From tmp_espiras_vacias a 
    where not exists (select 1 from array_json b where b.id_dispositivo = a.nombre and b.cod_sensor = a.cod_sensor);

    insert into array_json
    SELECT * FROM (
        select '-' espira, 1 codigo, '-' nombre, 
        concat('{"v": 1, "t": [', UNIX_TIMESTAMP(P_FECHA_DESDE), ',', UNIX_TIMESTAMP(ADDDATE(P_FECHA_DESDE, INTERVAL 1 minute)), ']}') json 
        union all 
        select '-', 1, '-', 
        concat('{"v": 1, "t": [', UNIX_TIMESTAMP(P_FECHA_HASTA), ',', UNIX_TIMESTAMP(ADDDATE(P_FECHA_HASTA, INTERVAL 1 minute)), ']}')
    ) t;

    DROP TABLE IF EXISTS array_json_orden;
    CREATE TEMPORARY TABLE IF NOT EXISTS array_json_orden AS (
        select id_dispositivo, concat('{"l": "', nombre_sensor, '" ,"d": [', group_concat(objetos), ']}') data_sensor 
        from array_json 
        group by id_dispositivo, cod_sensor, nombre_sensor
        ORDER BY id_dispositivo, cod_sensor, nombre_sensor
    );
    
    select concat('[', group_concat(sensores), ']') JSON_ESPIRAS From (
        select concat('{"g":" ', id_dispositivo, '","d": [', group_concat(data_sensor), ']}', '') sensores 
        from array_json_orden
        group by id_dispositivo
    ) t2;
END$$

DELIMITER ;