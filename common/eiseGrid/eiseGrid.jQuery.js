/********************************************************/
/*  
eiseGrid jQuery wrapper

requires jQuery UI 1.8: 
http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js


Published under GPL version 2 license
(c)2006-2013 Ilya S. Eliseev ie@e-ise.com, easyise@gmail.com

Contributors:
Pencho Belneiski
Dmitry Zakharov
Igor Zhuravlev

eiseGrid feference:
http://e-ise.com/eiseGrid/

*/
/********************************************************/
(function( $ ) {
var settings = {
    
};

function eiseGrid(gridDIV){
    this.id = gridDIV.attr('id');
    this.div = gridDIV;
    this.tbody = gridDIV.find('table tbody');
    this.tfoot = gridDIV.find('table tfoot');
    
    this.conf = $.parseJSON(this.div.find('#inp_'+this.id+'_config').val());
    
    this.activeRow = null;
    
    var oThis = this;
    
    this.tbody.find('tr').bind("click", function(){ //row select binding
        oThis.selectRow($(this));
    });
    
    
    this.tbody.find('tr .eg_del').click(function(event){ //row delete binding
        oThis.deleteRow($(this).parent('tr'));
        event.preventDefault(true);
    });
    
    this.tbody.find('input[type=text]').bind('change', function(){ // input change bind to mark row updated
        oThis.updateRow($(this).parents('tr').first()); 
    })
    
    this.tbody.find('.eg_editor').bind("blur", function(){ //bind contenteditable=true div save to hidden input
        if ($(this).prev('input').val()!=$(this).text()){
            oThis.updateRow($(this).parents('tr').first()); 
        }
        $(this).prev('input').val($(this).text());
    });
    
    this.tbody.find('tr:not(.eg_template)').each(function(){ // attach datepicker only for visible rows
        oThis.attachDatepicker(this);
        oThis.attachAutocomplete(this);
    })
    
    $.each(this.conf.columns, function(field, props){ //bind totals recalculation to totals columns
        if (props.totals==undefined)
            return;
        oThis.recalcTotals(field);
        oThis.tbody.find('.'+oThis.id+'_'+field+' input').bind('change', function(){
            oThis.recalcTotals(field);
        })
    }) 
    
    this.tbody.find('.eg_checkbox input, .eg_boolean input').bind('change', function(){
        if(this.checked)
            $(this).prev('input').val('1');
        else 
            $(this).prev('input').val('0');
        oThis.updateRow($(this).parents('tr').first()); 
    });
    
    this.tbody.find('.eg_combobox input, .eg_select input').bind('focus', function(){
        var oSelect = oThis.tbody.find('#select_'+($(this).attr('name').replace(/_text\[\]/, ''))).clone();
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
            oThis.updateRow(oInpValue.parents('tr').first());
            oInp.change();
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
        oThis.addRow(null);
    });
    this.div.find('.eg_button_insert').bind('click', function(){
        oThis.insertRow();
    });
    this.div.find('.eg_button_moveup').bind('click', function(){
        oThis.moveUp();
    });
    this.div.find('.eg_button_movedown').bind('click', function(){
        oThis.moveDown();
    });
    this.div.find('.eg_button_delete').bind('click', function(){
        if (oThis.activeRow!=null)
            oThis.deleteRow(oThis.activeRow);
    });
    this.div.find('.eg_button_save').bind('click', function(){
        oThis.save();
    });

    //tabs 3d
    this.div.find('#'+this.id+'_tabs3d').each(function(){
        var selectedTab = document.cookie.replace(new RegExp("(?:(?:^|.*;\\s*)"+oThis.conf.Tabs3DCookieName+"\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1");
        var selectedTabIx = 0;

        $(this).find('a').each(function(ix, obj){
            var tabID = $(obj).attr('href').replace('#'+oThis.id+'_tabs3d_', '');
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
                var ID = ui.panel.id.replace(oThis.id+'_tabs3d_', '');
                oThis.sliceByTab3d(ID);
            }
        });

        oThis.sliceByTab3d(selectedTab);
        

    });
    
    
}

eiseGrid.prototype.addRow = function(oTrAfter){
    
    this.tbody.find('.eg_no_rows').parent().remove();
    
    var trTemplate = this.tbody.find('.eg_template');
    var newTr = trTemplate.clone(true, true)
        .css("display", "none")
        .removeClass('eg_template');
    newTr.find('.eg_floating_select ').remove();
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
    
    this.updateRow(newTr);
    
    $(newTr).find('input[type=text]').first()[0].focus();
    
    return newTr;
}

eiseGrid.prototype.insertRow = function(){
    var newTr = this.addRow(this.activeRow);
}

eiseGrid.prototype.selectRow = function(oTr){
    this.tbody.find('tr').each(function(){
        $(this).removeClass('eg_selected');
    })
    oTr.addClass('eg_selected');
    this.activeRow = oTr;
}


eiseGrid.prototype.deleteRow = function(oTr){
    var oThis = this;
    var goneID = oTr.find('td input').first().val();
    if (goneID!=""){
        var inpDel = this.div.find('#inp_'+this.id+'_deleted');
        inpDel.val(inpDel.val()+(inpDel.val()!="" ?  "|" : "")+goneID);
    }
    oTr.remove();
    this.recalcOrder();
    $.each(this.conf.columns, function(field, props){
        if (props.totals!=undefined) oThis.recalcTotals(field);
    }) 
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
    if (this.activeRow!=null){
        if ($(this.activeRow).prev().hasClass('eg_template'))
            return; 
        $(this.activeRow).insertBefore($(this.activeRow).prev());
        this.updateRow(this.activeRow);
        this.updateRow($(this.activeRow).next());
        this.recalcOrder();
    }
}
eiseGrid.prototype.moveDown = function(){
    if (this.activeRow!=null){
        if(this.activeRow.next().html()==null)
            return; 
            
        $(this.activeRow).insertAfter($(this.activeRow).next());
        this.updateRow(this.activeRow);
        this.updateRow($(this.activeRow).prev());
        this.recalcOrder();
    }
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
        
        var data = $(this).attr('src');
        eval ("var arrData="+data+";");
        var table = arrData.table;
        var prefix = arrData.prefix;
        var showDeleted = arrData.showDeleted;
        var url = 'ajax_dropdownlist.php?table='+table+"&prefix="+prefix+
            (showDeleted!=undefined ? "&showDeleted="+showDeleted : "");
        
        if (typeof(jQuery.ui) != 'undefined') { // jQuery UI autocomplete conflicts with old-style BGIframe autocomplete
            $(this).autocomplete({
                source: function(request,response) {
                    
                    var extra = $(inp).attr('extra');
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(extra!=undefined ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
                        response($.map(response_json.data, function(item) {
                                return {  label: item.optText, value: item.optValue  }
                            }));
                        });
                        
                    },
                minLength: 3,
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
                        $(inp).prev("input").val(ui.item.value);
                    } else 
                        $(inp).prev("input").val("");
                }
            });
        } else { //...old-style BGIframe autocomplete
            $(this).autocomplete(url, {
                width: 300,
                multiple: false,
                matchContains: true,
                minChars: 3,
                dataType: 'json',
                //formatResult: function(row) {return row[0].replace(/(<.+?>)/gi, '');},
                parse: function(data) {
                    var parsed = [];
                    arrParse = data.data;
                    if (arrParse===null) {
                           arrParse = [];
                    }
                    for (var i = 0; i < arrParse.length; i++) {
                        parsed[parsed.length] = {
                                data: arrParse[i],
                                value: arrParse[i].optText,
                               result: arrParse[i].optText
                        };
                    }
                   return parsed;
                },
                formatItem: function(item) { return item.optText; }
            });
        }
        
        
        $(this).result(function(event, data, formatted) {
            if (data){
                $(this).prev("input").val(data.optValue);
            }
        });
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
    var fields = strFields.split(/[^a-z0-9\_]/i);
    var oThis = this;
    var strSelector = ""; $.each(fields, function (ix, val){ strSelector+=(ix==0 ? "" : ", ")+"."+oThis.id+'_'+val+' input[type=text]'});

    this.tbody.find(strSelector).bind("change", function(){
        var oTr = $(this).parents('tr').first();
        fn(oTr, $(this));
    });
}

eiseGrid.prototype.value = function(oTr, strFieldName, val, text){
    
    var strType = this.conf.columns[strFieldName].type;
    
    if (val==undefined){
        var strValue = oTr.find('.'+this.id+'_'+strFieldName+' input[name='+strFieldName+'\\[\\]]').first().val();
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
                strValue = this.number_format(strValue, 
                    this.conf.columns[strFieldName].decimalPlaces!=undefined ? this.conf.columns[strFieldName].decimalPlaces : this.conf.decimalPlaces
                );
                break;
            default:
                break;
        }
        oInp = oTr.find('.'+this.id+'_'+strFieldName+' input').first();
        oInp.val(strValue);
        if (oInp.next()[0]!=undefined){
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
    
    this.tbody.find('tr:not(.eg_template)').each(function(){ // y-iterations
        var oTr = $(this);
        $.each(oGrid.conf.columns, function(strFieldName, col){ // x-itearations
            
            if (col.static!=undefined || col.disabled!=undefined) //skip readonly columns
                return true; //continue
                
            
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
selectRow: function ($tr){
    //Selects a row specified by tr parameter.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.selectRow($tr);

    });
    return this;
},

deleteRow: function ($tr){
    //Removes a row specified by tr parameter. If not set, removes selected row
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.deleteRow($tr);

    });
    return this;
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
        grid.recalcTotals(strFields);

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
}

};

var protoSlice = Array.prototype.slice;

$.fn.eiseGrid = function( method ) {

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
