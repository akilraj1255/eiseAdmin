var eiseLists = [];

function eiseList(divEiseList){
    
    var list = this;
    
    list.id = divEiseList.attr('id');
    
    list.div = divEiseList;
    list.form = divEiseList.find('form');
    list.header = list.div.find('.el_header');
    list.divTable = list.div.find('.el_table');
    list.thead = list.div.find('.el_thead');
    list.body = list.div.find('.el_body');
    list.tbody = list.body.find('table tbody');
    list.tfoot = list.div.find('.el_tfoot');
    list.divFieldChooser = list.div.find('.el_fieldChooser');
    
    list.scrollBarHeight = null;
    
    list.activeRow = null;
    
    list.conf = $.parseJSON(list.div.find('#inp_'+list.id+'_config').val());
    
    list.activeRow = null;
    
    list.nTotalRows = 0;
    list.currentOffset = 0;
    
    list.uploadInProgress = false;
    
    
    
    // adjust contents div height
    list.adjustHeight();
    
    //attach onResize event handling
    $(window).resize(function(){
        list.adjustHeight();
    });
    
    // attach scroll event
    var oldScroll =0;
    var newScroll = 0;
    this.body.scroll(function(){
        newScroll = $(this).scrollTop();
        if (newScroll > oldScroll)
            list.handleScroll();
        oldScroll = newScroll;
    });
    
    // aquire data
     this.getData(0,null,true);
    
    
    this.tbody.find('tr').bind("click", function(){ //row select binding
        list.selectRow($(this));
    });
    
    this.thead.find('.el_sortable').click(function(){
        list.sort($(this));
    })
    
    this.thead.find('select.el_filter').change(function(){
        list.form.submit();
    })
    
    this.form.submit(function(){
        if (list.conf.doNotSubmitForm==true){
            list.refreshList();
            return false;
        }
    })
        
        
    this.div.find('#btnFieldChooser').click(function (){
        list.fieldChooser();
    });
    
    this.div.find('#btnOpenInExcel').click(function (){
        list.openInExcel();
    });
    
    this.div.find('#btnReset').click(function (){
        list.reset();
    });
    
    this.div.find('#sel_'+list.id+'_all').click(function (){
        list.toggleRowSelection();
    });
}

eiseList.prototype.adjustHeight = function(){
    
    // calculate bottom margin
    var elParent = this.div;
    var bottomOffset = 0;
    while( elParent[0].nodeName!="BODY" ){
        var padding = parseInt(elParent.css("padding-bottom"));padding=isNaN(padding) ? 0 : padding;
        var border = parseInt(elParent.css("border-bottom"));border = isNaN(border) ? 0 : border;
        var margin = parseInt(elParent.css("margin-bottom")); margin = isNaN(margin) ? 0 : margin;
        bottomOffset = bottomOffset + padding+border+margin;
        elParent = elParent.parent();
    }
    
    // calculate offset left/top for eiseList div    
    var offset = this.div.offset();
    
    //var scrollWidth = ($.browser.msie ? this.getScrollWidth() : 0);
    var scrollWidth = 0;
    
    var listMargins = this.div.parent().outerHeight(true) - this.div.parent().height(); // list margins
    
    var listHeight = $(window).height() - offset.top - bottomOffset - scrollWidth; //new list height
    this.div.height(listHeight);
    
    if ($.browser.msie){
        this.div.parent().css("overflow", "hidden");
    }
    
    var headerHeight = this.header.outerHeight(true);
    var theadHeight = this.thead.outerHeight(true);
    var tfootHeight = this.tfoot.outerHeight(true);
    
    this.scrollBarHeight = (this.scrollBarHeight==null 
        ? (this.thead.outerWidth(true) > this.div.outerWidth() 
            ? this.getScrollWidth()
            : 0)
        : this.scrollBarHeight);
    
    this.bodyHeight = listHeight-(headerHeight + theadHeight + tfootHeight + this.scrollBarHeight);
    this.body.css('height', this.bodyHeight+'px');
      
}

eiseList.prototype.getScrollWidth = function(){
    
    if (this.scrollBarHeight==null){
    
        var $inner = jQuery('<div style="width: 100%; height:200px;">test</div>'),
            $outer = jQuery('<div style="width:200px;height:150px; position: absolute; top: 0; left: 0; visibility: hidden; overflow:hidden;"></div>').append($inner),
            inner = $inner[0],
            outer = $outer[0];
         
        jQuery('body').append(outer);
        var width1 = inner.offsetWidth;
        $outer.css('overflow', 'scroll');
        var width2 = outer.clientWidth;
        $outer.remove();
    
        this.scrollBarHeight = (width1 - width2);
    } 
    return   this.scrollBarHeight;
}

eiseList.prototype.getQueryString = function(){
    
    var strARG = "";
    
    this.form.find('input, select').each(function(){
        if($(this).val()!=undefined 
            && $(this).val()!="" 
            && $(this).attr('name')!='DataAction' 
            && $(this).attr('type')!='button'
            && $(this).attr('type')!='submit'
            && $(this).attr('type')!='checkbox'
        ){
            strARG += '&'+$(this).attr("name")+'='+encodeURIComponent($(this).val());
            $(this).parent().addClass('el_filterset');
        }
        if ($(this).val()=="" && $(this).parent().hasClass('el_filterset')){
            strARG += '&'+$(this).attr("name")+'=';
            $(this).parent().removeClass('el_filterset');
        }
        
    });
    return strARG;
}

eiseList.prototype.getData = function(iOffset, recordCount, flagResetCache, callback){
    
    if (this.uploadInProgress)
        return;
    
    //collect filter data and compose GET query
    var ajaxURL = this.conf['dataSource'];
    
    this.currentOffset = iOffset;
    
    this.body.find('.el_spinner')
        .css("display", "block")
        .css("width", this.div.outerWidth() - (this.body.find('.el_spinner').outerWidth()-this.body.find('.el_spinner').width()));
    if (iOffset > 0){
        this.body.find('.el_spinner').css("margin-top", "10px");
    }
    
    var strARG = "";
    if (this.conf.cacheSQL!=true || flagResetCache==true){
        
        strARG = this.getQueryString();
    
    }
    
    strARG = "DataAction=json&offset="+iOffset+(recordCount!=undefined ? "&recordCount="+recordCount : "")+
        (flagResetCache==true ? "&noCache=1" : "")+
        strARG;
    var strURL = ajaxURL+'?'+strARG;
    //this.debug(strARG);
    
    //if (iOffset>0) return;
    
    this.uploadInProgress = true; //only one ajax-request at a time
    
    var list = this;
    $.ajax({ url: strURL
        , success: function(data, text){
            
            if (data.error!=undefined){
                alert (list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+data.error+'\r\n'+strARG);
                list.body.find('.el_spinner').css("display", "none");
                return;
            }
            
            //display count information
            if (iOffset==0 && list.conf.calcFoundRows==true){
                list.nTotalRows = data.nTotalRows;
                list.header.find('.el_span_foundRows').text(data.nTotalRows);
                list.header.find('.el_foundRows').css("display", "inline-block");
            }
            
            //append found rows
            for(var i=0;i<data.rows.length;i++){
                list.appendRow(i, data.rows[i]);
                list.currentOffset += 1;
            }
            
            // adjust column width
            list.body.find('.el_spinner').css("display", "none");
            if (iOffset==0){
                list.body.scrollTop(0);
            }
            list.adjustColumnWidth();
            
            list.adjustHeight();
            
            list.uploadInProgress = false;
            
            if (callback!=undefined){
                callback();
            }
            
        }
        , error: function(o, error, errorThrown){
            alert (list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+errorThrown+'\r\n'+strARG);
            list.body.find('.el_spinner').css("display", "none");
            list.uploadInProgress = false;
        }
        , dataType: "json"
        
    });
    
}


eiseList.prototype.openInExcel = function(){
    
    var strARG = this.getQueryString();
    
    strARG = "DataAction=excelXML&offset=0&noCache=1"+strARG;
    var strURL = this.conf['dataSource']+'?'+strARG;
    
    window.open(strURL, "_blank");
     
}


eiseList.prototype.refreshList = function(){
    
    this.tbody.children('tr:not(.el_template)').remove();
    this.getData(0,null,true);
    
}

eiseList.prototype.appendRow = function (index, rw){
    
    var list = this;
    
    //clone template row
    var tr = this.tbody.find('.el_template').clone(true);
    
    tr.find('td').each(function(){
        var fieldName = $(this).attr('class')
            .split(/\s+/)[0]
            .replace(list.id+'_','');
        var text = rw.r[fieldName].t;
        
        if (rw.r[fieldName].c!=null  && rw.r[fieldName].c!="")
            $(this).addClass(rw.r[fieldName].c);
        
        if ($(this).hasClass('el_checkbox')){
            
            $(this).find('input')
                .attr("id", $(this).find('input').attr("id")+rw.PK)
                .val(rw.PK);
            
        } else {
        
            if (rw.r[fieldName].v!=null && rw.r[fieldName].v!="")
                $(this).attr("value", rw.r[fieldName].v);
            
            if ($(this).hasClass('el_boolean') && text=="1"){
                $(this).addClass('el_boolean_s');
            }
            
            var html = (rw.r[fieldName].h!=null && rw.r[fieldName].h!=""
                ? '<a href="'+rw.r[fieldName].h+'"'+($(this).attr('target')!=undefined ? ' target="'+$(this).attr('target')+'"' : '')+'>'+
                    text+'</a>'
                : text);
            
            $(this).html(html!=null && html!=undefined ? html : '');
        }
    });
    
    tr.removeClass('el_template');
    tr.addClass('el_tr'+index%2);
    list.tbody.append(tr);
    
}

eiseList.prototype.adjustColumnWidth = function(){
    
    var list = this;
    
    var trHead = this.thead.find('tr').last();
    var trBody = this.tbody.children('tr:not(.el_template)').first();
    
    
    
    // iteration 1: make thead elements wider if wH < wB
    trHead.find('td').each(function(){
        
        var tdHead = $(this);
        var wH = tdHead.innerWidth();
        var tdBody = $(trBody.find('.'+$(this).attr('class').split(/\s+/)[0]).first());
        var wB = tdBody.innerWidth();
        
        if (wH < wB){
            var tdHeadW = wB-(wH-Math.max(tdHead.width(),0));
            //list.debug(' body1A = '+$("body")[0].scrollHeight+'\r\n');
            tdHead.css('min-width', tdHeadW+"px").css('max-width', tdHeadW+"px").css('width', tdHeadW+"px");
            //list.debug(' body1B = '+$("body")[0].scrollHeight+'\r\n');
        }
        
    })
    
    
    //iteration 2: make tbody elements equal to th
    trHead.find('td').each(function(){
        
        var tdHead = $(this);
        var wH = tdHead.innerWidth();
        var tdBody = $(trBody.find('.'+$(this).attr('class').split(/\s+/)[0]).first());
        var wB = tdBody.innerWidth();
        
        var tdBodyW = wH-(wB-Math.max(tdBody.width(),0));
        tdBody.css('min-width', tdBodyW+"px").css('max-width', tdBodyW+"px").css('width', tdBodyW+"px");
        //list.debug(' body2 = '+$("body")[0].scrollHeight+'\r\n');
    })
    
}

eiseList.prototype.handleScroll = function(){
    
    var list = this;
    
    var cellHeight = this.tbody.children('tr:not(.el_template)').first().outerHeight(true);
    var windowHeight = this.body.height();
    
    var nCells = Math.ceil(windowHeight/cellHeight);
    
    if (list.body[0].scrollHeight - windowHeight <= list.body.scrollTop()){
        //list.debug(list.body[0].scrollHeight+' '+list.body.scrollTop());
        //list.debug(nCells);
        list.getData(list.currentOffset, nCells);
    }
    
}

eiseList.prototype.selectRow = function(oTr){
    
    if (this.activeRow!=null) {
        this.activeRow.removeClass('el_selected');
    }
    oTr.addClass('el_selected');
    this.activeRow = oTr;
}


eiseList.prototype.getFieldName = function ( oField ){
    var arrClasses = oField.attr("class").split(/\s+/);
    var colID = arrClasses[0].replace(this.id+"_", "");
    return colID;
}

eiseList.prototype.sort = function(oTHClicked){

    var colID = this.getFieldName(oTHClicked);
    
    var classToAdd = "";
    
    this.form.find("#"+this.id+"OB").val(colID);
    if (oTHClicked.hasClass('el_sorted_asc')){
        this.form.find("#"+this.id+"ASC_DESC").val("DESC");
        oTHClicked.removeClass('el_sorted_asc');
         classToAdd = 'el_sorted_desc';
    } else if(oTHClicked.hasClass('el_sorted_desc')){
        this.form.find("#"+this.id+"ASC_DESC").val("ASC");
        oTHClicked.removeClass('el_sorted_desc');
         classToAdd = 'el_sorted_asc';
    } else {
        this.form.find("#"+this.id+"ASC_DESC").val("ASC");
         classToAdd = 'el_sorted_asc';
    }
    
    
    this.thead.find("th").each(function(){
        $(this).removeClass('el_sorted_asc');
        $(this).removeClass('el_sorted_desc');
    })
    oTHClicked.addClass(classToAdd);
    
    this.form.submit();
    
    
}

eiseList.prototype.fieldChooser = function(){
    
    var oList = this;
    
    $(this.divFieldChooser).dialog({
        width: $(window).width()/2,
        title: "Choose Fields",
        buttons: {
            "OK": function() {
                oList.fieldsChosen();
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        },
        modal: true,
    });
    
    $(this.divFieldChooser).dialog("open");
}

eiseList.prototype.fieldsChosen = function(){
    var strHiddenFields = "";
    $(this.divFieldChooser).find("input").each(function(){
       // alert (this.id+" "+this.checked);
        if(!this.checked){
           var o=this;
            var arrFldID = o.id.split("_");
            var strListName = arrFldID[1];
            var strFieldName = o.id.replace("flc_"+strListName+"_", "");
            strHiddenFields += (strHiddenFields=="" ? "" : ",")+strFieldName;
        }
    });
    $('#'+this.id+"HiddenCols").val(strHiddenFields);
    //alert (document.getElementById(lstName+"HiddenCols").value);
    this.conf.doNotSubmitForm=false;
    this.form.submit();
}

eiseList.prototype.reset = function (){
    
    // requires jQuery!
    $(".el_filter").each( function(idx, oInp){
        switch(oInp.nodeName){
            case "SELECT":
                if (oInp.name.replace(this.id+"_", "")=="staID"){
                    //INTRA2's staID exception
                    break;
                }
                oInp.selectedIndex=0;
                break;
            case "INPUT":
            default:
                oInp.value = "";
                break;
        }
    });
}

eiseList.prototype.toggleRowSelection = function(){
    
    var list = this;
    
    //1. check that we loaded all elements that match our selection
    //1a. no calcFoundRows - no selections
    if (!list.conf.calcFoundRows){
        alert("Function is not supported when calcFoundRows option is off.");
        return;
    }
    
    if (list.currentOffset < list.nTotalRows) {
        if (list.nTotalRows - list.nRowsLoaded > list.conf.maxRowsForSelection){
            alert("Number of rows to be loaded exceeds "+list.conf.maxRowsForSelection+".");
            return;
        } else {
            //2. if not, we download the rest (no more than specified in the config)    
            list.getData(list.currentOffset, list.nTotalRows - list.currentOffset, false, function(){
                list.toggleRowSelection();
            });
        }
    } else {
    
        //3. loop thru matched elements
        list.tbody.find('tr:not(.el_template) input[name="sel_'+list.id+'[]"]').each(function(){
            this.checked = !this.checked;
        });
    
    }
    
    
}


function eiseListInitialize(){
    
    $('.eiseList').each(function(){
        var listID = $(this).attr('id');
        var oList = new eiseList($(this));
        eiseLists[listID] = oList;
    });
    
}


eiseList.prototype.debug = function(msg){
    var oElDebug = $(this.div).find('.el_debug');
    oElDebug.text(oElDebug.text()+(oElDebug.text()!="" ? ' :: ' : '')+msg);
    oElDebug.slideDown();
}