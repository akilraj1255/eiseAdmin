<?php
class eiseGrid {

function __construct($oSQL
	, $strName
    , $arrConfig
    ){
    
    $this->conf = Array(                    //defaults for eiseGrid
        'titleDel' => "Del" // column title for Del
        , "titleAdd" => "Add >>" // column title for Add
        , "titleColor" => "Color" // column title for Color picker
        , 'flagNoTitle' => false // if set, no title displayed
        , 'showControlBar' => false
        //, 'controlBarButtons' => 'add|insert|moveup|movedown|save'
        , 'extraInputs' => Array("DataAction"=>"update")
        , 'urlToSubmit' => $_SERVER["PHP_SELF"]
        , 'dateFormat' => "d.m.Y" // 
        , 'timeFormat' => "h:i" // 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => "."
        , 'thousandsSeparator' => ","
        , 'totalsTitle' => 'Totals'
        , 'noRowsTitle' => 'Nothing found'
        , 'arrPermissions' => Array("FlagWrite" => true)
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
    
    $strRet .= "<table>\r\n";
    $strHead = "<thead>\r\n";
    $strHead .= "<tr>\r\n";
    
    $this->visibleColumns = Array();
    $this->hiddenInputs = Array();
    
    foreach ($this->Columns  as $ix=>$col){
        
        if ((int)$this->permissions["FlagWrite"]==0){
            $this->Columns[$ix]['static'] = true;
        }
        
        if ($col["title"]!=""){
            $col['style'] = 
                ($col["width"]!="" 
                ?  "width: {$col["width"]};".
                    (!preg_match("/\%$/", $col['width']) 
                        ? "min-width: {$col["width"]}"
                        : "" ) 
                : "" )."{$col["style"]}";
            $this->Columns[$ix]['style'] = $col['style'];
            $strHead .= "\t<th".
                ($col["style"]!="" 
                ? " style=\"{$col['style']}\""
                : "").
            " class=\"{$this->name}_{$col['field']}\"".
            ">".htmlspecialchars($col["title"]).
                ($col['mandatory'] ? "*" : "")."</th>\r\n";
            
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
            
        } else {
            if ($col['type']!='row_id')
                $this->hiddenInputs[$col["field"]] = &$this->Columns[$ix];
        }
        
        if ($col['type']=='row_id') {
            $inpRowID = $col;
        }
    }
    
    $this->hiddenInputs = array_merge(
        Array($inpRowID['field'] => $inpRowID
            , "inp_{$this->name}_updated" => Array(
                    'field' => "inp_{$this->name}_updated"
                )
           )
        , $this->hiddenInputs
    );
    
    if ($this->permissions["FlagWrite"] && $this->permissions["FlagDelete"]!==false){
        $strHead .= "\t<th class=\"eg_del\">".htmlspecialchars($this->conf["titleDel"]);
        $strHead .= "<input type='hidden' id='inp_".$this->name."_deleted' name='inp_".$this->name."_deleted' value=''>\r\n";
        $strHead .= "</th>\r\n";
        $this->visibleColumns["Del"] = Array('type'=>'del');
    }
    $strHead .= "</tr>\r\n";
    $strHead .= "</thead>\r\n";
    
    $strRet .= $strHead;
    
    $strRet .= "\r\n";


    $strRet .= "<tbody>\r\n";
    
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
            $strRet .= "<tr>";
            foreach($this->visibleColumns as $field=>$col){
                $strRet .= $this->paintCell($col, $iCol, $iRow);
                $iCol++;
            }
            $strRet .= "</tr>\r\n";
        }
    else {
        $strRet .= "<tr><td class=\"eg_no_rows\" colspan=\"".count($this->visibleColumns)."\">{$this->conf['noRowsTitle']}</td></tr>\r\n";
    }
    
    $strRet .= "</tbody>";
    
    //if there's any totals
    $strFooter .= "<tfoot>";
    $strFooter .= "<tr>";
    
    $iColspan = 0;
    $iTotalsCol = 0;
    foreach($this->visibleColumns as $field => $col){
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
        if ($col['title']!=""){
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
            if ($col['static']){
                $arrConfig['columns'][$col['field']]['static'] = true;
            }
            if ($col['disabled']){
                $arrConfig['columns'][$col['field']]['disabled'] = true;
            }
        }
    }
    
    $jsonConfig = json_encode($arrConfig);
    $strRet .= "<input type=\"hidden\" name=\"inp_".$this->name."_config\" id=\"inp_".$this->name."_config\" value=\"".htmlspecialchars($jsonConfig)."\">";
    
    
    $strRet .= "</div>\r\n";
    
    
    echo $strRet;

    return $strRet;
}

function paintCell($col, $ixCol, $ixRow, $rowID=""){
    
    $field = ($col['type']=="del" ? "del" : $col["field"]);
    $row = $this->Rows[$ixRow];
    $val = $ixRow!==null ? $row[$field] : $col['default'];
    $cell = $col;
    
    
    
    if ($ixRow===null){ //for template row: all calcualted class are grounded, static/disabled set to 0, href grounded
        $cell['class'] = preg_replace("/\[.+?\]/", "", $cell['class']);
        $cell['static'] = (is_string($cell['static']) ? 0 : $cell['static']);
        $cell['disabled'] = (is_string($cell['disabled']) ? 0 : $cell['disabled']) ;
        $cell['href'] = "" ;
    } else // calculate row-dependent options: class, static/disabled, or href 
        foreach($this->Rows[$ixRow] as $rowKey=>$rowValue){
            $cell['class'] = str_replace("[{$rowKey}]", $rowValue, $cell['class']);
            $cell['static'] = (is_string($cell['static']) ? str_replace("[{$rowKey}]", $rowValue, $cell['static']) : $cell['static']);
            $cell['disabled'] = (is_string($cell['disabled']) ? str_replace("[{$rowKey}]", $rowValue, $cell['disabled']) : $cell['disabled']) ;
            if ($col['href']){
                $cell['href'] = (strpos($cell['href'], "[{$rowKey}]")
                    ? ($val==''||$rowValue=='' 
                        ? "" 
                        : str_replace("[{$rowKey}]", urlencode($rowValue), $cell['href']))
                    : $cell['href']
                );
			}
            
        }
    
    if ((int)$cell['disabled'])
        $cell['class'] = "eg_disabled";
    
    $class = "eg_".$col['type'].($cell['class'] != "" ? " ".$cell['class'] : '');
    
    //echo "<pre>";
    //print_r($cell);
    
    $strCell = "";
    $strCell .= "\t<td class=\"{$this->name}_{$field} {$class}\"".(
            $cell["style"]!="" 
            ? " style=\"{$cell["style"]}\""
            : "").">";
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
    
    //pre-format value
    switch($cell['type']){
        case "date":
            $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']);
            break;
        case "datetime":
            $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']." ".$this->conf['timeFormat']);
            break;
        case "money":
        case "float":
        case "double":
        case "numeric":
        case "number":
        case "integer":
            $cell['decimalPlaces'] = isset($cell['decimalPlaces']) 
                ? $cell['decimalPlaces'] 
                : (in_array($cell['type'], Array('numeric','number','integer')) 
                    ? 0
                    : $this->conf['decimalPlaces']);
            $val = round($val, $cell['decimalPlaces']);
            $val = number_format($val, $cell['decimalPlaces'], $this->conf['decimalSeparator'], $this->conf['thousandsSeparator']);
            break;
        default:
            break;
    }
    
    //if cell is disabled, static, or there's a HREF, we make hidden input and text value
    if ((int)$cell['static'] || (int)$cell['disabled'] || $cell['href']!=""){
        
        $aopen = "";$aclose = "";
        if ($cell['href']!=""){
            $aopen = "<a href=\"{$cell['href']}\">";
            $aclose = "</a>";
        }
        
        $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
        switch($col['type']){
            case "boolean":
            case "checkbox":
                $strCell .= "<input type=\"checkbox\" name=\"{$field}_chk[]\"".($val==true ? " checked" : "")." disabled>";
                break;
            case "combobox":
                $strCell .= "<div>".$aopen.$this->getSelectValue($cell, $row).$aclose."</div>";
                break;
            case "textarea":
                $strCell .= "<div>".$aopen.str_replace("\r\n", "<br>", htmlspecialchars($val)).$aclose."</div>";
                break;
			case "html":
				$strCell .= "<div>".$aopen.$val.$aclose."</div>";
				break;
            default:
                $strCell .= "<div>".$aopen.htmlspecialchars($val).$aclose."</div>";
            break;
        }
        
    } else { //display input and stuff
    
        switch($col['type']){
            case "order":
                $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\"><div><span>".htmlspecialchars($val)."</span>.</div>";
                break;
            case "text":
                $strCell .= "<input type=\"text\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                break;
            case "textarea":
                $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                $strCell .= "<div contenteditable='true' class=\"eg_editor\">".str_replace("\r\n", "<br>", htmlspecialchars($val))."</div>";
                break;
            case "boolean":
            case "checkbox":
                $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                $strCell .= "<input type=\"checkbox\" name=\"{$field}_chk[]\"".($val==true ? " checked" : "").">";
                break;
            case "combobox":
            case "select":
                $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                $strCell .= "<input type=\"text\" name=\"{$field}_text[]\" value=\"".htmlspecialchars($this->getSelectValue($cell, $row))."\">";
                if ($ixRow===null){ //paint floating select
                    $strCell .= "<select id=\"select_{$field}\" class=\"eg_floating_select\">\r\n";
                    $strCell .= ($cell['defaultText']!="" ? "\t<option value=\"\">{$cell['defaultText']}\r\n" : "");
                    foreach($cell['arrValues'] as $optValue => $optText){
                        $strCell .= "\t<option value=\"".htmlspecialchars($optValue)."\">".htmlspecialchars($optText)."\r\n";
                    }
                    $strCell .= "</select>\r\n";
                }
                break;
            case "ajax_dropdown":
                $strCell .= "<input type=\"hidden\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                $strCell .= "<input type=\"text\" name=\"{$field}_text[]\"".
                    " src=\"{table:'{$col['source']}', prefix:''}\" autocomplete=\"off\"".
                    " value=\"".$this->getSelectValue($cell, $row)."\">";
            case "del":
                break;
            default: 
                $strCell .= "<input type=\"text\" name=\"{$field}[]\" value=\"".htmlspecialchars($val)."\">";
                break;
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


function Update($flagExecute = true){
	
    GLOBAL $usrID;
    
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
    for($i=0;$i<count($this->Columns);$i++){
        $col = $this->Columns[$i];
        if ($this->Columns[$i]['type']=="row_id") 
            $pkColName = $this->Columns[$i]['field'];
        
        if ($col['mandatory'])
            $mndFieldName = $col["field"];
        
        foreach($arrTable["columns"] as $j=>$tCol) 
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
            $sql[] = "DELETE FROM $tblName WHERE ".$this->getMultiPKCondition($arrTable['PK'], $arrToDelete[$i]);
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
                  "           WHERE ".$this->getMultiPKCondition($arrTable['PK'], $_POST[$pkColName][$i])."\";");
            }
        }

     /*
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


function getSQLValue($col){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."'][\$i]";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "'\".(integer)$strPost.\"'";
        break;
      case "order":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
        $strValue = "'\".(double)str_replace(',', '', $strPost).\"'";
        break;
      case "boolean":
      case "checkbox":
        $strValue = "'\".(integer)\$_POST['".$col["Field"]."'][\$i].\"'";
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

     /*
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