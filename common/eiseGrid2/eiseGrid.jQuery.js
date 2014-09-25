/********************************************************/
/*  
eiseGrid jQuery wrapper

requires jQuery UI 1.8: 
http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js


Published under GPL version 2 license
(c)2006-2014 Ilya S. Eliseev ie@e-ise.com, easyise@gmail.com

Contributors:
Pencho Belneiski
Dmitry Zakharov
Igor Zhuravlev

eiseGrid reference:
http://e-ise.com/eiseGrid/

*/
/********************************************************/
(function( $ ) {
var settings = {
    
};

function eiseGrid(gridDIV){
    this.id = gridDIV.attr('id');
    this.div = gridDIV;
    this.divBody = gridDIV.find('.eg_body');
    this.thead = gridDIV.find('table thead');
    this.trHead = this.thead.find('tr');
    this.tbody = gridDIV.find('.eg_body tbody');
    this.colgroupBody = gridDIV.find('.eg_body colgroup');
    this.trTemplate = this.tbody.find('tr.eg_template');
    this.trFirst = this.tbody.find('tr.eg_data').first();
    this.tfoot = gridDIV.find('table tfoot');
    
    this.conf = $.parseJSON(this.div.find('#inp_'+this.id+'_config').val());
    
    this.activeRow = [];
    this.lastClickedRowIx = null;
    
    var oGrid = this;
    
    oGrid.adjustColumnsWidth();

    this.tbody.find('tr').bind("click", function(event){ //row select binding
        oGrid.selectRow($(this), event);
    });
    

    this.tbody.find('tr').bind("dblclick", function(event){ //row select binding
        var id = oGrid.getRowID($(this));
        if(typeof(oGrid.dblclickCallback)!='undefined'){
            oGrid.dblclickCallback($(this), id, event);
        }
    });
    
    this.tbody.find('tr .eg_del').click(function(event){ //row delete binding
        oGrid.deleteRow($(this).parent('tr'));
        event.preventDefault(true);
    });
    
    $.each(this.conf.columns, function(col){
        oGrid.tbody.find("input[name='"+col+"[]']").bind('change', function(){ // input change bind to mark row updated
            oGrid.updateRow($(this).parents('tr').first()); 
        })
    });

    //this.tbody.find('input[type=text]').bind('change', function(){ // input change bind to mark row updated
    //    oGrid.updateRow($(this).parents('tr').first()); 
    //})
    
    this.tbody.find('.eg_editor').bind("blur", function(){ //bind contenteditable=true div save to hidden input
        if ($(this).prev('input').val()!=$(this).text()){
            oGrid.updateRow($(this).parents('tr').first()); 
        }
        $(this).prev('input').val($(this).text());
    });
    
    this.tbody.find('tr.eg_data').each(function(){ // attach datepicker only for visible rows
        oGrid.attachDatepicker(this);
        oGrid.attachAutocomplete(this);
    })
    
    $.each(this.conf.columns, function(field, props){ //bind totals recalculation to totals columns
        if (props.totals==undefined)
            return;
        oGrid.recalcTotals(field);
        oGrid.tbody.find('.'+oGrid.id+'_'+field+' input').bind('change', function(){
            oGrid.recalcTotals(field);
        })
    }) 
    
    this.tbody.find('.eg_checkbox input, .eg_boolean input').bind('change', function(){
        if(this.checked)
            $(this).prev('input').val('1');
        else 
            $(this).prev('input').val('0');
        oGrid.updateRow($(this).parents('tr').first()); 
    });
    
    this.tbody.find('.eg_combobox input, .eg_select input').bind('focus', function(){
        var oSelect = oGrid.tbody.find('#select_'+($(this).attr('name').replace(/_text\[\]/, ''))).clone();
        var oInp = $(this);
        var oInpValue = $(this).prev('input');
        $(this).parent('td').append(oSelect);
        
        oSelect.css('display', 'block');
        oSelect.offset({
            left: $(this).offset().left
//            - $(window).scrollLeft()
            , top: $(this).offset().top
//            - $(window).scrollTop()
            });
        
        oSelect.width($(this).width());
        
        oSelect.find('option').each(function(ix, option){
            if (option.value == $(oInpValue).val())
                oSelect[0].selectedIndex = ix;
        });
        
        oSelect.bind('change', function(){
            oInpValue.val($(this).val());
            oInp.val($(this)[0].options[$(this)[0].options.selectedIndex].text);
            oGrid.updateRow(oInpValue.parents('tr').first());
            oInp.change();
            oInpValue.change();
        });
         
        oSelect.bind('blur', function(){
            oInpValue.val($(this).val());
            oInp.val($(this)[0].options[$(this)[0].options.selectedIndex].text);
            $(this).css('display', 'none');
            $(this).remove();
        });
        
        oSelect.focus();
                
    });

    // control bar buttons
    this.div.find('.eg_button_add').bind('click', function(){
        oGrid.addRow(null);
    });
    this.div.find('.eg_button_edit').bind('click', function(){
        var selectedRow = oGrid.activeRow[oGrid.lastClickedRowIx];
        if (!selectedRow)
            return;
        var id = oGrid.getRowID(selectedRow);
        if(typeof(oGrid.dblclickCallback)!='undefined'){
            oGrid.dblclickCallback($(this), id, event);
        }
    });
    this.div.find('.eg_button_insert').bind('click', function(){
        oGrid.insertRow();
    });
    this.div.find('.eg_button_moveup').bind('click', function(){
        oGrid.moveUp();
    });
    this.div.find('.eg_button_movedown').bind('click', function(){
        oGrid.moveDown();
    });
    this.div.find('.eg_button_delete').bind('click', function(){
        oGrid.deleteSelectedRows();
            
    });
    this.div.find('.eg_button_save').bind('click', function(){
        oGrid.save();
    });

    if (this.tbody.find('tr.eg_data').length==0)
        this.tbody.find('.eg_tr_no_rows').css('display', 'table-row');

    //tabs 3d
    this.div.find('#'+this.id+'_tabs3d').each(function(){
        var selectedTab = document.cookie.replace(new RegExp("(?:(?:^|.*;\\s*)"+oGrid.conf.Tabs3DCookieName+"\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1");
        var selectedTabIx = 0;

        $(this).find('a').each(function(ix, obj){
            var tabID = $(obj).attr('href').replace('#'+oGrid.id+'_tabs3d_', '');
            if (ix==0 && selectedTab==''){
                selectedTab = tabID;
                return false; //break
            }
            if (tabID==selectedTab){
                selectedTabIx = ix;
                return false; //break
            }
        })
        
        $(this).tabs({
            selected: selectedTabIx
            , select: function(event, ui){
                var ID = ui.panel.id.replace(oGrid.id+'_tabs3d_', '');
                oGrid.sliceByTab3d(ID);
            }
        });

        oGrid.sliceByTab3d(selectedTab);
        

    });
    
    
}

eiseGrid.prototype.getFieldName = function ( oField ){
    var arrClasses = oField.attr("class").split(/\s+/);
    var colID = arrClasses[0].replace(this.id+"_", "");
    return colID;
}


eiseGrid.prototype.adjustColumnsWidth = function(){

    /* box-sizing CSS for any TD in list should be set to 'border-box'! */
    var oGrid = this;

    var wTbody = '1px';
    oGrid.divBody.css('min-width', wTbody).css('width', wTbody).css('max-width', wTbody);

    wTbody = oGrid.div.find('.eg_tdBody').outerWidth(false)+'px';
    oGrid.divBody.css('min-width', wTbody).css('width', wTbody).css('max-width', wTbody);

    // pass 0: reset all styles
    oGrid.colgroupBody.find('col').each(function(){
        var field = oGrid.getFieldName($(this));
        var $th = oGrid.trHead.find('th.'+oGrid.id+'_'+field);
        $th.css('min-width', '').css('max-width', '').css('width', '');
        $(this).css('min-width', '').css('max-width', '').css('width', '');
        oGrid.tbody.find('td.'+oGrid.id+'_'+field)
                    .css('overflow', '');
    });

    // pass 1: assign width to columns where width is directly specified
    // loop thru table body items
    oGrid.colgroupBody.find('col').each(function(){
        var field = oGrid.getFieldName($(this));
        var $th = oGrid.trHead.find('th.'+oGrid.id+'_'+field);
        var $colBody = $(this);
        if(typeof(oGrid.trFirst[0])!='undefined'){
            var $tdVisible = oGrid.trFirst.find('td.'+oGrid.id+'_'+field);
        }
        // if width is set by user, we apply it
        if( oGrid.conf.widths )
        if(typeof(oGrid.conf.widths[field])!='undefined'){
            var cssWidth = oGrid.conf.widths[field];
            $colBody.css('width', cssWidth);
            if(!(cssWidth.match(/\%$/))){
                $th.css('min-width', cssWidth).css('max-width', cssWidth);
                $colBody.css('min-width', cssWidth).css('max-width', cssWidth);
            } 
            
            // apply width to TH
            // if TH is not colspanned
            if (typeof($th.attr('colspan'))=='undefined') 
                $th.css('width', cssWidth);

        } else {// if width is not set
            // set maximum of header or body
            if(typeof($tdVisible)!='undefined' && typeof(oGrid.conf.spans[field]=='undefined') ) {
                var wH = $th.outerWidth();
                var wB = $tdVisible.outerWidth();
                var w = (wB<wH ? wH : wB);
                $th.css('min-width', w+"px").css('max-width', w+"px").css('width', w+"px");
                $colBody.css('min-width', w+"px").css('max-width', w+"px").css('width', w+"px");
            }
        }

    });
    
    if (typeof(oGrid.trFirst[0])!='undefined' && typeof($(oGrid.trFirst[0]).find('td.eg_no_rows')[0])=='undefined'){

        // if there's a scroll..
        if (this.checkHasScroll()){
            var $lastTH = this.thead.find('th:last-of-type');
            var lastColField = this.getFieldName($lastTH);
            var newWidth = $lastTH.outerWidth()+this.getScrollWidth();
            $lastTH.css('min-width', newWidth+"px").css('max-width', newWidth+"px").css('width', newWidth+"px")
        }

        // pass 2: search for colspanned header cells, align them according to body
        oGrid.trHead.find('th').each(function(){

            var field = oGrid.getFieldName($(this));

            if(typeof(oGrid.conf.spans[field])!='undefined'){

                var nSpan = parseInt(oGrid.conf.spans[field]);
                
                var $nextCell = oGrid.trFirst.find('td.'+oGrid.id+'_'+field);

                var nWidth = 0;
                for(var i=0;i<nSpan; i++){
                    nWidth += $nextCell.outerWidth();
                    var $nextCell = $nextCell.next();
                }
                $(this).css('min-width', nWidth+"px").css('max-width', nWidth+"px").css('width', nWidth+"px");
            }
        });

        // pass 3: align cells by header
        oGrid.trHead.find('th').each(function(){
            var field = oGrid.getFieldName($(this));
            var $th = $(this);
            var $tdVisible = oGrid.trFirst.find('td.'+oGrid.id+'_'+field);
            if(typeof(oGrid.conf.spans[field])=='undefined'){
                var wH = $th.outerWidth(true);
                var wB = wH-((oGrid.hasScroll && field==lastColField) ? oGrid.getScrollWidth() : 0);
                $th.css('min-width', wH+"px").css('max-width', wH+"px").css('width', wH+"px");
                oGrid.colgroupBody.find('col.'+oGrid.id+'_'+field)
                    .css('min-width', wB+"px").css('max-width', wB+"px").css('width', wB+"px")
                    .css('overflow', 'hidden');
            }
        });
        
    }

    return;

}


eiseGrid.prototype.getRowID = function(oTr){
    return oTr.find('td input').first().val();
}

eiseGrid.prototype.addRow = function(oTrAfter){
    
    this.tbody.find('.eg_tr_no_rows').css('display', 'none');
    
    var trTemplate = this.tbody.find('.eg_template');
    var newTr = trTemplate.clone(true, true)
        .css("display", "none")
        .removeClass('eg_template')
        .addClass('eg_data');
    newTr.find('.eg_floating_select').remove();
    if (oTrAfter==null) {  
        this.tbody.append(newTr);
    } else {
        oTrAfter.after(newTr);
    }
    newTr.slideDown();
    this.recalcOrder();
    
    this.selectRow(newTr);
    
    this.attachDatepicker(newTr);
    this.attachAutocomplete(newTr);
    
    //this.updateRow(newTr);
    
    var firstInput = $(newTr).find('input[type=text]').first()[0];
    if (typeof(firstInput)!='undefined')
        firstInput.focus();
    
    return newTr;
}

eiseGrid.prototype.insertRow = function(){
    var newTr = this.addRow(this.activeRow[this.lastClickedRowIx]);
}

eiseGrid.prototype.selectRow = function(oTr, event){

    var grid = this;
    
    if(event){
        if (event.shiftKey){

            var ixStart, ixEnd;
            if (grid.lastClickedRowIx){
                if(grid.lastClickedRowIx < oTr.index()){
                    ixStart = grid.lastClickedRowIx;
                    ixEnd = oTr.index();
                } else {
                    ixEnd = grid.lastClickedRowIx;
                    ixStart = oTr.index();
                } 
            }
            grid.activeRow = [];
            this.tbody.find('tr').each(function(){
                if ($(this).index()>=ixStart && $(this).index()<=ixEnd)
                    grid.activeRow[$(this).index()] = $(this);
            })
        } else if (event.ctrlKey || event.metaKey)  {
            if(!grid.activeRow[oTr.index()])
                grid.activeRow[oTr.index()] = oTr;
            else 
                grid.activeRow[oTr.index()] = null;
        } else {
            grid.activeRow = [];
            grid.activeRow[oTr.index()] = oTr;
        }
    } else {
        grid.activeRow = [];
        grid.activeRow[oTr.index()] = oTr;
    }


    grid.lastClickedRowIx = oTr.index();

    this.tbody.find('tr').each(function(){
        $(this).removeClass('eg_selected');
    })

    $.each(grid.activeRow, function(){
        $(this).addClass('eg_selected');
    })

}

eiseGrid.prototype.deleteRow = function(oTr, callback){
    var oThis = this;
    var goneID = this.getRowID(oTr);
    if (goneID!=""){
        var inpDel = this.div.find('#inp_'+this.id+'_deleted');
        inpDel.val(inpDel.val()+(inpDel.val()!="" ?  "|" : "")+goneID);
    }
    oTr.remove();
    delete oTr;
    this.recalcOrder();
    $.each(this.conf.columns, function(field, props){
        if (props.totals!=undefined) oThis.recalcTotals(field);
    });
    
    if (this.tbody.find('tr.eg_data').length==0)
        this.tbody.find('.eg_tr_no_rows').css('display', 'table-row');

    if (this.onDeleteCallback)
        this.onDeleteCallback(goneID);

    if(callback)
        callback(goneID);

}

eiseGrid.prototype.deleteSelectedRows = function(callback){
    var grid = this;
    var allowDelete = true;

    $.each(grid.activeRow, function(ix, $tr){
        if(!$tr)
            return true;

        if(callback){
            allowDelete = callback($tr);
        }
        if(allowDelete)
            grid.deleteRow($tr);
    });
}

eiseGrid.prototype.updateRow = function(oTr){
    
    oTr.find("input")[1].value="1";
    oTr.addClass('eg_updated');
}

eiseGrid.prototype.recalcOrder = function(){
    var oThis = this;
    var iCounter = 1;
    this.tbody.find('.eg_order').each(function (){
        if($(this).parent('tr.eg_template').html()==null){
            $(this).find('div span').html(iCounter).parent('div').prev('input').val(iCounter);
            iCounter++;
        }
    })
}

eiseGrid.prototype.moveUp = function(){

    var grid = this;

    $.each(grid.activeRow, function(ix, $rw){
        if ($rw){
            if ($rw.prev().hasClass('eg_template'))
                return false; // break, nothing to move, upper limit reached 
            $rw.insertBefore($rw.prev());
            grid.updateRow($rw);
            grid.updateRow($rw.next());
            
        }
    });

    this.recalcOrder();

}
eiseGrid.prototype.moveDown = function(){

    var grid = this;

    for(var i=grid.activeRow.length-1;i>=0;i--){
        var $rw = grid.activeRow[i];
        if ($rw){
            if ($rw.next().html()==null)
                return false; // break, nothing to move, upper limit reached 
            $rw.insertAfter($rw.next());
            grid.updateRow($rw);
            grid.updateRow($rw.prev());

        }
    }
    /*
    $.each(grid.activeRow, function(ix, $rw){
        if ($rw){
            if ($rw.next().html()==null)
                return false; // break, nothing to move, upper limit reached 
            $rw.insertAfter($rw.next());
            grid.updateRow($rw);
            grid.updateRow($rw.prev());

            console.log('qq')
            
        }
    });
*/

    this.recalcOrder();

}

eiseGrid.prototype.attachDatepicker = function(oTr){
    var grid = this;
    $(oTr).find('.eg_datetime input[type=text], .eg_date input[type=text]').each(function(){
        try {
            $(this).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: grid.conf.dateFormat.replace('d', 'dd').replace('m', 'mm').replace('Y', 'yy'),
                constrainInput: false,
                firstDay: 1
                , yearRange: 'c-7:c+7'
            });
        }catch(e) {alert('err')};
    });
}

eiseGrid.prototype.attachAutocomplete = function(oTr) {
    try {
      $(oTr).find(".eg_ajax_dropdown input[type=text]").each(function(){
        
        var inp = this;
        var $inpVal = $(inp).prev("input");
        
        if (typeof(jQuery.ui) != 'undefined') { // jQuery UI autocomplete conflicts with old-style BGIframe autocomplete
            $(this).autocomplete({
                source: function(request,response) {
                    
                    // reset old value
                    if(request.term.length<3){
                        response({});
                        $inpVal.val('');
                        $inpVal.change();
                        return;
                    }

                    var data = $(inp).attr('src');
                    eval ("var arrData="+data+";");
                    var table = arrData.table;
                    if (!table)
                        return;
                    var prefix = arrData.prefix;
                    var showDeleted = arrData.showDeleted;
                    var url = 'ajax_dropdownlist.php?table='+table+"&prefix="+prefix+
                        (showDeleted!=undefined ? "&showDeleted="+showDeleted : "");

                    var extra = $(inp).attr('extra');
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(extra!=undefined ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
                        response($.map(response_json.data, function(item) {
                                return {  label: item.optText, value: item.optValue  }
                            }));
                        });
                        
                    },
                minLength: 0,
                focus: function(event,ui) {
                    event.preventDefault();
                    if (ui.item){
                        $(inp).val(ui.item.label);
                    } 
                },
                select: function(event,ui) {
                    event.preventDefault();
                    if (ui.item){
                        $(inp).val(ui.item.label);
                        $inpVal.val(ui.item.value);
                        $inpVal.change();
                    } else 
                        $inpVal.val("");
                }
            });
        }
    });
    } catch (e) {}
}

function formatResult(row) {
        return row[0].replace(/(<.+?>)/gi, '');
    }

eiseGrid.prototype.recalcTotals = function(field){
    var oThis = this;
    var nTotals = 0.0;
    var nCount = 0;
    var nValue = 0.0;
    this.tbody.find('.'+this.id+'_'+field+' input').each(function(){
        var strVal = $(this).val()
            .replace(new RegExp("\\"+oThis.conf.decimalSeparator, "g"), '.')
            .replace(new RegExp("\\"+oThis.conf.thousandsSeparator, "g"), '');
        var nVal = parseFloat(strVal);
        if (!isNaN(nVal)) {
            nTotals += nVal;
            nCount++;
        }
    });
    switch(this.conf.columns[field].totals){
        case "avg":
            nValue = nTotals/nCount;
            break;
        case "sum":
        default:
            nValue = nTotals;
            break;
        
    }
    
    var decimalPlaces = 2;
    switch(this.conf.columns[field].type){
        case "int":
        case "integer":
            decimalPlaces = 0;
            break;
        default:
            decimalPlaces  = this.conf.columns[field].decimalPlaces!=undefined ? this.conf.columns[field].decimalPlaces : this.conf.decimalPlaces;
            break;
    }
    
    this.tfoot.find('.'+this.id+'_'+field+' div').html(
        //this.conf.columns[field].totals+' '+
        this.number_format(nValue, decimalPlaces)
    );
}

eiseGrid.prototype.number_format = function(arg, decimalPlaces){
/* adapted by Ilya Eliseev e-ise.com
 Made by Mathias Bynens <http://mathiasbynens.be/> */
    a = arg;
    b = decimalPlaces;
    c = this.conf.decimalSeparator;
    d = this.conf.thousandsSeparator;
    
    a = Math.round(a * Math.pow(10, b)) / Math.pow(10, b);
    
    
    e = a + '';
     f = e.split('.');
     if (!f[0]) {
      f[0] = '0';
     }
     if (!f[1]) {
      f[1] = '';
     }
     if (f[1].length < b) {
      g = f[1];
      for (i=f[1].length + 1; i <= b; i++) {
       g += '0';
      }
      f[1] = g;
     }
     if(d != '' && f[0].length > 3) {
      h = f[0];
      f[0] = '';
      for(j = 3; j < h.length; j+=3) {
       i = h.slice(h.length - j, h.length - j + 3);
       f[0] = d + i +  f[0] + '';
      }
      j = h.substr(0, (h.length % 3 == 0) ? 3 : (h.length % 3));
      f[0] = j + f[0];
     }
     c = (b <= 0) ? '' : c;
     return f[0] + c + f[1];
}

eiseGrid.prototype.change = function(strFields, fn){
    var fields = strFields.split(/[^a-z0-9\_]+/i);
    var oThis = this;
    var strSelector = ""; $.each(fields, function (ix, val){ strSelector+=(ix==0 ? "" : ", ")+"."+oThis.id+'_'+val+' input[name="'+val+'[]"]'});

    this.tbody.find(strSelector).bind("change", function(){
        var oTr = $(this).parents('tr').first();
        fn(oTr, $(this));
    });
}

eiseGrid.prototype.value = function(oTr, strFieldName, val, text){

    if (!this.conf.columns[strFieldName]){
        $.error( 'Field ' +  strFieldName + ' does not exist in eiseGrid ' + this.id );
    }
        
    
    var strType = this.conf.columns[strFieldName].type;
    var strTitle = this.conf.columns[strFieldName].title;
    
    if (val==undefined){
        var strValue = oTr.find('input[name='+strFieldName+'\\[\\]]').first().val();
        switch(strType){
            case "integer":
            case "int":
            case "numeric":
            case "real":
            case "double":
            case "money":
               strValue = strValue
                .replace(new RegExp("\\"+this.conf.decimalSeparator, "g"), '.')
                .replace(new RegExp("\\"+this.conf.thousandsSeparator, "g"), '');
                return parseFloat(strValue);
            default:
                return strValue;
        }
    } else {
        var strValue = val;
        switch(strType){
            case "integer":
            case "int": 
                strValue = this.number_format(strValue, 0);
                break;
            case "numeric":
            case "real":
            case "double":
            case "money":
                if(typeof(strValue)=='number'){
                    strValue = this.number_format(strValue, 
                        this.conf.columns[strFieldName].decimalPlaces!=undefined ? this.conf.columns[strFieldName].decimalPlaces : this.conf.decimalPlaces
                    )
                }
                break;
            default:
                break;
        }
        oInp = oTr.find('input[name='+strFieldName+'\\[\\]]').first();
        oInp.val(strValue);
        if (strTitle!='' && oInp.next()[0]!=undefined){
            switch(strType){
                case "checkbox":
                case "boolean":
                    if(strValue=="1"){
                        oInp.next().attr("checked", "checked");
                    } else 
                        oInp.next().removeAttr("checked");
                    return;
                default:
                    if (oInp.next()[0].tagName=="INPUT")
                        oInp.next().val((text!=undefined ? text : strValue));
                    else 
                        oInp.next().html((text!=undefined ? text : strValue));
            }
        }
        this.recalcTotals(strFieldName);
    }
}

eiseGrid.prototype.text = function(oTr, strFieldName, text){
    if(this.conf.columns[strFieldName].static !=undefined
        || this.conf.columns[strFieldName].disabled !=undefined
        || (this.conf.columns[strFieldName].href !=undefined && this.value(oTr, strFieldName)!="")
        ){
            return oTr.find('.'+this.id+'_'+strFieldName).text();
        } else {
            switch (this.conf.columns[strFieldName].type){
                case "order":
                case "textarea":
                    return oTr.find('.'+this.id+'_'+strFieldName).text();
                case "text":
                case "boolean":
                case "checkbox":
                    return this.value(oTr, strFieldName);
                case "combobox":
                case "select":
                case "ajax_dropdown":
                    return oTr.find('.'+this.id+'_'+strFieldName+' input[type=text]').val();
                default: 
                    return oTr.find('.'+this.id+'_'+strFieldName+' input').val();
            }
            
        }
}

eiseGrid.prototype.focus = function(oTr, strFieldName){
    oTr.find('.'+this.id+'_'+strFieldName+' input[type=text]').focus().select();
}

eiseGrid.prototype.verifyInput = function (oTr, strFieldName) {
    
    var strValue = oTr.find('.'+this.id+'_'+strFieldName+' input').first().val();
    if (strValue!=undefined){ //input mask compliance
        switch (this.conf.columns[strFieldName].type){
            case "money":
            case "numeric":
            case "real":
            case "float":
            case "double":
                var nValue = parseFloat(strValue
                    .replace(new RegExp("\\"+this.conf.decimalSeparator, "g"), '.')
                    .replace(new RegExp("\\"+this.conf.thousandsSeparator, "g"), ''));
                if (strValue!="" && isNaN(nValue)){
                    alert(this.conf.columns[strFieldName].title+" should be numeric");
                    this.focus(oTr, strFieldName);
                    return false;
                }
                break;
            case 'date':
            case 'time':
            case 'datetime':
                 
                var strRegExDate = this.conf.dateFormat
                    .replace(new RegExp('\\.', "g"), "\\.")
                    .replace(new RegExp("\\/", "g"), "\\/")
                    .replace("d", "[0-9]{1,2}")
                    .replace("m", "[0-9]{1,2}")
                    .replace("Y", "[0-9]{4}")
                    .replace("y", "[0-9]{1,2}");
                var strRegExTime = this.conf.timeFormat
                    .replace(new RegExp("\.", "g"), "\\.")
                    .replace(new RegExp("\:", "g"), "\\:")
                    .replace(new RegExp("\/", "g"), "\\/")
                    .replace("h", "[0-9]{1,2}")
                    .replace("i", "[0-9]{1,2}")
                    .replace("s", "[0-9]{1,2}");
                
                var strRegEx = "^"+(this.conf.columns[strFieldName].type.match(/date/) ? strRegExDate : "")+
                    (this.conf.columns[strFieldName].type=="datetime" ? " " : "")+
                    (this.conf.columns[strFieldName].type.match(/time/) ? strRegExTime : "")+"$";
                
                if (strValue!="" && strValue.match(new RegExp(strRegEx))==null){
                    alert ("Field '"+this.conf.columns[strFieldName].type+"' should contain date value formatted as "+this.conf.dateFormat+".");
                    this.focus(oTr, strFieldName);
                    return false;
                }
                break;
            default:
                 break;
         }
    }
    
    return true;
    
}

eiseGrid.prototype.verify = function(){
    
    var oGrid = this;
    var flagError = false;
    
    this.tbody.find('tr.eg_data').each(function(){ // y-iterations
        var oTr = $(this);
        $.each(oGrid.conf.columns, function(strFieldName, col){ // x-itearations
            
            if (col.static!=undefined || col.disabled!=undefined){ //skip readonly columns{
                return true; //continue
            }
                
                
            
            if (col.mandatory != undefined){ //mandatoriness
                if (oGrid.value(oTr, strFieldName)==""){
                    alert("Field "+col.title+" is mandatory");
                    oGrid.focus(oTr, strFieldName);
                    flagError = true;
                    return false; //break
                }
            }
            
            if (!oGrid.verifyInput(oTr, strFieldName)){
                flagError = true;
                return false; //break
            }
                
        }) 
        if(flagError)
            return false;
    })
    
    return !flagError;

}

eiseGrid.prototype.save = function(){
    
    if (!this.verify())
        return false;

    this.div.wrap('<form action="'+this.conf.urlToSubmit+'" id="form_eg_'+this.id+'" method="POST" />');
    var oForm = $('#form_eg_'+this.id);
    $.each(this.conf.extraInputs, function(name, value){
        oForm.append('<input type="hidden" name="'+name+'" value="'+value+'">');
    });
    oForm.find('#inp_'+this.id+'_config').remove();
    oForm.submit();
}


eiseGrid.prototype.sliceByTab3d = function(ID){
    document.cookie = this.conf.Tabs3DCookieName+'='+ID;
    //eg_3d eg_3d_20DC
    this.tbody.find('td .eg_3d').css('display', 'none');
    this.tbody.find('td .eg_3d_'+ID).css('display', 'block');
}

eiseGrid.prototype.height = function(nHeight){
    
    var hBefore = this.div.outerHeight();

    if (typeof(nHeight)!='undefined'){
        var nGridExtraz = this.div.outerHeight()-this.divBody.height();
        this.divBody
            .css('overflow-y', 'auto')
            .css('max-height', (nHeight-nGridExtraz)+'px');

        if(this.checkHasScroll()){
            this.adjustColumnsWidth();
        }    
    }
    
    return hBefore;

}

eiseGrid.prototype.checkHasScroll = function(){
    this.hasScroll = (this.tbody.outerHeight(true)>this.divBody.outerHeight())
    return this.hasScroll;
}

eiseGrid.prototype.getScrollWidth = function(){
    
    if (this.systemScrollWidth==null){
    
        var $inner = jQuery('<div style="width: 100%; height:200px;">test</div>'),
            $outer = jQuery('<div style="width:200px;height:150px; position: absolute; top: 0; left: 0; visibility: hidden; overflow:hidden;"></div>').append($inner),
            inner = $inner[0],
            outer = $outer[0];
         
        jQuery('body').append(outer);
        var width1 = inner.offsetWidth;
        $outer.css('overflow', 'scroll');
        var width2 = outer.clientWidth;
        $outer.remove();
    
        this.systemScrollWidth = (width1 - width2);
    } 
    return   this.systemScrollWidth;
}

eiseGrid.prototype.reset = function(fn){
    
    var oGrid = this;

    this.tbody.find('tr.eg_data').each(function(){ // delete visible rows
        oGrid.deleteRow($(this));
    });
    this.tbody.find('.eg_tr_no_rows').css('display', 'table-row');

    if (typeof(fn)!='undefined'){
        fn();
    }
}

eiseGrid.prototype.fill = function(data, fn){
    
    var oGrid = this;

    $.each(data, function(ix, row){
        var $tr = oGrid.addRow();
        $.each(oGrid.conf.columns, function(field, props){
            
            if (typeof(row[field])=='undefined')
                return true; // continue

            var val = (typeof(row[field])=='object'
                    ? row[field].v
                    : row[field]);
            var text = (typeof(row[field].t)!='undefined'
                    ? row[field].t
                    : val);
            var href = (typeof(row[field])=='object'
                    ? row[field].h
                    : row[field]);

            var $td = $tr.find('td.'+oGrid.id+'_'+field);
            var $inp = $tr.find('input[name="'+field+'[]"]');
            $inp.val(val);

            if ($td.hasClass("eg_disabled")){
                $inp.val(val);
                $td.find('div').first().text(text);
            } else {
                switch(props['type']){
                    case 'combobox':
                    case 'ajax_dropdown':                        
                        $td.find('input[type=text]').first().text(text);
                        break;
                    case 'boolean':
                        $td.find('input[type=checkbox]')[0].checked = true;
                        break;
                    default:
                        break;
                }
            }

        });
    });

    $.each(oGrid.conf.columns, function(field, props){
        if (props.totals!=undefined) oThis.recalcTotals(field);
    });

    oGrid.trFirst = oGrid.tbody.find('tr.eg_data').first();
    oGrid.adjustColumnsWidth();

}

var methods = {
init: function( conf ) {

    this.each(function() {
        var data, dataId, conf_,
                $this = $(this);

        $this.eiseGrid('conf', conf);
        data = $this.data('eiseGrid') || {};
        conf_ = data.conf;

        // If the plugin hasn't been initialized yet
        if ( !data.eiseGrid ) {
            dataId = +new Date;

            data = {
                eiseGrid_data: true
                , conf: conf_
                , id: dataId
                , eiseGrid : new eiseGrid($this)
            };

            
            // create element and append to body
            var $eiseGrid_data = $('<div />', {
                'class': 'eiseGrid_data'
            }).appendTo( 'body' );

            // Associate created element with invoking element
            $eiseGrid_data.data( 'eiseGrid', {target: $this, id: dataId} );
            // And vice versa
            data.eiseGrid_data = $eiseGrid_data;

            $this.data('eiseGrid', data);
        } // !data.eiseGrid

        

    });

    return this;
},
destroy: function( ) {

    this.each(function() {

        var $this = $(this),
                data = $this.data( 'eiseGrid' );

        // Remove created elements, unbind namespaced events, and remove data
        $(document).unbind( '.eiseGrid_data' );
        data.eiseGrid.remove();
        $this.unbind( '.eiseGrid_data' )
        .removeData( 'eiseGrid_data' );

    });

    return this;
},
conf: function( conf ) {

    this.each(function() {
        var $this = $(this),
            data = $this.data( 'eiseGrid' ) || {},
            conf_ = data.conf || {};

        // deep extend (merge) default settings, per-call conf, and conf set with:
        // html10 data-eiseGrid conf JSON and $('selector').eiseGrid( 'conf', {} );
        conf_ = $.extend( true, {}, $.fn.eiseGrid.defaults, conf_, conf || {} );
        data.conf = conf_;
        $.data( this, 'eiseGrid', data );
    });

    return this;
},
addRow: function ($trAfter){
    //Adds a row after specified trAfter row. If not set, adds a row to the end of the grid.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.addRow($trAfter);

    });
    return this;

}, 
selectRow: function ($tr, event){
    //Selects a row specified by tr parameter.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.selectRow($tr, event);

    });
    return this;
}, 
getSelectedRow: function (){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var $lastSelectedRow = grid.activeRow[grid.lastClickedRowIx];
    return $lastSelectedRow;
}, 
getSelectedRows: function (){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.activeRow;
}, 
getSelectedRowID: function ($tr){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var $lastSelectedRow = grid.activeRow[grid.lastClickedRowIx];
    if(!$lastSelectedRow)
        return null;
    else 
        return grid.getRowID($lastSelectedRow);
},
getSelectedRowIDs: function ($tr){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var arrRet = [];

    $.each(grid.activeRow, function(ix, $tr){
        arrRet[ix] = grid.getRowID($tr);
    });

    return arrRet; 
},

deleteRow: function ($tr, callback){
    //Removes a row specified by tr parameter. If not set, removes selected row
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.deleteRow($tr, callback);

    });
    return this;
},

deleteSelectedRows: function(callback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.deleteSelectedRows(callback);
},

updateRow: function ($tr){
    //It marks specified row as updated
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.updateRow($tr);

    });
    return this;

}, 
recalcOrder: function(){
    //recalculates row order since last changed row
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.recalcOrder();

    });
    return this;
},

moveUp: function(){
    //Moves selected row up by 1 step, if possible
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.moveUp();

    });
    return this;
},

moveDown: function(){
    //Moves selected row down by 1 step, if possible
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.moveDown();

    });
    return this;
},

sliceByTab3d: function(ID){ 
    //brings data that correspond to tab ID to the front
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.sliceByTab3d(ID);
    });
    return this;
},

recalcTotals: function (strField){
    //Recalculates totals for given field.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.recalcTotals(strField);

    });
    return this;
},

change:  function(strFields, callback){
    //Assigns “change” event callback for fields enlisted in strFields parameter.
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;

        grid.change(strFields, callback);

    });
    return this;
},

value: function ($tr, strField, value, text){
    //Sets or gets value for field strField in specified row, if there’s a complex field 
    //(combobox, ajax_dropdown), it can also set text representation of data.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.value($tr, strField, value, text);
},

text: function($tr, strField, text) {
    //Returns text representation of data for field strField in specified row tr.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.text($tr, strField, text);
},

focus: function($tr, strField){
    //Sets focus to field strField in specified row tr.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.focus($tr, strField);
    return this;
},

validateInput: function ($tr, strField){
    //Validates data for field strField in row tr. Returns true if valid.
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;

        grid.verifyInput($tr, strField);

    });
    return this;
},

validate: function(){
    //Validates entire contents of eiseGrids matching selectors. Returns true if all data in all grids is valid
    var flagOK = true;
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;
        flagOK = flagOK && grid.verify();

    });

    return flagOK;
},

save: function(){
    //Wraps whole grid with FORM tag and submits it to script specified in settings.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.save();
    return this;
},

height: function(nHeight){
    //Wraps whole grid with FORM tag and submits it to script specified in settings.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.height(nHeight);
},

dblclick: function(dblclickCallback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.dblclickCallback = dblclickCallback;
    return this;
},

_delete: function(onDeleteCallback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.onDeleteCallback = onDeleteCallback;
    return this;
},

getGridObject: function(){
    return $(this[0]).data('eiseGrid').eiseGrid;
},

reset: function(fn){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.reset(fn);
    return this;
},

fill: function(data, fn){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.fill(data, fn);
    return this;
}

};



var protoSlice = Array.prototype.slice;

$.fn.eiseGrid = function( method ) {

    if (method=='delete') method = '_delete';

    if ( methods[method] ) {
        return methods[method].apply( this, protoSlice.call( arguments, 1 ) );
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' does not exist on jQuery.fn.eiseGrid' );
    }

};

$.extend($.fn.eiseGrid, {
    defaults: settings
});

})( jQuery );
