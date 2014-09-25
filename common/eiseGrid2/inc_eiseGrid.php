<?php
class eiseGrid {

function __construct($oSQL
	, $strName
    , $arrConfig
    ){
    
    GLOBAL $intra;

    $this->conf = Array(                    //defaults for eiseGrid
        'titleDel' => "Del" // column title for Del
        , "titleAdd" => "Add >>" // column title for Add
        //, 'controlBarButtons' => 'add|insert|moveup|movedown|delete|save'
        , 'extraInputs' => Array("DataAction"=>"update")
        , 'urlToSubmit' => $_SERVER["PHP_SELF"]
        , 'dateFormat' => (isset($intra->conf['dateFormat']) ? $intra->conf['dateFormat'] : "d.m.Y")  
        , 'timeFormat' => (isset($intra->conf['timeFormat']) ? $intra->conf['timeFormat'] : "H:i") 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => (isset($intra->conf['decimalSeparator']) ? $intra->conf['decimalSeparator'] : ".")
        , 'thousandsSeparator' => (isset($intra->conf['thousandsSeparator']) ? $intra->conf['thousandsSeparator'] : ",")
        , 'totalsTitle' => 'Totals'
        , 'noRowsTitle' => 'Nothing found'
        , 'arrPermissions' => Array("FlagWrite" => true)
        , 'Tabs3DCookieName' => $strName.'_tabs3d'
    );
    
    $this->conf = array_merge($this->conf, $arrConfig);
    $this->name = $strName;
	$this->oSQL = $oSQL;
    $this->permissions = $this->conf["arrPermissions"];
    
    //backward-compatibility staff
    $this->permissions["FlagDelete"] = (isset($this->conf['flagNoDelete']) ? !$this->conf['flagNoDelete'] : $this->permissions["FlagDelete"]);
    $this->permissions["FlagWrite"] = (isset($this->conf['flagDisabled']) ? !$this->conf['flagDisabled'] : $this->permissions["FlagWrite"]);
    
}


function Execute($allowEdit=true) {
	
    GLOBAL $_DEBUG;
    GLOBAL $strLocal;
   
    $oSQL=$this->oSQL;
   
    $strRet = "<div class=\"eiseGrid\" id=\"{$this->name}\">\r\n";
    
    if (!$allowEdit)
        $this->permissions["FlagWrite"] = false;
    
    if ($this->permissions["FlagWrite"]  && !empty($this->conf['controlBarButtons'])){
        
        $arrButtons = explode("|", $this->conf['controlBarButtons']);
        
        $strControlBar = "<div class=\"eg_controlbar\">";
        
        foreach ($arrButtons as $btn){
            $strControlBar .= "<input type=\"button\" class=\"eg_button eg_button_{$btn}\" value=\" \">\r\n";
        }
        
        $strControlBar .= "</div>";
        
        $strRet .= $strControlBar;
        
    }
    
    if(count($this->Tabs3D)>0){
        $strRet .= "<div id=\"{$this->name}_tabs3d\">\r\n";
        $strRet .= "<ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $strRet .= "<li><a href=\"#{$this->name}_tabs3d_{$tab['ID']}\">{$tab['title']}</a></li>\r\n"; 
        }
        $strRet .= "</ul>\r\n";
        foreach($this->Tabs3D as $ix=>$tab){
            $strRet .= "<div id=\"{$this->name}_tabs3d_{$tab['ID']}\" class=\"eg_pseudotabs\"></div>\r\n"; 
    }
    }

    $strRet .= "<table class=\"eg_table\">\r\n";
    $strHead = "<thead>\r\n";
    $strHead .= "<tr>\r\n";

    $this->visibleColumns = Array();
    $this->hiddenInputs = Array();
    
    $this->headerColumns = array();
    $this->arrSpans = array();

    $strCols = '';

    $nColNumber = 0;

    $spannedColumns = array();

    foreach ($this->Columns  as $ix=>$col){
        
        if ((int)$this->permissions["FlagWrite"]==0){
            $this->Columns[$ix]['static'] = true;
        }
        if ($col['class'])
            $this->Columns[$ix]['staticClass'] = ' '.preg_replace("/\[.+?\]/", "", $col['class']);
        
        if ($col["title"]!=""){

            $strCols .= "<col class=\"{$this->name}_{$col['field']}\">\n";

            $this->Columns[$ix]['style'] = $col['style'];
            
            $spannedColumns[] = $this->Columns[$ix];                
            
            if ((isset($this->Columns[$ix+1])
                //&& $this->Columns[$ix+1]['title']!=''
                && $this->Columns[$ix+1]['title']!=$col['title']) || !isset($this->Columns[$ix+1])){
                    $strHead .= "\t<th".
                            ($col["style"]!="" 
                            ? " style=\"{$col['style']}\""
                            : "").
                        " class=\"{$this->name}_{$spannedColumns[0]['field']}"
                            .($col['mandatory'] 
                                ? " eg_mandatory" 
                                : "").$this->Columns[$ix]['staticClass']."\"".
                        ">"
                        .($nColNumber==0 && $this->permissions["FlagWrite"] && !($this->permissions["FlagDelete"]===false)
                            ? '<input type="hidden" id="inp_'.$this->name.'_deleted" name="inp_'.$this->name.'_deleted" value="">'
                            : '')
                        .htmlspecialchars($col["title"])
                        ."</th>\r\n";
                if (count($spannedColumns)>1) {
                    $this->arrSpans[$spannedColumns[0]['field']]=count($spannedColumns);
                }
                $this->headerColumns[$col['field']] = array_merge($col, array('spannedColumns'=>$spannedColumns));
                $spannedColumns = array();
            }
            
            switch($col['type']){
                case "select":
                case "combobox":
                    if (!isset($col['arrValues']))
                        $this->Columns[$ix]['arrValues'] = Array();
                        
                    if (count($col['arrValues'])==0){
                        $oSQL = $this->oSQL;
                        if ($col['sql']){
                            $rs = $oSQL->do_query($col['sql']);
                        } else 
                            if ($col['source']){
                                $rs = $this->getDataFromCommonViews($oSQL, "", "", $col['source'], $col['prefix']);
                            }
                        if (is_resource($rs) // for mysql_query() function
							|| is_object($rs) // for mysqli::query() function
							)
                            while ($rw = $oSQL->fetch_array($rs)){
                                    $this->Columns[$ix]['arrValues'][(string)$rw['optValue']] = $rw['optText'];
                                }    
                    }
                    break;
                default:
                    break;
            }
            
            $this->visibleColumns[$col["field"]] = &$this->Columns[$ix];
            if (isset($col['width']))
                $this->arrWidth[$col["field"]] = $col['width'].(preg_match('/^[0-9]+$/', $col['width']) ? 'px' : '');

            $nColNumber++;
            
        } else {
            if ($col['type']!='row_id')
                $this->hiddenInputs[$col["field"]] = &$this->Columns[$ix];
        }
        
        if ($col['type']=='row_id') {
            $inpRowID = $col;
        }
    }

    $strCols = '<colgroup>'.$strCols.'</colgroup>';

    $strHead .= "</tr>\r\n";
    $strHead .= "</thead>\r\n";
    
    //$strRet .= $strCols;

    $strRet .= $strHead;
    
    $strRet .= "\r\n";


    $strRet .= "<tbody>\r\n";
    $strRet .= "<tr>\r\n";
    $strRet .= "<td class=\"eg_tdBody\" colspan=\"".count($this->visibleColumns)."\">";
    $strRet .= "<div class=\"eg_body\">";

    $strRet .= "<table>\r\n";
    $strRet .= $strCols;
    $strRet .= "<tbody>\r\n";


    $this->hiddenInputs = array_merge(
        Array($inpRowID['field'] => $inpRowID
            , "inp_{$this->name}_updated" => Array(
                    'field' => "inp_{$this->name}_updated"
                )
           )
        , $this->hiddenInputs
    );
    


    // template row
    $strRet .= "<tr class=\"eg_template\">\r\n";
    $iCol = 0;
    foreach($this->visibleColumns as $field=>$col){
        $strRet .= $this->paintCell($col, $iCol, null);
        $iCol++;
    }
    $strRet .= "</tr>\r\n";
    
    //other rows
    if (count($this->Rows)>0)
        foreach($this->Rows as $iRow=>$row){
            $iCol = 0;
            $strRet .= "<tr class=\"eg_data".($row['__rowClass'] ? ' '.$row['__rowClass'] : '')."\">";
            foreach($this->visibleColumns as $field=>$col){
                $strRet .= $this->paintCell($col, $iCol, $iRow);
                $iCol++;
            }
            $strRet .= "</tr>\r\n";
        }

    $strRet .= "<tr class=\"eg_tr_no_rows\"><td class=\"eg_no_rows\" colspan=\"".count($this->visibleColumns)."\">{$this->conf['noRowsTitle']}</td></tr>\r\n";
    $strRet .= "<tr class=\"eg_tr_spinner\"><td class=\"eg_spinner\" colspan=\"".count($this->visibleColumns)."\">&nbsp;</td></tr>\r\n";

    $strRet .= "</tbody>\r\n";
    $strRet .= "</table>";

    $strRet .= "</div>";
    $strRet .= "</td>";
    $strRet .= "</tr>\r\n";
    
    $strRet .= "</tbody>";
    
    //if there's any totals
    $strFooter .= "<tfoot>";
    $strFooter .= "<tr>";
    
    $iColspan = 0;
    $iTotalsCol = 0;
    foreach($this->headerColumns as $field => $col){
        if ($col['totals']){
            if ($iColspan>0){
                $strFooter .= "\t<td class=\"eg_totals_caption\"".($iColspan>1 ? " colspan=\"{$iColspan}\"" : "").">".
                    ($iTotalsCol==0 ? $this->conf['totalsTitle'].":" : "")."</td>\r\n";
                
            }
            $strFooter .= "<td class=\"{$this->name}_{$field} eg_{$col['type']}\"><div></div></td>";
            $iTotalsCol++;
            $iColspan = 0;
            continue;
        }
        $iColspan++;
    }
    
    if ($iColspan>0){
       $strFooter .= "\t<td class=\"eg_totals_caption\"".($iColspan>1 ? " colspan=\"{$iColspan}\"" : "")."></td>\r\n"; 
    }
    
    $strFooter .= "</tr>";
    $strFooter .= "</tfoot>";
    
    if ($iTotalsCol!=0){
        $strRet .= $strFooter;
    }
    
    $strRet .= "</table>\r\n";
    
    $arrConfig = $this->conf;
    foreach($this->Columns as $ix=>$col){
        $arrConfig['columns'][$col['field']] = Array('type'=>$col['type'], 'title'=>$col['title']);
        if ($col['mandatory']){
            $arrConfig['columns'][$col['field']]['mandatory'] = $col['mandatory'];
        }
        if ($col['totals']){
            $arrConfig['columns'][$col['field']]['totals'] = $col['totals'];
        }
        if ($col['decimalPlaces']){
            $arrConfig['columns'][$col['field']]['decimalPlaces'] = $col['decimalPlaces'];
        }
        if ($col['static']===true){
            $arrConfig['columns'][$col['field']]['static'] = true;
        }
        if ($col['disabled']===true){
            $arrConfig['columns'][$col['field']]['disabled'] = true;
        }
    }
    
    $jsonConfig = json_encode(array_merge($arrConfig, array('widths'=>$this->arrWidth
        , 'spans' => $this->arrSpans)
    ));
    $strRet .= "<input type=\"hidden\" id=\"inp_".$this->name."_config\" value=\"".htmlspecialchars($jsonConfig)."\">";
    
    if(count($this->Tabs3D)>0){
        $strRet .= "</div>\r\n";        
    }

    
    $strRet .= "</div>\r\n";
    
    
    echo $strRet;

    return $strRet;
}

function paintCell($col, $ixCol, $ixRow, $rowID=""){
    
    $field = ($col['type']=="del" ? "del" : $col["field"]);
    $row = $this->Rows[$ixRow];
    $val = $ixRow!==null ? $row[$field] : $col['default'];
    $cell = $col;
    
    $arrSuffix = array();
    if (count($this->Tabs3D)>0 && ($ixRow===null || is_array($val))) {
        foreach($this->Tabs3D as $ix=>$tab){
            $arrSuffix[] = $tab['ID'];
        }
        $arrVal = $val;
    } else {
        $arrSuffix = array('');
        $arrVal = array($val);
    }
    
    if ($ixRow===null){ //for template row: all calcualted class are grounded, static/disabled set to 0, href grounded
        $cell['class'] = $cell['staticClass'];
        $cell['static'] = (is_string($cell['static']) ? 0 : $cell['static']);
        $cell['disabled'] = (is_string($cell['disabled']) ? 0 : $cell['disabled']) ;
        $cell['href'] = "" ;
    } else // calculate row-dependent options: class, static/disabled, or href 
        foreach($this->Rows[$ixRow] as $rowKey=>$rowValue){
            $cell['class'] = str_replace("[{$rowKey}]", $rowValue, $cell['class']);
            $cell['static'] = (is_string($cell['static']) ? str_replace("[{$rowKey}]", $rowValue, $cell['static']) : $cell['static']);
            $cell['disabled'] = (is_string($cell['disabled']) ? str_replace("[{$rowKey}]", $rowValue, $cell['disabled']) : $cell['disabled']) ;
            if ($cell['source'])
                $cell['source'] = (is_string($cell['source']) ? str_replace("[{$rowKey}]", $rowValue, $cell['source']) : $cell['source']) ;
            if ($col['href']){
                $cell['href'] = (strpos($cell['href'], "[{$rowKey}]")!==null // if argument exists in HRef
                    ? ($val==''||$rowValue==''
                        ? $cell['href'] 
                        : str_replace("[{$rowKey}]"
                            , (strpos($cell['href'], "[{$rowKey}]")===0 
                                ? $rowValue // avoid urlencode() for first argument
                                : urlencode($rowValue)), $cell['href']))
                    : $cell['href']
                );
                $cell['target'] = str_replace("[{$rowKey}]", $rowValue, $cell['target']);
			}
        }
    
    if ((int)$cell['disabled'])
        $cell['class'] .= " eg_disabled";
    
    $class = "eg_".$col['type'].($cell['class'] != "" ? " ".$cell['class'] : '');
    
    $strCell = "";
    $strCell .= "\t<td class=\"{$this->name}_{$field} {$class}\"".(
            $cell["style"]!="" 
            ? " style=\"{$cell["style"]}\""
            : "").">";

    // hidden inputs are to be repeated once
    if ($ixCol==0){
        if (is_array($this->hiddenInputs))
        foreach($this->hiddenInputs as $hidden_field=>$hidden_col){
            $strCell .= "\r\n\t\t<input type=\"hidden\" name=\"{$hidden_field}[]\" value=\"".
                htmlspecialchars($ixRow===null 
                    ? $hidden_col["default"] 
                    : $this->Rows[$ixRow][$hidden_field]).
                "\">";
        }
        
    }

    // for 3d grid roll thru suffixes array
    foreach($arrSuffix as $suffix){

        $_val = ($suffix ? $val[$suffix] : $val);
        $_field = ($suffix ? $field."[{$suffix}]" : $field);
        $classStr = ($suffix ? "eg_3d eg_3d_{$suffix}" : '');
        $classAttr = ($suffix ? ' class="'.$classStr.'"' : '');

        //pre-format value
        if ($_val!==null){
            switch($cell['type']){
                case "date":
                    $_val = $this->DateSQL2PHP( $_val, ($col['format'] ? $col['format'] : $this->conf['dateFormat']) );
                    break;
                case "datetime":
                    $_val = $this->DateSQL2PHP( $_val
                        , ($col['format'] ? $col['format'] : ($this->conf['dateFormat']." ".$this->conf['timeFormat'])) );
                    break;
                case "money":
                case "float":
                case "double":
                case "real":
                case "numeric":
                case "number":
                case "integer":
                    $cell['decimalPlaces'] = isset($cell['decimalPlaces']) 
                        ? $cell['decimalPlaces'] 
                        : (in_array($cell['type'], Array('numeric','number','integer')) 
                            ? 0
                            : $this->conf['decimalPlaces']);
                    $_val = round($_val, $cell['decimalPlaces']);
                    $_val = number_format($_val, $cell['decimalPlaces'], $this->conf['decimalSeparator'], $this->conf['thousandsSeparator']);
                    break;
                default:
                    break;
            }
        }
        
        //if cell is disabled, static, or there's a HREF, we make hidden input and text value
        if ((int)$cell['static'] || (int)$cell['disabled'] || $cell['href']!=""){
            
            $aopen = "";$aclose = "";
            if ($cell['href']!=""){
                $aopen = "<a href=\"{$cell['href']}\"".($cell['target'] ? " target=\"{$cell['target']}\"" : '').">";
                $aclose = "</a>";
            }
            
            $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
            switch($col['type']){
                case "boolean":
                case "checkbox":
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_field}_chk[]\"".($_val==true ? " checked" : "")." disabled>";
                    break;
                case "combobox":
                case "ajax_dropdown":
                    $strCell .= "<div{$classAttr}>".$aopen.htmlspecialchars($this->getSelectValue($cell, $row)).$aclose."</div>";
                    break;
                case "textarea":
                    $strCell .= "<div{$classAttr}>".$aopen.str_replace("\r\n", "<br>", htmlspecialchars($_val)).$aclose."</div>";
                    break;
                case "html":
                    $strCell .= "<div{$classAttr}>".$aopen.$_val.$aclose."</div>";
                    break;
                default:
                    $strCell .= "<div{$classAttr}>".$aopen.htmlspecialchars($_val).$aclose."</div>";
                break;
            }
            
        } else { //display input and stuff
        
            switch($col['type']){
                case "order":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val).
                        "\"><div{$classAttr}><span>".htmlspecialchars($_val)."</span>.</div>";
                    break;
                case "text":
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    break;
                case "textarea":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<div contenteditable='true' class=\"eg_editor {$classStr}\">".str_replace("\r\n", "<br>", htmlspecialchars($_val))."</div>";
                    break;
                case "boolean":
                case "checkbox":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"checkbox\" name=\"{$_field}_chk[]\"".($_val==true ? " checked" : "").">";
                    break;
                case "combobox":
                case "select":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}_text[]\" value=\"".htmlspecialchars($this->getSelectValue($cell, $row))."\">";
                    if ($ixRow===null){ //paint floating select
                        $strCell .= "<select id=\"select_{$_field}\" class=\"eg_floating_select\">\r\n";
                        $strCell .= ($cell['defaultText']!="" ? "\t<option value=\"\">{$cell['defaultText']}\r\n" : "");
                        
                        foreach($cell['arrValues'] as $key => $_value){

                            if (is_array($_value)){ // if there's an optgoup
                                $retVal .= '<optgroup label="'.(isset($cell['optgroups']) ? $cell['optgroups'][$key] : $key).'">';
                                foreach($_value as $optVal=>$optText){
                                    $retVal .= "<option value='$optVal'".((string)$optVal==(string)$strValue ? " SELECTED " : "").">".str_repeat('&nbsp;',5*$cell["indent"][$key]).htmlspecialchars($optText)."</option>\r\n";
                                }
                                $retVal .= '</optgroup>';
                            } else
                                $strCell .= "\t<option value=\"".htmlspecialchars($key)."\">".htmlspecialchars($_value)."\r\n";
                        }
                        $strCell .= "</select>\r\n";
                    }
                    break;
                case "ajax_dropdown":
                    $strCell .= "<input type=\"hidden\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}_text[]\"".
                        " src=\"{table:'{$cell['source']}', prefix:'{$cell['prefix']}'}\" autocomplete=\"off\"".
                        ($cell['extra'] ? ' extra="'.htmlspecialchars($cell['extra']).'"' : '').
                        " value=\"".htmlspecialchars($this->getSelectValue($cell, $row))."\">";
                case "del":
                    break;
                default: 
                    $strCell .= "<input{$classAttr} type=\"text\" name=\"{$_field}[]\" value=\"".htmlspecialchars($_val)."\">";
                    break;
            }
        
        }
            
    }
    
    
    $strCell .= "</td>\r\n";
    return $strCell;
}

function getSelectValue($cell, $row){
    
    $oSQL = $this->oSQL;
    
    if ($row[$cell['field']]==""){
        return $cell['defaultText'];
    }
    
    if ($row[$cell['field']."_text"] != ""){
        return $row[$cell['field']."_text"];
    }
    if (isset($cell['arrValues'][$row[$cell['field']]])){
        return $cell['arrValues'][$row[$cell['field']]];
    } else {
        if ($cell['source']!=''){
            $rs = $this->getDataFromCommonViews($this->oSQL, $row[$cell['field']], "", $cell['source'], $cell['prefix']);
            $rw = $oSQL->fetch_array($rs);
            return $rw["optText"];
        } else {
            return $cell['defaultText'];
        }
    }
}

function getDataFromCommonViews($oSQL, $strValue, $strText, $strTable, $strPrefix){

    GLOBAL $strLocal;
    
    if (function_exists("getDataFromCommonViews")) // normally defined in common.php
        return (getDataFromCommonViews($oSQL, $strValue, $strText, $strTable, $strPrefix));
    
    if ($strPrefix!=""){
        $arrFields = Array(
            "idField" => "{$strPrefix}ID"
            , "textField" => "{$strPrefix}Title"
            , "textFieldLocal" => "{$strPrefix}TitleLocal"
            , "delField" => "{$strPrefix}FlagDeleted"
            );
    } else {
        $arrFields = Array(
            "idField" => "optValue"
            , "textField" => "optText"
            , "textFieldLocal" => "optTextLocal"
            , "delField" => "optFlagDeleted"
        );
    }    
    
    $sql = "SELECT `{$arrFields["idField"]}` as optValue, `".$arrFields["textField{$strLocal}"]."` as optText
        FROM `{$strTable}`";
    
    if ($strValue!=""){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue);
    } else { //value-based search
        $sql .= "\r\nWHERE 
        (`{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'
            OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($strText, "for_search")." COLLATE 'utf8_general_ci'";
        $sql .=	")
		AND `{$arrFields["delField"]}`<>1";
    }
    $sql .= "\r\nLIMIT 0, 30";
    /*
    echo "<pre>";
    print_r($sql);
    echo "</pre>";
   //*/
    
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}


function dateSQL2PHP($dtVar, $datFmt="d.m.Y H:i"){
GLOBAL $dbType;
$result =  $dtVar ? date($datFmt, strtotime($dtVar)) : "";
$result = preg_replace("/( 00\:00(\:00){0,1})/", "", $result);
return($result);
}

function datePHP2SQL($dtVar, $valueIfEmpty="NULL"){
// потом сделаю
}


function Update($arrNewData = array(), $flagExecute = true){
	
    GLOBAL $usrID;

    if (count($arrNewData)==0){
        $arrNewData = $_POST;        
    }

    $sql = Array();
    
    $oSQL=$this->oSQL;
    $tblName = $this->conf['strTable'];
    
    $arrTable = $this->getTableInfo($oSQL->dbName, $tblName);
    
    $arrFields = Array();
    $arrValues = Array();
    $arrFieldsValues = Array();
    
    switch($arrTable['PKtype']){
       case "auto_increment":
          break;
       case "GUID":
          $arrFields[] = $arrTable['PK'][0];
          $arrValues[] = "UUID()";
          break;
       default:
          break;
    }
    
    //defining PK field on grid
    foreach($this->Columns as $i=>$col){
        if ($this->Columns[$i]['type']=="row_id") 
            $pkColName = $this->Columns[$i]['field'];
        
        if ($col['mandatory'])
            $mndFieldName = $col["field"];
        
        foreach($arrTable["columns"] as $j=>$tCol) 
            if ((!$col['disabled'] || ($col['disabled'] && in_array($col['field'], $arrTable['PK'])))
                && $col['type']!="row_id"
                && $col['field'] == $arrTable["columns"][$j]["Field"]
                ){
                $arrFields[] = $col['field'];
                $arrTable["columns"][$j]['DataType'] = ($col['type']=="combobox" || $col['type']=="ajax_dropdown" 
                    ? "combobox" 
                    : $arrTable["columns"][$j]['DataType']);
                $arrValues[] = $this->getSQLValue($arrTable["columns"][$j], true);
                $arrFieldsValues[] = $col['field'] ." = ".$this->getSQLValue($arrTable["columns"][$j], true);
            }
    }
    
    if (!$mndFieldName)
        $mndFieldName = $pkColName;
    
    if ($arrTable['hasActivityStamp']){
        $arrFields[] = $arrTable['prefix']."InsertBy";
        $arrFields[] = $arrTable['prefix']."InsertDate";
        $arrFields[] = $arrTable['prefix']."EditBy";
        $arrFields[] = $arrTable['prefix']."EditDate";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrFieldsValues[] = $arrTable['prefix']."EditBy = '\$usrID'";
        $arrFieldsValues[] = $arrTable['prefix']."EditDate = NOW()";
    }
    
    //deleted items
    $strDeleted = $arrNewData["inp_".$this->name."_deleted"];
    //echo $strDeleted;
    $arrToDelete = explode("|", $strDeleted);
    
    for ($i=0;$i<count($arrToDelete);$i++)
        if ($arrToDelete[$i]!="") {
            $sql[] = "DELETE FROM $tblName WHERE ".$this->getMultiPKCondition($arrTable['PK'], $arrToDelete[$i]);
        }
    
    // running thru updated
    for($i=1;$i<count($arrNewData[$pkColName]);$i++)
        if ($arrNewData["inp_".$this->name."_updated"][$i] && $arrNewData[$mndFieldName][$i]!=""){
            if ($arrNewData[$pkColName][$i]=="") { //if inserted
                eval("\$sql[] = \"INSERT INTO $tblName (\r\n".
                     "              ".implode("\r\n              , ", $arrFields)."\r\n".
                     "           ) VALUES (\r\n".
                     "              ".implode("\r\n              , ", $arrValues)."
                  )\";");
            } else { //if updated
                eval("\$sql[] = \"UPDATE $tblName SET
                  ".implode("\r\n                  , ", $arrFieldsValues)."\r\n".
                  "           WHERE ".$this->getMultiPKCondition($arrTable['PK'], $arrNewData[$pkColName][$i])."\";");
            }
        }

     /*
    echo "<pre>";
    print_r($arrNewData);
    print_r($sql);
    print_r($arrTable);
    echo "</pre>";
    die();
//    */
    
    
    for ($i=0;$i<count($sql);$i++){
       if ($flagExecute)
          $oSQL->do_query($sql[$i]);
       else {
          echo "<pre>";
          echo $sql[$i];
          echo "</pre>";
       }
    }
    
    
	return(true);
}


function getSQLValue($col){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."'][\$i]";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "\".(integer)$strPost.\"";
        break;
      case "order":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
      case "money":
        $strValue = "\".(double)str_replace('{$this->conf['decimalSeparator']}', '.', str_replace('{$this->conf['thousandsSeparator']}', '', $strPost)).\"";
        break;
      case "boolean":
      case "checkbox":
        $strValue = "\".(integer)\$_POST['".$col["Field"]."'][\$i].\"";
        break;      
      case "binary":
        $strValue = "\".mysql_real_escape_string(\$".$col["Field"].").\"";
        break;
      case "datetime":
      case "date":
        $strValue = "\".DatePHP2SQL($strPost).\"";
        break;
      case "activity_stamp":
        if (preg_match("/By$/i", $col["Field"]))
           $strValue .= "'\$usrID'";
        if (preg_match("/Date$/i", $col["Field"]))
           $strValue .= "NOW()";
        break;
      case "FK":
      case "combobox":
      case "ajax_dropdown":
       $strValue = "\".($strPost!=\"\" ? \"'\".$strPost.\"'\" : \"NULL\").\"";
        break;
      case "PK":
      case "text":
	  case "varchar":
      default:
        $strValue = "\".\$oSQL->e($strPost).\"";
        break;
    }
    //echo "<pre>";
    //echo $strValue;
    //print_r($col);
    
    return $strValue;
}

function Update_Intra($flagExecute = true){
	
    //GLOBAL $usrID;
    GLOBAL $intra;
    $usrID = $intra->usrID;
    
    $sql = Array();
    
    $oSQL=$this->oSQL;
    $tblName = $this->conf['strTable'];
    
    $arrTable = $intra->getTableInfo($oSQL->dbName, $tblName);
    
    $arrFields = Array();
    $arrValues = Array();
    $arrFieldsValues = Array();
    
    switch($arrTable['PKtype']){
       case "auto_increment":
          break;
       case "GUID":
          $arrFields[] = $arrTable['PK'][0];
          $arrValues[] = "UUID()";
          break;
       default:
          break;
    }
    
    //defining PK field on grid
    for($i=0;$i<count($this->Columns);$i++){
        $col = $this->Columns[$i];
        if ($this->Columns[$i]['type']=="row_id") 
            $pkColName = $this->Columns[$i]['field'];
        
        if ($col['mandatory'])
            $mndFieldName = $col["field"];
        
        for ($j=0;$j<count($arrTable["columns"]);$j++) 
            if (!$col['disabled'] && ($col['type']!="row_id")  && $col['field'] == $arrTable["columns"][$j]["Field"]){
                $arrFields[] = $col['field'];
                $arrTable["columns"][$j]['DataType'] = ($col['type']=="combobox" || $col['type']=="ajax_dropdown" 
                    ? "combobox" 
                    : $arrTable["columns"][$j]['DataType']);
                $arrValues[] = $this->getSQLValue($arrTable["columns"][$j], true);
                $arrFieldsValues[] = $col['field'] ." = ".$this->getSQLValue($arrTable["columns"][$j], true);
            }
    }
    
    if (!$mndFieldName)
        $mndFieldName = $pkColName;
    
    if ($arrTable['hasActivityStamp']){
        $arrFields[] = $arrTable['prefix']."InsertBy";
        $arrFields[] = $arrTable['prefix']."InsertDate";
        $arrFields[] = $arrTable['prefix']."EditBy";
        $arrFields[] = $arrTable['prefix']."EditDate";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrValues[] = "'\$usrID'";
        $arrValues[] = "NOW()";
        $arrFieldsValues[] = $arrTable['prefix']."EditBy = '\$usrID'";
        $arrFieldsValues[] = $arrTable['prefix']."EditDate = NOW()";
    }
    
    //deleted items
    $strDeleted = $_POST["inp_".$this->name."_deleted"];
    //echo $strDeleted;
    $arrToDelete = explode("|", $strDeleted);
    
    for ($i=0;$i<count($arrToDelete);$i++)
        if ($arrToDelete[$i]!="") {
            $sql[] = "DELETE FROM $tblName WHERE ".$intra->getMultiPKCondition($arrTable['PK'], $arrToDelete[$i]);
        }
    
    // running thru updated
    for($i=1;$i<count($_POST[$pkColName]);$i++)
        if ($_POST["inp_".$this->name."_updated"][$i] && $_POST[$mndFieldName][$i]!=""){
            if ($_POST[$pkColName][$i]=="") { //if inserted
                eval("\$sql[] = \"INSERT INTO $tblName (\r\n".
                     "              ".implode("\r\n              , ", $arrFields)."\r\n".
                     "           ) VALUES (\r\n".
                     "              ".implode("\r\n              , ", $arrValues)."
                  )\";");
            } else { //if updated
                eval("\$sql[] = \"UPDATE $tblName SET
                  ".implode("\r\n                  , ", $arrFieldsValues)."\r\n".
                  "           WHERE ".$intra->getMultiPKCondition($arrTable['PK'], $_POST[$pkColName][$i])."\";");
            }
        }

     //*
    echo "<pre>";
    print_r($_POST);
    print_r($sql);
    print_r($arrTable);
    echo "</pre>";
    die();
//    */
    
    
    for ($i=0;$i<count($sql);$i++){
       if ($flagExecute)
          $oSQL->do_query($sql[$i]);
       else {
          echo "<pre>";
          echo $sql[$i];
          echo "</pre>";
       }
    }
    
    
	return(true);
}

private function getMultiPKCondition($arrPK, $strValue){
    
    GLOBAL $intra;
    
    if (is_object($intra))
        return $intra->getMultiPKCondition($arrPK, $strValue);
    else 
        if (function_exists('getMultiPKCondition')){
            return getMultiPKCondition($arrPK, $strValue);
        }
        
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."='".$arrValue[$jj]."'";
    return $sql_;
    
}

private function getTableInfo($dbName, $tableName){
    GLOBAL $intra;
    
    if (is_object($intra))
        return $intra->getTableInfo($dbName, $tableName);
    else 
        if (function_exists('getTableInfo')){
            return getTableInfo($dbName, $tableName);
        }
        
        throw new Exception('Unable to retrieve table information using "getTableInfo()" function');
}


}

class easyGrid extends eiseGrid{}
?>