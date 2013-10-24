<?php 

/*  common funcitons for various scripts of eiseAdmin */
/* funciton dumps tables specified in $arrTables according to $arrOptions */

function dumpTables($oSQL, $arrTables, $arrOptions=Array()){

    $arrDefaultOptions = array('DropCreate' => true
        , 'crlf'=>"\n"
        , 'sql_type'=>'INSERT'
        , );

    $arrOptions = array_merge($arrDefaultOptions, $arrOptions);

    $strDump = '';

    foreach($arrTables as $ix=>$value){

        if (is_integer($ix)) {
            $tableName = $value;
            $tableOptions = Array();
        } else {
            $tableName = $ix;
            $tableOptions = $value;
        }

        $strDump .= dumpTable($oSQL, $tableName, array_merge($arrOptions, $tableOptions));

    }

    return $strDump;

}


function dumpTable ($oSQL, $tableName, $tableOptions){

    $crlf = $tableOptions['crlf'];
    $strDump = '';

    // recognize is it table or view or hell knows what it is
    $sqlKind = "SHOW FULL TABLES LIKE '{$tableName}'";
    $rsKind = $oSQL->q($sqlKind);
    $rwKind = $oSQL->f($rsKind);
    switch($rwKind['Table_type']){

        case "VIEW":
            $flagView = true;
            $objKind = 'VIEW';
            break;
        case "BASE TABLE":
            $objKind = 'TABLE';
            break;
        default:
            throw new Exception("Unknown object type/unknown database object: {$tableName}", 1);
            
    }

    if (!$flagView)
        $strDump .= $crlf."SET FOREIGN_KEY_CHECKS=0;{$crlf}";
    
    // if DropCreate - add DROP... CREATE statements;
    if ($tableOptions['DropCreate']){

        $strDump .= $crlf."DROP {$objKind} IF EXISTS `{$tableName}`;".$crlf;

        $sqlGetCreate = "SHOW CREATE {$objKind} `{$tableName}`";
        $fieldCreate = "Create ".ucfirst(strtolower($objKind));

        $rsCreate = $oSQL->q($sqlGetCreate);
        $rwCreate = $oSQL->f($rsCreate);

        $strCreate = $rwCreate[$fieldCreate];

        if ($flagView){
            // wash out damn alogorithm and definer
            $strCreate = preg_replace('/(ALGORITHM\=UNDEFINED\s+DEFINER=[\S]+\s+SQL\s+SECURITY\s+DEFINER)/','',$strCreate);
        }

        $strDump .= $strCreate.';'.$crlf;

    }

    // if there's a view - nothing to dump
    if ($flagView || $tableOptions['flagNoData'])
        return $strDump;


    // otherwise dump all data in table---------------------- 
    // THANKS PMA TEAM for the code -------------------------
    // slightly updated by ISE at 2013

    $arrFields = array();$strFields = '';
    $sqlFields = "SHOW FIELDS FROM `{$tableName}`";
    $rsFields = $oSQL->q($sqlFields);
    while($rwFields = $oSQL->f($rsFields)){
        $arrFields[$rwFields['Field']]=$rwFields;
        $strFields .= ($strFields=='' ? '' : ', ')."`{$rwFields['Field']}`";
    }

    if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {
        // update
        $schema_insert  = 'UPDATE ';
        if ($tableOptions['sql_ignore']) {
            $schema_insert .= 'IGNORE ';
        }
        $schema_insert .= "`{$tableName}` SET";
    } else {
        // insert or replace
        if ($tableOptions['sql_type'] == 'REPLACE') {
            $sql_command    = 'REPLACE';
        } else {
            $sql_command    = 'INSERT';
        }

        // delayed inserts?
        if ($tableOptions['sql_delayed']) {
            $insert_delayed = ' DELAYED';
        } else {
            $insert_delayed = '';
        }

        // insert ignore?
        if ($tableOptions['sql_type'] == 'INSERT' && (!$tableOptions['DropCreate'] && isset($GLOBALS['sql_ignore']))) {
            $insert_delayed .= ' IGNORE';
        }

        // scheme for inserting fields
        if ($tableOptions['sql_columns']) {
            $schema_insert = $sql_command . $insert_delayed ." INTO `{$tableName}`"
                           . ' (`' . $strFields . '`) VALUES';
        } else {
            $schema_insert = $sql_command . $insert_delayed ." INTO `{$tableName}`"
                           . ' VALUES';
        }
    }

    $search       = array("\x00", "\x0a", "\x0d", "\x1a"); //\x08\\x09, not required
    $replace      = array('\0', '\n', '\r', '\Z');
    $current_row  = 0;
    $query_size   = 0;
    if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {
        $separator    = ';';
    } else {
        $separator    = ',';
        $schema_insert .= $crlf;
    }

    $sqlTable = "SELECT * FROM `{$tableName}`";
    $result = $oSQL->q($sqlTable);
    while ($row = $oSQL->f($result)) {
        $current_row++;
        foreach ($row as $j=>$value) {

            $rwCol = $arrFields[$j];

            // NULL
            if (!isset($row[$j]) || is_null($row[$j])) {
                $values[]     = 'NULL';
            // a number
            } elseif (preg_match("/int/i", $rwCol["Type"])
                || preg_match("/float/i", $rwCol["Type"])
                || preg_match("/double/i", $rwCol["Type"])
                || preg_match("/decimal/i", $rwCol["Type"])
                || preg_match("/bit/i", $rwCol["Type"])
                ) {
                $values[] = $row[$j];

            // a BLOB
            } elseif (preg_match("/binary/i", $rwCol["Type"])
                || preg_match("/blob/i", $rwCol["Type"])
                || preg_match("/text/i", $rwCol["Type"])) {
                
                // empty blobs need to be different, but '0' is also empty :-(
                if (empty($row[$j]) && $row[$j] != '0') {
                    $values[] = '\'\'';
                } else {
                    $values[] = '0x' . bin2hex($row[$j]);
                }
            // something else -> treat as a string
            } else {
                $values[] = '\'' . str_replace($search, $replace, PMA_sqlAddslashes($row[$j])) . '\'';
            } // end if
        } // end foreach

        // should we make update?
        if (!$tableOptions['DropCreate'] 
        && $tableOptions['sql_type'] == 'UPDATE') {
            /*
            $insert_line = $schema_insert;
            for ($i = 0; $i < $fields_cnt; $i++) {
                if (0 == $i) {
                    $insert_line .= ' ';
                }
                if ($i > 0) {
                    // avoid EOL blank
                    $insert_line .= ',';
                }
                $insert_line .= $field_set[$i] . ' = ' . $values[$i];
            }

            $insert_line .= ' WHERE ' . PMA_getUniqueCondition($result, $fields_cnt, $fields_meta, $row);
            */
        } else {

            // Extended inserts case
            //if (isset($GLOBALS['sql_extended'])) {
            if (true) {
                if ($current_row == 1) {
                    $insert_line  = $schema_insert . '(' . implode(', ', $values) . ')';
                } else {
                    $insert_line  = '(' . implode(', ', $values) . ')';
                    if (isset($tableOptions['sql_max_query_size']) 
                            && $tableOptions['sql_max_query_size'] > 0 && $query_size + strlen($insert_line) > $tableOptions['sql_max_query_size']) {
                        
                        $strDump .= ';' . $crlf;
                        
                        $query_size = 0;
                        $current_row = 1;
                        $insert_line = $schema_insert . $insert_line;
                    }
                }
                $query_size += strlen($insert_line);
            }
            // Other inserts case
            else {
                $insert_line      = $schema_insert . '(' . implode(', ', $values) . ')';
            }
        }
        unset($values);

        $strDump .= ($current_row == 1 ? '' : $separator . $crlf) . $insert_line;

    } // end while
    if ($current_row > 0) {
        $strDump .= ';' . $crlf;
    }

    if (!$flagView)
        $strDump .= "SET FOREIGN_KEY_CHECKS=1;{$crlf}";

    return $strDump;

}

function PMA_sqlAddslashes($a_string = '', $is_like = false, $crlf = false, $php_code = false)
{
    if ($is_like) {
        $a_string = str_replace('\\', '\\\\\\\\', $a_string);
    } else {
        $a_string = str_replace('\\', '\\\\', $a_string);
    }

    if ($crlf) {
        $a_string = str_replace("\n", '\n', $a_string);
        $a_string = str_replace("\r", '\r', $a_string);
        $a_string = str_replace("\t", '\t', $a_string);
    }

    if ($php_code) {
        $a_string = str_replace('\'', '\\\'', $a_string);
    } else {
        $a_string = str_replace('\'', '\'\'', $a_string);
    }

    return $a_string;
} // end of the 'PMA_sqlAddslashes()' function

 ?>