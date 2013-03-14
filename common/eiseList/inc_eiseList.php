<?php
/* ===================================================== */
//    Version 1.0 (4.0)
// replaces Ilya's library called 'phpList'
// (c)2005-2012 Eliseev Ilya http://e-ise.com
// requires - JS: jquery ui
// requires - PHP: eise's inc_mysql.php
// eiseIntra: compatibility OK, reads $intra->conf if it exists
// Authors: Ilya Eliseev, Pencho Belneiski, Dmitry Zakharov, Igor Zhuravlev
// License: GNU Public License v.3
// sponsored: Yusen Logistics Rus LLC
// contibutors: Ilya Eliseev, Igor Zhuravlev, Dmitry Zakharov, Pencho Belneiski
/* ===================================================== */

class eiseList{

const DS = DIRECTORY_SEPARATOR;
const counterColumn = "phpLNums";

private $debug = true;
//private $debug = false;

public $conf = Array(
    'includePath' => '../'
    , 'dateFormat' => "d.m.Y" // 
    , 'timeFormat' => "h:i" // 
    , 'decimalPlaces' => "2"
    , 'decimalSeparator' => "."
    , 'thousandsSeparator' => ","
    , 'titleTotals' => 'Totals'
    , 'titlePleaseWait' => 'Please wait...'
    , 'titleNothing' => 'Nothing found'
    , 'titleERRORBadResponse' => 'ERROR: bad response'
    , 'titleTryReload' => 'try to reload this page'
    
    , 'dataSource' => "" //$_SERVER["PHP_SELF"]
    
    , 'rowsFirstPage' => 100
    , 'rowsPerPage' => 40
    , 'maxRowsForSelection' => 1000
    , 'calcFoundRows' => true
    , 'cacheSQL' => true
    , 'doNotSubmitForm' => true
);

private $oSQL;

private $arrHiddenCols = Array();

function __construct($oSQL, $strName, $arrConfig=Array()){
    
    $this->name = $strName;
    
    $this->oSQL = $oSQL;
    
    $this->sqlFrom = $arrConfig["sqlFrom"];unset($arrConfig["sqlFrom"]);
    $this->sqlWhere = $arrConfig["sqlWhere"];unset($arrConfig["sqlWhere"]);
    $this->defaultOrderBy = $arrConfig["defaultOrderBy"];unset($arrConfig["defaultOrderBy"]);
    $this->defaultSortOrder = $arrConfig["defaultSortOrder"];unset($arrConfig["defaultSortOrder"]);
    $this->exactSQL = $arrConfig["exactSQL"];unset($arrConfig["exactSQL"]);
    
    //merge with settings come from eiseINTRA
    if (is_object($arrConfig["intra"])){
        $intra = $arrConfig['intra'];
        $this->conf['dateFormat'] = $intra->conf['dateFormat'];
        $this->conf['timeFormat'] = $intra->conf['timeFormat'];
        $this->conf['decimalPlaces'] = $intra->conf['decimalPlaces'];
        $this->conf['decimalSeparator'] = $intra->conf['decimalSeparator'];
        $this->conf['thousandsSeparator'] = $intra->conf['thousandsSeparator'];
        $this->conf['strLocal'] = $intra->local;
        unset($arrConfig["intra"]);
    }

    $this->conf = array_merge($this->conf, $arrConfig);
    
    $this->conf["dataSource"] = ($this->conf["dataSource"]!="" ? $this->conf["dataSource"] : $_SERVER["PHP_SELF"]);
    
}

public function handleDataRequest(){ // handle requests and return them with Ajax, Excel, XML, PDF, whatsoever user can ask
    
    $DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
    if (!$DataAction)
        return;
    
    $oSQL = $this->oSQL;
    $this->error = "";
    
    if ($this->conf["cacheSQL"] && !$_GET["noCache"]){
        $this->getCachedSQL();
    } else {
        $this->handleInput();
        $this->composeSQL();
        if ($this->conf["cacheSQL"]) 
            $this->cacheSQL();
    }
    /*
    print_r($_GET);
    print_r($_COOKIE);
    print_r($this->Columns);
    echo ($this->strSQL);
    //*/
    $iOffset = (int)$_GET["offset"];
    
    $this->strSQL .= ($DataAction=="json" 
        ? "\n LIMIT {$iOffset}, ".($iOffset==0 
            ? $this->conf["rowsFirstPage"] 
            : (isset($_GET["recordCount"]) 
                ? (int)$_GET["recordCount"]
                : $this->conf['rowsPerPage']
                )
            )
        : "");
    
    if ($iOffset==0 && $this->conf["calcFoundRows"]) {
        $this->strSQL = str_replace("#SQL_CALC_FOUND_ROWS", "SQL_CALC_FOUND_ROWS", $this->strSQL);
    }
    
    try {
        $rsData = $oSQL->q($this->strSQL);
        if ($iOffset==0 && $this->conf["calcFoundRows"]){
            $nTotalRows = $oSQL->d("SELECT FOUND_ROWS();");
        }
    } catch(Exception $e){
        $this->error = $e->getMessage();
    }
    
    $iStart = $iOffset;
    switch($DataAction){
        case "json":
            
            header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
            header('Content-Type: application/json');
            
            $arrRows = Array();
            
            if ($this->error){
                echo json_encode(Array("error"=>$this->error));
                die();
            }
            while ($rw = $oSQL->f($rsData)){
                $arrRows[] = $this->getRowArray($iStart++, $rw);
            }
            
            $arrRet = Array("rows"=>$arrRows);
            $arrRet['nTotalRows'] = isset($nTotalRows) ? (int)$nTotalRows : null;
            $arrRet['nRowsReturned'] = $oSQL->n($rsData);
            
            if ($this->debug){
                $arrDebug = Array(
                    "get" => $_GET
                    , "cookie" => $_COOKIE
                    , "columns"=> $this->Columns
                    , "sql" => $this->strSQL
                    , 'conf' => $this->conf
                    );
                    
                $arrRet = array_merge($arrDebug, $arrRet);
                
            }
            
            echo json_encode($arrRet);
            die();
        case "excelXML":
            
            set_time_limit(600);
            
            include_once (dirname(__FILE__). self::DS . "inc_excelXML.php");
            
            $xl = new excelXML();
            
            foreach($this->Columns as $col) {
                if ($col["title"]=="" || in_array($col["field"], $this->arrHiddenCols)) {
                    continue;
                }
                
                $arrHeader[$col["field"]] = $col["title"];
                
            }
            $xl->addHeader($arrHeader);
            
            $nRow = 0;
            while ($rw = $oSQL->f($rsData)){
                $nRow++;
                $rw[self::counterColumn] = $nRow;
                
                $arrRow = Array();
                foreach($this->Columns as $col) {
                    if ($col["title"]=="" || in_array($col["field"], $this->arrHiddenCols)) {
                        continue;
                    }
                    $arrRow[$col["field"]] = $this->formatData($col, $rw[$col["field"]], $rw);
                
                }
                $xl->addRow($arrRow);
            }
            
            $xl->Output();
            
            die();
    }
    
}


public function show(){ // draws the wrapper

    $this->handleInput();
    
    if ($this->conf["cacheSQL"]){
        
        $this->composeSQL();
        $this->cacheSQL();
        
    }
    
?>    
<div class="eiseList" id="<?php  echo $this->name ; ?>">

<form action="<?php echo $_SERVER["PHP_SELF"]; ?>">
<input type="hidden" id="DataAction" name="DataAction" value="newsearch">
<input type="hidden" id="<?php echo $this->name."OB"; ?>" name="<?php echo $this->name."OB"; ?>" value="<?php echo htmlspecialchars($this->orderBy); ?>">
<input type="hidden" id="<?php echo $this->name."ASC_DESC"; ?>" name="<?php echo $this->name."ASC_DESC"; ?>" value="<?php echo htmlspecialchars($this->sortOrder); ?>">
<input type="hidden" id="<?php echo $this->name."HiddenCols"; ?>" name="<?php echo $this->name."HiddenCols"; ?>" value="<?php echo htmlspecialchars(implode(",", $this->arrHiddenCols)); ?>">
<?php 
// Igor: Create fields for initial GET string-------------------------------------------------------------
foreach ($_GET as $key => $value) {
    if ( !($key=="offset" || $key=="DataAction" || preg_match("/^".$this->name."/",$key)) && strlen($value)>0){
        echo "<input type=hidden id=\"".$key."\" name=\"".$key."\" value=\"".urlencode($value)."\">\r\n";
    }
}
 ?>

<div class="el_header">
<h1><?php echo htmlspecialchars($this->conf["title"]); ?></h1>
<div class="el_foundRows">(<span class="el_span_foundRows"></span>)</div>
<div class="el_controlBar">
<input type="submit" value="Search" id="btnSearch">
<input type="button" value="Choose fields" id="btnFieldChooser"><?php 
if( !$this->conf["flagNoExcel"] ){ ?><input type="button" value="Open in Excel" id="btnOpenInExcel"><?php } ?>
<input type="button" value="Reset" id="btnReset">
</div>
</div>

<div class="el_table">
<table class="el_thead">
<?php  echo $this->showTableHeader(); ?>
</table>

<div class="el_body">
<table>
<tbody>
<tr class="el_template"><?php 
echo $this->showTemplateRow();
 ?></tr>
</tbody>
</table>
<div class="el_spinner"><?php  echo $this->conf["titlePleaseWait"] ; ?></div>
</div>

<?php 
if ($this->flagHasAggregate) {
 ?>
<table class="el_tfoot">
<tfoot>
<tr><?php  echo $this->showFooterRow() ; ?></tr>
</tfoot>
</table>
<?php 
}
 ?>
</div>

</form>

<div class="el_fieldChooser" style="display:none;"><?php  echo $this->showFieldChooser() ; ?></div>

<div class="el_debug"></div>
<input type="hidden" id="inp_<?php  echo $this->name ; ?>_config" name="inp_<?php  echo $this->name ; ?>_config" value="<?php  
    echo htmlspecialchars(json_encode($this->conf)) ; ?>">
</div>

<script>
$(document).ready(function(){
    eiseListInitialize();
});
</script>
<?php
}

private function showTableHeader(){
    
    $oSQL = $this->oSQL;
    
    $strOut = "<thead>";
    
    $this->nCols = 0;

    /* first and second rows - titles and filter inputs */
    foreach($this->Columns as $col) {
        if ($col["title"]=="" || in_array($col["field"], $this->arrHiddenCols)) {
            continue;
        }
        
        $strClassList = "{$this->name}_{$col["field"]}";
        $strClassList .= ($col['order_field'] ? " el_sortable" : "");
        
        $strArrowImg = "";
        if ($col['field']==$this->orderBy) {
            $strClassList .= " el_sorted_".strtolower($this->sortOrder);
        }
        /* TD for row title */
        $strTDHead = "<th";
        $strTDHead .= ($col["width"]!=""   ? " style=\"width: ".$col["width"]."\"" : "");
        $strTDHead .= " class=\"{$strClassList}\"";
        $strTDHead .=  ">" ;

        $strTDHead .= "<div>".htmlspecialchars($col['title'])."</div>";
        
        $strTDHead .= "</th>\r\n";

        /* TD for search input */
        $strTDFilter = "";
        if ($this->flagHasFilters) {
            $strTDFilter .= "<td class=\"{$this->name}_{$col['field']}".($col["filterValue"]!="" ? " el_filterset" : "")."\">";
            if ($col['filter']) {
                switch ($col['type']) {
                    case "combobox":
                        $arrCombo = Array();
                        if (is_array($col['source'])) {
                            $arrCombo = $col['source'];
                        } else {
                            $sqlCombo = (
                                preg_match("/^(vw_|tbl_)/", $col['source'])
                                ? ($col['source_prefix']!=""
                                    ? "SELECT `{$col['source_prefix']}Title{$this->conf['strLocal']}` as optText, `{$col['source_prefix']}ID` as optValue FROM `{$col['source']}`"
                                    : "SELECT * FROM `{$col['source']}`"
                                    )
                                : $col['source']
                            );
                            //echo $col['title']."\r\n";
                            $rsCombo = $oSQL->do_query($sqlCombo);
                            while ($rwCombo = $oSQL->fetch_array($rsCombo)) {
                                $arrCombo[$rwCombo['optValue']] = $rwCombo['optText'];
                            }
                        }
                        $strTDFilter .= "<select id='cb_".$col["filter"]."' name='".$this->name."_".$col["filter"]."' class='el_filter'>\r\n";
                        $strTDFilter .= "<option value=''>\r\n";
                        while (list($value, $text) = each($arrCombo)){
                            $strTDFilter .= "<option value='$value'".((string)$col["filterValue"]==(string)$value ? " selected" : "").">$text\r\n";
                        }
                        $strTDFilter .= "</select>\r\n";
                        break;
                    default:
                        $strTDFilter .= "<input type=text name='".$this->name."_".$col["filter"]."' class='el_filter' value='".$col["filterValue"]."'>";
                    break;
                }
            } elseif ($col['checkbox']) {
                $strTDFilter .= "<div align='center'><input type='checkbox' style='width:auto;'".
                    "id=\"sel_{$this->name}_all\" title='Select/unselect All'></div>";
            } else {
                $strTDFilter .= "&nbsp;";
            }
            $strTDFilter .= "</td>\r\n";
            
            $secondRow .= $strTDFilter;
            
        }
        
        $firstRow .= $strTDHead;
        
        $this->nCols++;
        
    }
    
    $strOut = "<thead><tr>{$firstRow}</tr><tr>{$secondRow}</tr></thead>";
    
    return $strOut;

}

private function showTemplateRow(){

    $strRow = "";
    
    foreach($this->Columns as $col){
        if (!$col["title"] || in_array($col["field"], $this->arrHiddenCols))
            continue;
            
        $strRow.= "<td class=\"{$this->name}_{$col["field"]} ".
            ($col["type"]!="" 
                ? "el_".$col["type"] 
                : ($col['checkbox'] ? "el_checkbox" : "text")).
            "\">".($col['checkbox']
                ? "<input type='checkbox' name='sel_{$this->name}[]' value='' id='sel_{$this->name}_'>" 
                : "")."</td>\r\n";
        
    }
    
    return $strRow;
    
}

private function showFooterRow(){
    return "<td colspan=\"{$this->nCols}\">&nbsp;</td>";
}

private function showFieldChooser(){
    
    $strOut = "";
    
    $jj=1;
    
    $nElementsInColumn = $this->nCols/2;
    for ($i=0; $i<Count($this->Columns); $i++)
        if (!in_array($this->Columns[$i]["title"], Array("", "##")) && $this->Columns[$i]["href"]==""){
            $cl = $this->Columns[$i];
            $id = "flc_".$this->name."_".$cl["field"]."";
            $strOut .= "<input type=\"checkbox\" name=\"{$id}\" id=\"{$id}\" style=\"width:auto;\"".
                (in_array($cl["field"], $this->arrHiddenCols) ? "" : " checked").">";
            $strOut .= "<label for=\"{$id}\">".$cl["title"]."</label><br>\r\n";
            if ($jj == floor($nElementsInColumn))
                $strOut .= "</td><td>\r\n";
            $jj++;
        }
    
    $strOut = "<div><table><tbody><tr><td>{$strOut}</td></tr></tbody></table></div>";
    
    return $strOut;
}


/* data handling functions */
private function handleInput(){

   GLOBAL $_DEBUG;

   //$_DEBUG = true;

   $this->arrHiddenCols = Array();
   $arrCookieToSet = Array();

   $this->cookieName = (isset($this->cookieName) ? $this->cookieName : $this->name)."_LstParams";
   $arrCookie = unserialize($_COOKIE[$this->cookieName]);

   $hiddenCols = (isset($_GET[$this->name."HiddenCols"]) ? $_GET[$this->name."HiddenCols"] : $arrCookie["HiddenCols"]);
   //print_r($hiddenCols);
   $this->arrHiddenCols = explode(",", $hiddenCols);
   $this->iMaxRows = (int)(isset($_GET[$this->name."MaxRows"])
        ? $_GET[$this->name."MaxRows"]
        : (isset($arrCookie["MaxRows"])
           ? $arrCookie["MaxRows"]
           : $this->iMaxRows));
   $this->orderBy =  (isset($_GET[$this->name."OB"])
        ? $_GET[$this->name."OB"]
        : (isset($arrCookie["OB"]) ? $arrCookie["OB"] : $this->defaultOrderBy)
        );
   $this->sortOrder = (isset($_GET[$this->name."ASC_DESC"])
        ? $_GET[$this->name."ASC_DESC"]
        : (isset($arrCookie["ASC_DESC"])
             ? $arrCookie["ASC_DESC"]
             : ($this->defaultSortOrder=="" ? "ASC" : $this->defaultSortOrder ))
        );
    $this->sortOrderAlt = ($this->sortOrder=="ASC" ? "DESC" : "ASC");

    $arrCookieToSet["HiddenCols"] = $hiddenCols;
    $arrCookieToSet["MaxRows"] = $this->iMaxRows;
    $arrCookieToSet["OB"] = $this->orderBy;
    $arrCookieToSet["ASC_DESC"] = $this->sortOrder;

    $this->arrOrderByCols = Array();

    /* dealing with filters and order_field */
    $this->flagHasFilters = false;
    for ($i=0;$i<count($this->Columns);$i++){
        $this->arrOrderByCols[] = ($this->Columns[$i]["order_field"]!="" ? $this->Columns[$i]["order_field"] : $this->Columns[$i]["field"]);
        if ($this->Columns[$i]["filter"]) {

            $strColInputName = $this->name."_".$this->Columns[$i]["filter"];

            if (isset($arrCookie[$strColInputName]) || isset($_GET[$strColInputName])){
                $this->Columns[$i]["filterValue"] = isset($_GET[$strColInputName]) ? $_GET[$strColInputName] : $arrCookie[$strColInputName];
                if ($this->Columns[$i]["filterValue"]!="")
                   $arrCookieToSet[$strColInputName] = $this->Columns[$i]["filterValue"];
            }

            $this->flagHasFilters = true;

        }
    }

    SetCookie($this->cookieName, serialize($arrCookieToSet), $this->cookieExpire, $_SERVER["PHP_SELF"]);
    
    if ($this->flagExcel)
        $this->iMaxRows = 0;
    
    if ($_DEBUG){
        echo "<pre>GET ARRAY:\r\n";
        print_r ($_GET);
        echo "</pre>";
        echo "<pre>COOKIE ARRAY:\r\n";
        print_r ($_COOKIE);
        echo "\r\nUnserialized\r\n";
        print_r ($arrCookie);
        echo "</pre>";
        echo "<pre>Columns ARRAY:\r\n";
        print_r ($this->Columns);
        echo "</pre>";
    }
}

private function composeSQL(){
    
    GLOBAL $_DEBUG;
    
    $this->flagGroupBy = false;
    $this->fieldsForCount = Array();
    
    foreach ($this->Columns as $i => $col){
        if ($col["field"]=="" || $col["field"]=="phpLNums") 
            continue;
            
        if ($col['PK']){ //if it is PK
            $this->sqlPK = $col['field'];
        }

        // SELECT
        $sqlTextField = "";
        if ($col["type"]=="combobox" || $col["type"]=="ajax_dropdown"){
            // if combobox or ajax_dropwon, we also compose _Text suffixed field
            if (preg_match("/^(vw_|tbl_)/", $col["source"])){
                $optText = ($col["source_prefix"]!="" ? $col["source_prefix"]."Title" : "optText");
                $optValue = ($col["source_prefix"]!="" ? $col["source_prefix"]."ID" : "optValue");
                $sqlTextField = "IFNULL((SELECT {$optText}{$this->conf['strLocal']} FROM `{$col["source"]}` WHERE {$optValue}=".($col["sql"] != ""
                            ? "({$col["sql"]})"
                            : $col["field"])."), '{$col['defaultText']}') as {$col["field"]}_Text";
                $this->sqlFields .= ( $this->sqlFields!="" ? "\r\n, " : "").$sqlTextField;
            }
        }

        $this->sqlFields .= ( $this->sqlFields!="" ? "\r\n, " : ""). // if 'sql' array member is set
            ($col["sql"]!="" && $col["sql"]!=$col["field"] ? "(".$col["sql"].") AS " : "").$col["field"];

        // GROUP BY
        if ($col['group']!="") { // if we should group by this column and 'sql' is set, we group by 'sql'
            $this->sqlGroupBy .= ($this->sqlGroupBy!="" ? ", " : "").($col['sql']!="" ? $col['sql'] : $col['field']);
            $this->flagGroupBy = true;
        }

        //WHERE/HAVING
        if ($strCondition = $this->getSearchCondition($col)) {// if we filter results by this column
			// HAVING - only for 
            //      non-grouped columns in aggregate queries 
            //      ajax_dropdown
            //      all other columns where we search by sql 'SELECT' subquery
            // WHERE - all the rest
            if (($this->flagGroupBy && !$col['group'])
                || $col["type"]=="ajax_dropdown"
                || $col["sql"]!=""
               ){
                  $this->sqlHaving = ($this->sqlHaving ? "(".$this->sqlHaving.") AND " : "").$strCondition;
                  if ($sqlTextField) $this->fieldsForCount[] = $sqlTextField;
                  $this->fieldsForCount[] = ($col["sql"]!="" && $col["sql"]!=$col["field"] ? "(".$col["sql"].") AS " : "").$col["field"];
            } else {
                  $this->sqlWhere = ($this->sqlWhere ? "(".$this->sqlWhere.") AND " : "").$strCondition;
            }
            /*
            if ($sqlTextField) $this->fieldsForCount[] = $sqlTextField;
                $this->fieldsForCount[] = ($col["sql"]!="" && $col["sql"]!=$col["field"] ? "(".$col["sql"].") AS " : "").$col["field"];
            */
        }

      
    }
   
   // if an element not found in order_fields collection, we set orderBy field as PK
    if (!in_array($this->orderBy, $this->arrOrderByCols)){
        $this->orderBy = $this->sqlPK;
    }
    
    if ($this->flagExcel && count($_GET[$this->sqlPK])>0){ // if we pass some arguments for exact excel output
        $strExact = "";
        for ($i=0;$i<count($_GET[$this->sqlPK]);$i++){
            $strExact .= ($strExact!="" ? "," : "")." '".$_GET[$this->sqlPK][$i]."'";
        }
        $this->sqlWhere = $this->sqlPK." IN (".$strExact.")";
    }

    $this->strSQL = "FROM ".$this->sqlFrom.($this->sqlWhere!="" ? "
        WHERE ".$this->sqlWhere : "");

    if ($this->flagGroupBy)
        $this->strSQL .= "
        GROUP BY ".$this->sqlGroupBy;
      
    $this->strSQL .= ($this->sqlHaving!="" ? "
        HAVING ".$this->sqlHaving : "");

    $this->strSQLFrom = $this->strSQL;
    
    $this->strSQL = "SELECT #SQL_CALC_FOUND_ROWS\r\n".$this->sqlFields."
        ".$this->strSQLFrom;

    $this->strSQL .= "
        ORDER BY ".$this->orderBy." ".$this->sortOrder;
   
    if ($_DEBUG){
        echo "<pre>strSQL: ".$this->strSQL."</pre>";
    }
}

private function getSearchCondition($col){
     
    $oSQL = $this->oSQL;
    
    if ($col["filterValue"]=="")
         return "";

    $strFlt = $col["filterValue"];

     switch ($col['type']) {
       case "text":
            $prgList = "/\s*[,\:\|]\s*/";
            if (preg_match($prgList, $strFlt)){
                $arrList = preg_split('/\s*[,\:\|]\s*/',$strFlt);
                if( count($arrList) > 1)
                    $strCondition = " {$col['filter']} IN ('".implode("', '", $arrList)."')";
                else
                    $strCondition = " {$col['filter']} LIKE ".$oSQL->escape_string($strFlt, "for_search");
            } else
                $strCondition = " {$col['filter']} LIKE ".$oSQL->escape_string($strFlt, "for_search");
          break;
       case "numeric":
       case "money":
          if (preg_match("/^([\<\>\=]{0,1})(\-){0,1}[0-9]+([\.][0-9]+){0,1}$/", $strFlt, $arrMatch)) {
            if ($arrMatch[1])
                $strCondition = " ".$col['filter'].$strFlt;
            else
                $strCondition = " ".$col['filter']." = ".$strFlt;
          } else
             $strCondition = "";
          break;
       case 'combobox':
         $strCondition = " {$col['filter']} = ".$oSQL->escape_string($strFlt)."";
         break;
       case 'ajax_dropdown':
         $strCondition = ($col['filter']!=$col['field'] 
                    ? " {$col['filter']} "
                    : " {$col['field']}_Text").
                    " LIKE ".$oSQL->escape_string($strFlt, "for_search");
         break;
       case "date":
       case "datetime":

         $prgDTOp = preg_replace("/^\//", "/(\|\|{0,1}|\&\&{0,1}|OR|AND){0,1}\s*(\>|\<|\=|\<\=|\>\=){0,1}\s*", prgDT);
         if (preg_match_all($prgDTOp, $strFlt, $arrMatch)) {

             for ($i=0;$i<count($arrMatch[0]);$i++){
                   $cond = $i==0 ? ""
                      : (
                        $arrMatch[1][$i]=="&" || $arrMatch[1][$i]=="&&" || $arrMatch[1][$i]=="AND"
                        ? "AND"
                        : "OR" );
                   $oper = $arrMatch[2][$i]=="" ? "=" : $arrMatch[2][$i];

                   if ($this->oSQL->dbtype=="MSSQL")
                       $strCondition .= ($cond ? " ".$cond." " : "")."DATEDIFF(d, '".$arrMatch[5][$i]."-".$arrMatch[4][$i]."-".$arrMatch[3][$i]."', ".$col['filter'].")".$oper."0";
                   if ($this->oSQL->dbtype=="MySQL5")
                       $strCondition .= ($cond ? " ".$cond." " : "")."DATEDIFF(".$col['filter'].", '".$arrMatch[5][$i]."-".$arrMatch[4][$i]."-".$arrMatch[3][$i]."')".$oper."0";
                   //echo "here ".$this->oSQL->dbtype;
              }
          }

          $strCondition = $i>1 ? "(".$strCondition.")" : $strCondition;
          break;
       default:
          $strCondition = " ".$col['filter']." = ".$this->oSQL->escape_string($strFlt);
          break;
     }

     return $strCondition;

}

private function cacheSQL(){
    
    $_SESSION[$this->name."_sql"]=$this->strSQL;
    $_SESSION[$this->name."_sqlPK"]=$this->sqlPK;
    
}

private function getCachedSQL(){
    
    $this->strSQL = $_SESSION[$this->name."_sql"];
    $this->sqlPK = $_SESSION[$this->name."_sqlPK"];
    
}

private function getRowArray($index, $rw){
    
    $arrRet = Array();
    $arrFields = Array();
    
    $iColCounter = 0;
    foreach($this->Columns as $i => $col){
        
        $arrField = Array();
        
        $valFormatted = "";
        $val = $rw[$col['field']];
        $class = "";
        $href = "";
        
        /* obtain calculated values for class and href */
        foreach($rw as $rowKey=>$rowValue){
            $col['class'] = str_replace("[{$rowKey}]", $rowValue, $col['class']);
            $col['href'] = str_replace("[{$rowKey}]", urlencode($rowValue), $col['href']) ;
        }
        
        if($col['class'])
            $arrField["c"] = $col['class'];
            
        if($col['href'])
            $arrField["h"] = (empty($val) ? "" : $col['href']);
        
        if ($col["field"]=="phpLNums")
            $val = ($index+1).".";
        
        /* formatting data */
        $valFormatted = $this->formatData($col, $val, $rw);
        
        $arrField["t"] = ($valFormatted!="" ? $valFormatted : $val); // we will always display text in here
        if (in_array($col["type"], Array("combobox", "ajax_dropdown")))
            $arrField["v"] = $val;
            
        $arrFields[$col['field']] = $arrField;
        
    }
    
    $arrRet["PK"] = $rw[$this->sqlPK];
    if ($rw['__rowClass']){
        $arrRet["c"] = $rw['__rowClass'];
    }
    $arrRet['r'] = $arrFields;
    
    return $arrRet;
}

private function formatData($col, $val, $rw){
    switch ($col['type']) {
            case "date":
                $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']);
                break;
            case "datetime":
                $val = $this->DateSQL2PHP($val, $this->conf['dateFormat']." ".$this->conf['timeFormat']);
                break;
            case "time":
                $val = $this->DateSQL2PHP($val, $this->conf['timeFormat']);
                break;
                
            case "numeric":
            case "integer":
            case "number":
            case "money":
            case "float":
            case "double":
                $cell['decimalPlaces'] = (in_array($col['type'], Array("numeric", "integer", "number"))
                    ? 0
                    : isset($cell['decimalPlaces']) ? $cell['decimalPlaces'] : $this->conf['decimalPlaces']
                    );
                $val = round($val, $cell['decimalPlaces']);
                $val = number_format($val, $cell['decimalPlaces'], $this->conf['decimalSeparator'], $this->conf['thousandsSeparator']);
                break;
            case "boolean":
                $val = (int)$val;
                break;
            case "combobox":         // return Text representation for FK-based columns
            case "ajax_dropdown":
                $val = ($rw[$col['field']."_Text"]!=""
                    ? $rw[$col['field']."_Text"]
                    : $val);
                break;
            case "text":
            default:
                mb_internal_encoding("UTF-8");
                $val = ($col["limitOutput"] > 0 && mb_strlen($rw[$col['field']]) > $col["limitOutput"]) 
                    ? mb_substr($rw[$col['field']], 0, $col["limitOutput"])."..." 
                    : $val;
                break;
        }
        return $val;
}

private function dateSQL2PHP($dtVar, $datFmt="d.m.Y H:i"){
    $result =  $dtVar ? date($datFmt, strtotime($dtVar)) : "";
    return($result);
}

}

class phpLister extends eiseList{
    function __construct($name){
        GLOBAL $oSQL;
        parent::__construct($oSQL, $name);
    }
    function Execute($oSQL, $sqlFrom, $sqlWhere="", $strDefaultOrderBy="", $strDefaultSortOrder="ASC", $iMaxRows=20, $openInExcel=false){
        $this->sqlFrom = $sqlFrom;
        $this->sqlWhere = $sqlWhere;
        $this->defaultOrderBy = $strDefaultOrderBy;
        $this->defaultSortOrder = $strDefaultSortOrder;
    }
}

?>