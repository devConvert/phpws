/*
*	Visutaltask grid client library
*	OfferToro, Gilad Babin
* 	Packed: http://dean.edwards.name/packer/ and then https://www.javascriptobfuscator.com/Javascript-Obfuscator.aspx
*/

var VisualTaskGrid = (function(){

	"use strict";

	/*

		visual task grid can be used only with full qualified queries (with select, from, ... and not a predefined query string)

		_render(taskId, graphId)
		
		_query(taskId, graphId, e)

		_queryOnKeypPressEnter(taskId, graphId, e)

		_change(taskId, graphId, typeId, showFieldId, val)

		_csv(taskId, graphId)

		_ui(taskId, graphId, typeId)

	*/


	var _isQuerying = false;
	var _limitSizeDefault = 10;

	var _funcs = ["", "count", "countd", "sum", "avg", "max", "min", "gconcat", "gconcatd", "date", "yearmonth", "year", "month", "day", "hour"];
	var _funcsDesc = ["&nbsp;", "Count", "Count D.", "Sum", "Average", "Max", "Min", "Concat", "Concat D.", "Date", "Year-Month", "Year", "Month", "Day", "Hour"];






	function _render(taskId, graphId){

		var _options = window.VisualTasks[taskId];

		var graph = _options.graphs[graphId]; 

		var results = _options.results[graph.queryId];

		var queryConfig = _options.queries[graph.queryId];

		var html = ["<table class='table table-striped table-hover vtg-grid' data-vtgtaskid='"+taskId+"' data-vtggraphid='"+graphId+"' data-vtgfrom='"+queryConfig.from+"'><thead><tr class='vtg-headers'>"];

		var headerValues = graph.header.values;

		var thClasses, htmlAttribs, fieldConfig, testFieldName, showFieldIds = [];

		var realFieldName, rowCellId, queryOrderById, queryGroupById, dir, cellRenderer, cellStyler, cellStyle, topAggregationCellRenderer, bottomAggregationCellRenderer, topAggregationHTMLArrId;

		var isShowBottomAggregationRow = false, isShowTopAggregationRow = false;

		var thFields = [];

		for (var i=0; i<headerValues.length; i++){

			htmlAttribs = [];
			thClasses = ["vtg-header"];
			fieldConfig = graph.fields[i];

			if (typeof fieldConfig.isHide === "boolean" && fieldConfig.isHide)
				continue;

			realFieldName = "";
			rowCellId = -1;

			for (var j=0; j<queryConfig.select.length; j++){
				if (typeof queryConfig.select[j].alias !== "undefined")
					testFieldName = queryConfig.select[j].alias;
				else
					testFieldName = queryConfig.select[j].fieldName;

				if (testFieldName == fieldConfig.fieldName){
					rowCellId = j;
					realFieldName = queryConfig.select[j].fieldName;
					break;
				}
			}

			if (realFieldName === "")
				return false;

			if (typeof fieldConfig.topAggregationCellRenderer !== "undefined")
				isShowTopAggregationRow = true;

			if (typeof fieldConfig.bottomAggregationCellRenderer !== "undefined")
				isShowBottomAggregationRow = true;

			htmlAttribs.push("data-vtgrealfieldname='" + realFieldName + "'");
			htmlAttribs.push("data-vtgfieldname='" + testFieldName + "'");

			if (typeof queryConfig.select[rowCellId].function !== "undefined")
				htmlAttribs.push("data-vtgselectfunction='"+queryConfig.select[rowCellId].function+"'");

			if (typeof fieldConfig.isSortable === "undefined" || (typeof fieldConfig.isSortable !== "undefined" && fieldConfig.isSortable))
				thClasses.push("vtg-sortable");

			if (typeof fieldConfig.isGroupable === "undefined" || (typeof fieldConfig.isGroupable !== "undefined" && fieldConfig.isGroupable))
				thClasses.push("vtg-groupable");

			queryOrderById = -1;
			if (typeof queryConfig.orderBy !== "undefined"){
				for (var j=0; j<queryConfig.orderBy.length; j++)
					if (queryConfig.orderBy[j].fieldName == realFieldName){

						queryOrderById = j;

						dir = typeof queryConfig.orderBy[j].dir !== "undefined" ? queryConfig.orderBy[j].dir : "asc";

						if (dir !== "asc")
							dir = "desc";

						thClasses.push("vtg-orderby-"+dir);
						htmlAttribs.push("data-vtgorderbyid='" + j + "'");

						if (typeof queryConfig.orderBy[j].function !== "undefined")
							htmlAttribs.push("data-vtgorderbyfunction='" + queryConfig.orderBy[j].function + "'");

						break;
					}
			}

			queryGroupById = -1;
			if (typeof queryConfig.groupBy !== "undefined"){
				for (var j=0; j<queryConfig.groupBy.length; j++)
					if (queryConfig.groupBy[j].fieldName == realFieldName){

						queryGroupById = j;

						htmlAttribs.push("data-vtggroupbyid='" + j + "'");

						if (typeof queryConfig.groupBy[j].function !== "undefined")
							htmlAttribs.push("data-vtggroupbyfunction='" + queryConfig.groupBy[j].function + "'");

						break;
					}
			}

			htmlAttribs.push("data-vtgfieldtype='"+fieldConfig.fieldType+"'");

			if (typeof fieldConfig.useDictionaryId !== "undefined")
				htmlAttribs.push("data-dictid='"+fieldConfig.useDictionaryId+"'");

			showFieldIds.push([
				rowCellId, // also querySelectId
				(typeof fieldConfig.cellRenderer === "function" ? fieldConfig.cellRenderer : function(val, rowId){ return val; }), 
				(typeof fieldConfig.cellStyler === "function" ? fieldConfig.cellStyler : function(rowId){ return ""; }),
				(typeof fieldConfig.topAggregationCellRenderer === "function" ? fieldConfig.topAggregationCellRenderer : function(numRows, numCols){ return ""; }),
				(typeof fieldConfig.bottomAggregationCellRenderer === "function" ? fieldConfig.bottomAggregationCellRenderer : function(numRows, numCols){ return ""; }),
				(typeof fieldConfig.useDictionaryId !== "undefined" ? fieldConfig.useDictionaryId : ""),
				queryOrderById,
				queryGroupById,
				(typeof fieldConfig.isSortable === "boolean" ? fieldConfig.isSortable : true),
				(typeof fieldConfig.isGroupable === "boolean" ? fieldConfig.isGroupable : true)
			]);

			htmlAttribs.push("data-vtgshowfieldid='"+(showFieldIds.length-1)+"'");

			html.push("<th scope='col' class='"+thClasses.join(" ")+"' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' "+htmlAttribs.join(" ")+">" + headerValues[i] + "</th>");

			thFields.push({
				fieldName: realFieldName,
				fieldType: fieldConfig.fieldType,
				header: headerValues[i]
			});
		}


		if (typeof window.VisualTaskConfig !== "undefined" && typeof window.VisualTaskConfig.queries !== "undefined" && typeof window.VisualTaskConfig.queries[queryConfig.from] !== "undefined")
			thFields = window.VisualTaskConfig.queries[queryConfig.from];


		// filter box

		html.push("</tr><tr class='vtg-filter vtg-filter-hide'><th colspan='"+showFieldIds.length+"'>Filtering</th></tr><tr class='vtg-filter vtg-filter-hide'><th class='vtg-filter-list' colspan='"+showFieldIds.length+"'><div class='vtg-filter-thfields' style='display:none'>"+JSON.stringify(thFields)+"</div><div><input type='button' value='Add' onclick='VisualTaskGrid.ui(\""+taskId+"\", \""+graphId+"\", 20)' /></div>");

		if (typeof queryConfig.where !== "undefined")
			for (var j=0; j<queryConfig.where.length; j++)
				html.push(_getFilterHTML(thFields, taskId, graphId, queryConfig.where[j].fieldName, queryConfig.where[j].op, queryConfig.where[j].val, queryConfig.where[j].useDictionaryId));

		html.push("</tr><tr class='vtg-filter vtg-filter-hide'><th colspan='"+showFieldIds.length+"'><input type='button' value='Apply' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' /></th>");


		// advanced box

		html.push("</tr><tr class='vtg-advanced vtg-advanced-hide'><th colspan='"+showFieldIds.length+"'>Field Function</th></tr><tr class='vtg-advanced vtg-advanced-hide'>");

		for (var j=0; j<showFieldIds.length; j++)
			html.push((function(){

				var selectHTML = ["<th class='vtg-header-functionselect'><select data-vtgforshowfieldid='"+j+"' onchange='VisualTaskGrid.change(\""+taskId+"\", \""+graphId+"\", 0, "+j+", this.options[this.selectedIndex].value, this.selectedIndex)'>"];
				var selectFunction, fieldType, allowFuncs = [];

				if (typeof queryConfig.select[showFieldIds[j][0]].function === "undefined")
					selectFunction = "";
				else
					selectFunction = queryConfig.select[showFieldIds[j][0]].function;

				if (typeof graph.fields[showFieldIds[j][0]].fieldType !== "undefined")
					fieldType = graph.fields[showFieldIds[j][0]].fieldType;
				else
					fieldType = "";

				if (fieldType !== ""){
					switch (fieldType){
						case "string":
							allowFuncs = ["", "count", "countd", "gconcat", "gconcatd"];
							break;
						case "num":
							allowFuncs = ["", "count", "countd", "sum", "avg", "max", "min"];
							break;
						case "datetime":
							allowFuncs = ["", "count", "countd", "max", "min", "yearmonth", "year", "month", "day", "date", "hour"];
							break;
						case "boolean":
							allowFuncs = ["", "count", "countd"];
							break;
					}
				}

				for (var l=0; l<_funcs.length; l++){

					var funcSelectedHTML = "", funcDisabledHTML = "";

					if (selectFunction === _funcs[l])
						funcSelectedHTML = " selected";

					if (allowFuncs.indexOf(_funcs[l]) < 0)
						funcDisabledHTML = " disabled";

					selectHTML.push("<option value='" + _funcs[l] + "'" + funcSelectedHTML + funcDisabledHTML + ">" + _funcsDesc[l] + "</option>");
				}

				selectHTML.push("</select></th>");

				return selectHTML;

			})());

		html.push("</tr><tr class='vtg-advanced vtg-advanced-hide'><th colspan='"+showFieldIds.length+"'>Grouping Function</th></tr><tr class='vtg-advanced vtg-advanced-hide'>");

		for (var j=0; j<showFieldIds.length; j++)
			html.push((function(){

				var selectHTML = ["<th class='vtg-header-groupfunctionselect'><select "+(showFieldIds[j][7] == -1 ? "disabled" : "")+" data-vtgforshowfieldid='"+j+"' onchange='VisualTaskGrid.change(\""+taskId+"\", \""+graphId+"\", 1, "+j+", this.options[this.selectedIndex].value, this.selectedIndex)'>"];
				var groupByFunction, fieldType, allowFuncs = [];

				if (typeof queryConfig.groupBy === "undefined" || typeof queryConfig.groupBy[showFieldIds[j][7]] === "undefined" || typeof queryConfig.groupBy[showFieldIds[j][7]].function === "undefined")
					groupByFunction = "";
				else
					groupByFunction = queryConfig.groupBy[showFieldIds[j][7]].function;

				if (typeof graph.fields[showFieldIds[j][0]].fieldType !== "undefined")
					fieldType = graph.fields[showFieldIds[j][0]].fieldType;
				else
					fieldType = "";

				if (fieldType !== ""){
					switch (fieldType){
						case "string":
							allowFuncs = ["", "count", "countd", "gconcat", "gconcatd"];
							break;
						case "num":
							allowFuncs = ["", "count", "countd", "sum", "avg", "max", "min"];
							break;
						case "datetime":
							allowFuncs = ["", "count", "countd", "max", "min", "yearmonth", "year", "month", "day", "date", "hour"];
							break;
						case "boolean":
							allowFuncs = ["", "count", "countd"];
							break;
					}
				}

				for (var l=0; l<_funcs.length; l++){

					var funcSelectedHTML = "", funcDisabledHTML = "";

					if (groupByFunction === _funcs[l])
						funcSelectedHTML = " selected";

					if (allowFuncs.indexOf(_funcs[l]) < 0)
						funcDisabledHTML = " disabled";

					selectHTML.push("<option value='" + _funcs[l] + "'" + funcSelectedHTML + funcDisabledHTML + ">" + _funcsDesc[l] + "</option>");
				}

				selectHTML.push("</select></th>");

				return selectHTML;

			})());

		html.push("</tr><tr class='vtg-advanced vtg-advanced-hide'>");

		for (var j=0; j<showFieldIds.length; j++)
			html.push((function(){

				if (!showFieldIds[j][9])
					return "<th>&nbsp;</th>";


				var selectHTML = ["<th class='vtg-header-groupidselect'><select data-vtgforshowfieldid='"+j+"' onchange='VisualTaskGrid.change(\""+taskId+"\", \""+graphId+"\", 2, "+j+", this.options[this.selectedIndex].value, this.selectedIndex)'>"];
				var groupById = showFieldIds[j][7];

				if (groupById === -1)
					selectHTML.push("<option value='' selected>&nbsp;</option>");
				else
					selectHTML.push("<option value=''>&nbsp;</option>");

				for (var l=1; l<=showFieldIds.length-1; l++){

					if (groupById === l-1)
						selectHTML.push("<option value='"+(l-1)+"' selected>Group"+l+"</option>");
					else
						selectHTML.push("<option value='"+(l-1)+"'>Group"+l+"</option>");
				}

				selectHTML.push("</select></th>");

				return selectHTML;

			})());

		html.push("</tr><tr class='vtg-advanced vtg-advanced-hide'><th colspan='"+showFieldIds.length+"'><input type='button' value='Apply' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' /></th></tr>");


		// field selector

		var fieldSelectorHTML = ["<select class='vtg-fieldselector-fieldlist' multiple>"];

		if (typeof VisualTaskConfig !== "undefined" && typeof VisualTaskConfig.queries !== "undefined" && VisualTaskConfig.queries[queryConfig.from] !== "undefined"){
			for (var j=0; j<VisualTaskConfig.queries[queryConfig.from].length; j++){
				if (typeof VisualTaskConfig.queries[queryConfig.from][j].header !== "undefined")
					fieldSelectorHTML.push("<option value='"+j+"'>"+VisualTaskConfig.queries[queryConfig.from][j].header+"</option>");
				else
					fieldSelectorHTML.push("<option value='"+j+"'>"+VisualTaskConfig.queries[queryConfig.from][j].fieldName+"</option>");
			}
		}

		fieldSelectorHTML.push("</select>");

		html.push("<tr class='vtg-fieldselector vtg-fieldselector-hide'><th colspan='"+showFieldIds.length+"'>Field Selector</th></tr><tr class='vtg-fieldselector vtg-fieldselector-hide'><th class='vtg-fieldselector-fieldlistcontainer' colspan='"+showFieldIds.length+"'>"+fieldSelectorHTML.join("")+"</th></tr><tr class='vtg-fieldselector vtg-fieldselector-hide'><th colspan='"+showFieldIds.length+"'><input type='button' value='Apply' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' /></th></tr>");


		// top aggregation row

		if (isShowTopAggregationRow){

			html.push("<tr>");

			html.push("");

			topAggregationHTMLArrId = html.length - 1;

			html.push("</tr>");
		}

		html.push("</thead><tbody>");

		var cellValue, dict, dictId;
		for (var i=0; i<results.length; i++){

			html.push("<tr>");

			for (var j=0; j<showFieldIds.length; j++){
				rowCellId = showFieldIds[j][0];
				cellRenderer = showFieldIds[j][1];
				cellStyler = showFieldIds[j][2];
				dictId = showFieldIds[j][5];

				cellStyle = cellStyler(i);

				if (cellStyle.length > 0)
					cellStyle = " style='"+cellStyle+"'";

				cellValue = results[i][rowCellId];

				if (dictId !== ""){
					dict = VisualTask.dicts[dictId];
					if (typeof dict[cellValue] !== "undefined")
						cellValue = dict[cellValue];
				}

				html.push("<td"+cellStyle+">" + cellRenderer(cellValue, i) + "</td>");

				//

			}

			html.push("</tr>");
		}

		html.push("</tbody>");

		if (isShowTopAggregationRow){

			var topHTML = [];

			for (var j=0; j<showFieldIds.length; j++){
				topAggregationCellRenderer = showFieldIds[j][3];
				topHTML.push("<th class='vtg-top-aggregation'>" + topAggregationCellRenderer(results.length, showFieldIds.length) + "</th>");
			}

			html[topAggregationHTMLArrId] = topHTML.join("");
		}

		html.push("<tfoot>");

		if (isShowBottomAggregationRow){

			html.push("<tr>");

			for (var j=0; j<showFieldIds.length; j++){
				bottomAggregationCellRenderer = showFieldIds[j][4];
				html.push("<td class='vtg-bottom-aggregation'>" + bottomAggregationCellRenderer(results.length, showFieldIds.length) + "</td>");
			}

			html.push("</tr>");
		}


		// add toolbox

		var toolboxHTML = ["<tr><td colspan='"+showFieldIds.length+"'>"];

		if (typeof graph.isShowFieldSelector === "boolean" && graph.isShowFieldSelector)
			toolboxHTML.push("<input type='button' value='Field Selector' onclick='VisualTaskGrid.ui(\""+taskId+"\", \""+graphId+"\", 1)' />");

		if (typeof graph.isShowAdvancedBox === "boolean" && graph.isShowAdvancedBox)
			toolboxHTML.push("<input type='button' value='Advanced Menu' onclick='VisualTaskGrid.ui(\""+taskId+"\", \""+graphId+"\", 0)' />");

		if (typeof graph.isShowFiltering === "boolean" && graph.isShowFiltering)
			toolboxHTML.push("<input type='button' value='Filtering' onclick='VisualTaskGrid.ui(\""+taskId+"\", \""+graphId+"\", 2)' />");

		toolboxHTML.push("<input type='button' value='Export CSV' onclick='VisualTaskGrid.csv(\""+taskId+"\", \""+graphId+"\", event)' /></td></tr>");

		html.push(toolboxHTML.join("") + "</tfoot></table>");

		if (typeof graph.isShowPager === "boolean" && graph.isShowPager){
			
			var limitFirstRowId;
			if (typeof queryConfig.limit !== "undefined" && typeof queryConfig.limit.offset !== "undefined")
				limitFirstRowId = queryConfig.limit.offset;
			else
				limitFirstRowId = 0;

			var limitLastRowId = limitFirstRowId + results.length;

			var limitJump = (typeof queryConfig.limit !== "undefined" && typeof queryConfig.limit.size !== "undefined") ? parseInt(queryConfig.limit.size) : _limitSizeDefault;

			if (typeof VisualTaskConfig !== "undefined" && VisualTaskConfig.limitSizeMax !== "undefined"){
				var limitSizeMax = parseInt(VisualTaskConfig.limitSizeMax);
				if (limitJump > limitSizeMax)
					limitJump = limitSizeMax;
			}

			var pagerHTML = "<table class='vtg-pager' data-vtgtaskid='"+taskId+"' data-vtggraphid='"+graphId+"'><tbody><tr><td class='vtg-pager-middle-showing'>Showing Rows</td><td><input type='text' class='vtg-pager-middle-offset' value='"+(limitFirstRowId+1)+"' data-vtgpagerfromid='"+limitFirstRowId+"' /></td><td class='vtg-pager-middle-to'>to</td><td><input type='text' class='vtg-pager-middle-size' value='"+limitLastRowId+"' data-vtgpagertoid='"+limitFirstRowId+"' /></td><td><input class='vtg-pager-go-btn' type='button' value='Go' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' /></td><td class='vtg-pager-middle-jump'>Jump Size</td><td><input type='text' class='vtg-pager-middle-jump-size' value='"+limitJump+"' /></td><td><input type='button' class='vtg-pager-left' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' value='←' /></td><td><input type='button' class='vtg-pager-right' onclick='VisualTaskGrid.query(\""+taskId+"\", \""+graphId+"\", event)' value='→' /></td></tr></tbody></table>";

			html.push(pagerHTML);
		}

		document.getElementById(graph.HTMLElementId).innerHTML = html.join("");
	}

	function _query(taskId, graphId, e){

		if (_isQuerying)
			return false;

		var isCtrlPressed = false;
		e = e ? e : (window.event ? window.event : "");

		if (e && e.target)
			e.target.classList.add("vtg-interaction");

		if (e.ctrlKey)
			isCtrlPressed = true;

		_isQuerying = true;

		var _options = window.VisualTasks[taskId];

		var graph = _options.graphs[graphId];

		if (typeof _options.queries[graph.queryId].orderBy === "undefined")
			_options.queries[graph.queryId].orderBy = [];

		if (typeof _options.queries[graph.queryId].groupBy === "undefined")
			_options.queries[graph.queryId].groupBy = [];

		if (typeof _options.queries[graph.queryId].where === "undefined")
			_options.queries[graph.queryId].where = [];

		var queryConfig = _options.queries[graph.queryId];

		var graph = _options.graphs[graphId];

		var tbl = document.querySelector("table.vtg-grid[data-vtgtaskid='"+taskId+"'][data-vtggraphid='"+graphId+"']");

		if (!tbl){
			_isQuerying = false;
			return false;
		}

		var isInteraction = tbl.querySelector(".vtg-interaction") !== null;

		if (isInteraction && tbl.querySelector(".vtg-fieldselector .vtg-interaction") !== null){

			// user clicked apply on field selector

			var selectEl = tbl.querySelector(".vtg-fieldselector-fieldlist");

			var querySelectIds = [];

		    if (selectEl.selectedOptions != undefined) {
		        for (var i=0; i < selectEl.selectedOptions.length; i++) {
		            querySelectIds.push(selectEl.selectedOptions[i].value);
		        }
		    } else {
		        for (var i=0; i < selectEl.options.length; i++) {
		            if (selectEl.options[i].selected) {
		                querySelectIds.push(selectEl.options[i].value);
		            }
		        }
		    }

		    if (querySelectIds.length > 0){

		    	// user selected some fields so send a simple query

				// create another task but first delete old results

				window.VisualTasks[taskId].results[graph.queryId] = [];

				var options = {
					queries: {},
					graphs: {}
				};

				var tblName = _options.queries[graph.queryId].from;

				options.queries[graph.queryId] = {};
				options.queries[graph.queryId].select = [];
				options.queries[graph.queryId].from = tblName;

				if (typeof _options.queries[graph.queryId].type !== "undefined")
					options.queries[graph.queryId].type = _options.queries[graph.queryId].type;

				options.graphs[graphId] = _options.graphs[graphId];

				options.graphs[graphId].fields = [];
				options.graphs[graphId].header = { values: [] };

				for (var i=0; i<querySelectIds.length; i++){

					var _fieldName = VisualTaskConfig.queries[tblName][querySelectIds[i]].fieldName;

					var _fieldHeader;
					if (typeof VisualTaskConfig.queries[tblName][querySelectIds[i]].header !== "undefined")
						_fieldHeader = VisualTaskConfig.queries[tblName][querySelectIds[i]].header;
					else
						_fieldHeader = VisualTaskConfig.queries[tblName][querySelectIds[i]].fieldName;

					options.queries[graph.queryId].select.push({ fieldName: _fieldName });

					var graphFieldConfig = {
						fieldName: _fieldName
					};

					if (typeof VisualTaskConfig.queries[tblName][querySelectIds[i]].fieldType !== "undefined")
						graphFieldConfig.fieldType = VisualTaskConfig.queries[tblName][querySelectIds[i]].fieldType;

					options.graphs[graphId].fields.push(graphFieldConfig);
					options.graphs[graphId].header.values.push(_fieldHeader);
				}

				VisualTask.query(options);

				_isQuerying = false;

				return true;		    	
		    	
		    }
		}

		var querySelect = [];
		var queryWhere = [];
		var queryGroupBy = [];
		var queryOrderBy = [];


		// handle where

		// if where criteria were not changed then the where clause should be used also in next query unless field selector was used (already taken care of) or advanced menu was used
		if (tbl.querySelector(".vtg-filter .vtg-interaction") !== null) {

			// where criteria were changed so take it from ui

			var filters = tbl.querySelectorAll("div.vtg-filter-criterion");
			for (var i=0; i<filters.length; i++){

				var selectField = filters[i].querySelector(".vtg-filter-criterion-field");
				var selectOp = filters[i].querySelector(".vtg-filter-criterion-op");

				//groupByFunctionSelectEl.options[groupByFunctionSelectEl.selectedIndex].value

				var fieldName = selectField.options[selectField.selectedIndex].value;
				var fieldType = selectField.options[selectField.selectedIndex].getAttribute("data-vtgfieldtype");
				var op = selectOp.options[selectOp.selectedIndex].value;
				var val = filters[i].querySelector(".vtg-filter-criterion-value").value;

				queryWhere.push({
					fieldName: fieldName,
					fieldType: fieldType,
					op: op,
					val: val
				});
			}

		} else if (typeof queryConfig.where !== "undefined" && tbl.querySelector(".vtg-advanced .vtg-interaction") === null) {
			
			// advanced menu was not used so use where clause from current query in new one

			queryWhere = queryConfig.where;
		}

		// get orderby, groupby and limits

		var th, ths = tbl.querySelectorAll("th.vtg-header");
		var select, fieldName, realFieldName, isSortable, isGroupable, isClicked, isGrouped, isSortedAsc, isSortedDesc, groupById, orderById, fieldDataType, selectFunction, orderByFunction, groupByFunction;
		var clickOrderById = "";
		var isQueryHasGrouping = false, queryNonGroupedSelectIds = [];
		var groupByIds = {};

		for (var i=0; i<ths.length; i++){

			th = ths[i];

			isClicked = th.classList.contains("vtg-interaction");

			fieldName = th.getAttribute("data-vtgfieldname");
			realFieldName = th.getAttribute("data-vtgrealfieldname");

			isSortable = th.classList.contains("vtg-sortable");
			isGroupable = th.classList.contains("vtg-groupable");

			isSortedAsc = th.classList.contains("vtg-orderby-asc");
			isSortedDesc = th.classList.contains("vtg-orderby-desc");

			orderById = -1;
			if (isSortedAsc || isSortedDesc)
				orderById = th.getAttribute("data-vtgorderbyid");

			groupById = th.getAttribute("data-vtggroupbyid");

			isGrouped = false;
			if (groupById !== null && groupById !== ""){

				isGrouped = true;
				isQueryHasGrouping = true;

				groupById = parseInt(groupById);

				// check for duplicate groupById

				if (typeof groupByIds[groupById] !== "undefined"){

					// this groupById is already used so find next available groupById

					var j = 1;
					while (true){
						if (typeof groupByIds[groupById + j] === "undefined")
							break; 

						j++;
					}

					groupById += j;

				}

				groupByIds[groupById] = false;

			} else {

				groupById = -1;

			}
			
			fieldDataType = th.getAttribute("data-vtgfieldtype");

			selectFunction = th.getAttribute("data-vtgselectfunction");
			if (!selectFunction)
				selectFunction = "";

			orderByFunction = th.getAttribute("data-vtgorderbyfunction");
			if (!orderByFunction)
				orderByFunction = "";

			groupByFunction = th.getAttribute("data-vtggroupbyfunction");
			if (!groupByFunction)
				groupByFunction = "";


			// handle select

			querySelect.push({
				function: selectFunction,
				fieldName: realFieldName
			});

			if (fieldName !== realFieldName)
				querySelect[querySelect.length-1].alias = fieldName;


			// handle order by

			// if ctrl is not pressed then delete all other sortings instead of current
			// if ctrl is pressed then don't touch other sortings

			if (isSortedAsc || isSortedDesc){

				// sorting on this field existed before current query (with or without a click)

				if (!isClicked){

					queryOrderBy[orderById] = {
						function: orderByFunction,
						fieldName: realFieldName,
						dir: (isSortedAsc ? "asc" : "desc")
					};

				} else {

					clickOrderById = orderById;	

					queryOrderBy[orderById] = {
						function: orderByFunction,
						fieldName: realFieldName,
						dir: (isSortedAsc ? "desc" : "asc")
					};

				}

			} else if (isClicked && !isSortedAsc && !isSortedDesc) {

				// sorting on this field did not exist prior to click

				// can't order by without function while there's already a select function on this field

				if (orderByFunction === "" && selectFunction !== "")
					orderByFunction = selectFunction;

				orderById = -1;
				clickOrderById = orderById;

				queryOrderBy[orderById.toString()] = {
					function: orderByFunction,
					fieldName: realFieldName,
					dir: "asc"					
				};
			}

			// handle group by

			if (isGrouped){
				queryGroupBy[groupById] = {
					function: groupByFunction,
					fieldName: realFieldName
				};
			} else if (selectFunction === "") {
				queryNonGroupedSelectIds.push(i);
			}

		}


		// get rid of empty slots in queryGroupBy

		var newQueryGroupBy = [];
		for (var i=0; i<queryGroupBy.length; i++)
			if (typeof queryGroupBy[i] !== "undefined")
				newQueryGroupBy.push(queryGroupBy[i]);
		queryGroupBy = newQueryGroupBy;


		// handle order by

		if (clickOrderById != "" && !isCtrlPressed){

			// a click was made on a header for sorting and ctrl was not pressed
			// leave only the clicked field as sorted

			queryOrderBy = [queryOrderBy[clickOrderById]];

		} else {

			// add the negative ids in queryOrderBy at the end of the array

			orderById = -1;

			if (typeof queryOrderBy[orderById.toString()] !== "undefined"){
				queryOrderBy.push(queryOrderBy[orderById.toString()]);
				delete queryOrderBy[orderById.toString()];
			}

		}


		// when query is grouping all non grouped fields must have an aggregative function

		if (isQueryHasGrouping && queryNonGroupedSelectIds.length > 0){

			// automatically add field function to non-grouped fields
			for (var i=0; i<queryNonGroupedSelectIds.length; i++){

				querySelect[queryNonGroupedSelectIds[i]].function = "count";

				// TODO: if fieldType is datetime then put "date"

				// if these field is sorted then add function to order by
				for (var j=0; j<queryOrderBy.length; j++){
					if (queryOrderBy[j].fieldName === querySelect[queryNonGroupedSelectIds[i]].fieldName)
						if (typeof queryOrderBy[j].function === "undefined" || queryOrderBy[j].function === ""){
							queryOrderBy[j].function = "count";
							break;
						}
				}

			}

		}


		// handle limits

		var limitOffset = 0, limitSize = _limitSizeDefault;

		// when doing something else than limits it should reset limits

		if (!isInteraction){
			if (typeof queryConfig.limit !== "undefined"){

				if (typeof queryConfig.limit.offset !== "undefined")
					limitOffset = parseInt(queryConfig.limit.offset);

				if (typeof queryConfig.limit.size !== "undefined")
					limitSize = parseInt(queryConfig.limit.size);
			}
		}


		// check for pager

		var pagerTbl = document.querySelector("table.vtg-pager[data-vtgtaskid='"+taskId+"'][data-vtggraphid='"+graphId+"']");
		var pagerInteractionEl;

		if (pagerTbl){

			var el = pagerTbl.querySelector(".vtg-interaction");
			pagerInteractionEl = el;

			if (el){

				// clicked on pager

				if (el.classList.contains("vtg-pager-left") || el.classList.contains("vtg-pager-right")){
					
					// clicked on pager jump

					var limitJump = _limitSizeDefault;

					var jumpEl = pagerTbl.querySelector(".vtg-pager-middle-jump-size");

					if (jumpEl)
						limitJump = parseInt(jumpEl.value);

					if (el.classList.contains("vtg-pager-left")){

						limitOffset -= limitJump;
						if (limitOffset < 0)
							limitOffset = 0;

						limitSize = limitJump;

					} else {

						limitOffset += limitJump;
						limitSize = limitJump;

					}

				} else {

					// clicked on Go

					var fromEl = pagerTbl.querySelector(".vtg-pager-middle-offset");
					var toEl = pagerTbl.querySelector(".vtg-pager-middle-size");

					if (fromEl && toEl){

						var fromId = parseInt(fromEl.value) - 1;

						if (fromId < 0)
							fromId = 0;

						var toId = parseInt(toEl.value) - 1;

						if (toId < 0)
							toId = 0;

						var originalFromId = fromEl.getAttribute("data-vtgpagerfromid");
						var originalToId = toEl.getAttribute("data-vtgpagertoid");

						limitOffset = fromId;
						limitSize = toId - fromId + 1;
					}

				}

			}
		
		}

		if (isInteraction && !pagerInteractionEl){

			// there was interaction but not with pager so reset limits

			limitOffset = 0; 
			limitSize = _limitSizeDefault;
		}


		// create another task but first 

		var options = {
			queries: {},
			graphs: {}
		};

		options.queries[graph.queryId] = _options.queries[graph.queryId];
		options.queries[graph.queryId].select = querySelect;
		options.queries[graph.queryId].where = queryWhere;
		options.queries[graph.queryId].groupBy = queryGroupBy;
		options.queries[graph.queryId].orderBy = queryOrderBy;
		options.queries[graph.queryId].limit = { offset: limitOffset, size: limitSize };

		// TODO: check validity of query:

		// delete old results
		
		window.VisualTasks[taskId].results[graph.queryId] = [];

		options.graphs[graphId] = _options.graphs[graphId];

		VisualTask.query(_options);

		_isQuerying = false;

		return true;
	}

	function _queryOnKeypPressEnter(taskId, graphId, e){
		e = e ? e : (window.event ? window.event : "");

		if (e && (e.keyCode == 13 || e.which == 13))
			_query(taskId, graphId);
	}

	function _csv(taskId, graphId){
		var tbl = document.querySelector("table.vtg-grid[data-vtgtaskid='"+taskId+"'][data-vtggraphid='"+graphId+"']");

		if (!tbl)
			return false;

		var csv = [];

		var ths = tbl.querySelector("tr.vtg-headers").children;
		for (var i=0; i<ths.length; i++)
			csv.push((i > 0 ? "," : "") + ths[i].innerHTML);

		csv = [csv.join("")];

		var trs = tbl.querySelector("tbody").children;

		for (var i=0; i<trs.length; i++){
			var line = "";
			var tds = trs[i].children;
			for (var j=0; j<tds.length; j++)
				line += (j > 0 ? "," : "") + tds[j].innerHTML.replace(/,/g, " ").replace(/\t/g, " ").replace(/\n/g, " ").replace(/\r/g, " ");
			csv.push(line);
		}

		var filename = "export.csv";
		var uri = encodeURI("data:text/plain;charset=utf-8,\ufeff" + csv.join("\r\n"));

		var link = document.createElement('a');
		link.style = "display:none";
	    link.setAttribute('href', uri);
	    link.setAttribute('download', filename);
		link.setAttribute('target', "_blank");
		document.body.appendChild(link);
	    link.click();
		document.body.removeChild(link);		
	}

	function _ui(taskId, graphId, typeId, val){
		var tbl = document.querySelector("table.vtg-grid[data-vtgtaskid='"+taskId+"'][data-vtggraphid='"+graphId+"']");

		if (!tbl)
			return false;

		if (typeId == 20){

			// add filter
			/*
			var thFields = [];

			var ths = tbl.querySelectorAll("tr.vtg-headers > th.vtg-header");
			for (var i=0; i<ths.length; i++)
				thFields.push({
					fieldName: ths[i].getAttribute("data-vtgrealfieldname"),
					fieldType: ths[i].getAttribute("data-vtgfieldtype"),
					header: ths[i].innerHTML
				});

			//vtg-filter-thfields
			*/

			var thFields = JSON.parse(tbl.querySelector("div.vtg-filter-thfields").innerHTML);

			tbl.querySelector(".vtg-filter-list").innerHTML += _getFilterHTML(thFields, taskId, graphId);
		}

		function showAdvancedBox(){
			var els = tbl.querySelectorAll(".vtg-advanced.vtg-advanced-hide");
			for (var i=0; i<els.length; i++)
				els[i].classList.remove("vtg-advanced-hide");
		}

		function hideAdvancedBox(){
			var els = tbl.querySelectorAll(".vtg-advanced");
			for (var i=0; i<els.length; i++)
				els[i].classList.add("vtg-advanced-hide");
		}

		function showFieldSelector(){
			var els = tbl.querySelectorAll(".vtg-fieldselector.vtg-fieldselector-hide");
			for (var i=0; i<els.length; i++)
				els[i].classList.remove("vtg-fieldselector-hide");
		}

		function hideFieldSelector(){
			var els = tbl.querySelectorAll(".vtg-fieldselector");
			for (var i=0; i<els.length; i++)
				els[i].classList.add("vtg-fieldselector-hide");
		}

		function showFiltering(){
			var els = tbl.querySelectorAll(".vtg-filter.vtg-filter-hide");
			for (var i=0; i<els.length; i++)
				els[i].classList.remove("vtg-filter-hide");
		}

		function hideFiltering(){
			var els = tbl.querySelectorAll(".vtg-filter");
			for (var i=0; i<els.length; i++)
				els[i].classList.add("vtg-filter-hide");
		}

		switch (typeId){
			case 0:

				// advanced box

				hideFieldSelector();
				hideFiltering();

				if (typeof val === "undefined"){
					// trigger
					if (tbl.querySelector(".vtg-advanced.vtg-advanced-hide"))
						showAdvancedBox();
					else
						hideAdvancedBox();
				} else if (typeof val === "boolean" && val){
					showAdvancedBox();
				} else {
					hideAdvancedBox();
				}

				break;

			case 1:

				// field selector

				hideAdvancedBox();
				hideFiltering();

				if (typeof val === "undefined"){
					// trigger
					if (tbl.querySelector(".vtg-fieldselector.vtg-fieldselector-hide"))
						showFieldSelector();
					else
						hideFieldSelector();
				} else if (typeof val === "boolean" && val){
					showFieldSelector();
				} else {
					hideFieldSelector();
				}

				break;

			case 2:

				// filtering

				hideFieldSelector();
				hideAdvancedBox();

				if (typeof val === "undefined"){
					// trigger
					if (tbl.querySelector(".vtg-filter.vtg-filter-hide"))
						showFiltering();
					else
						hideFiltering();
				} else if (typeof val === "boolean" && val){
					showFiltering();
				} else {
					hideFiltering();
				}

				break;
		}

	}

	function _change(taskId, graphId, typeId, showFieldId, val, selectedIndex){
		var tbl = document.querySelector("table.vtg-grid[data-vtgtaskid='"+taskId+"'][data-vtggraphid='"+graphId+"']");

		if (!tbl)
			return false;

		var th = tbl.querySelector("th[data-vtgshowfieldid='"+showFieldId+"']");

		var fieldFunctionSelectEl = tbl.querySelector(".vtg-header-functionselect select[data-vtgforshowfieldid='"+showFieldId+"']");
		var groupByFunctionSelectEl = tbl.querySelector(".vtg-header-groupfunctionselect select[data-vtgforshowfieldid='"+showFieldId+"']");
		var groupByIdSelectEl = tbl.querySelector(".vtg-header-groupidselect select[data-vtgforshowfieldid='"+showFieldId+"']");

		if (!th)
			return false;

		switch (typeId){
			case 0:
				// field function
				th.setAttribute("data-vtgselectfunction", val);

				if (groupByIdSelectEl.selectedIndex > 0){
					// group by level is not empty so set field function like group by function
					groupByFunctionSelectEl.selectedIndex = selectedIndex;
					th.setAttribute("data-vtggroupbyfunction", groupByFunctionSelectEl.options[groupByFunctionSelectEl.selectedIndex].value);
				}

				// if this is field is sorted then add order by function
				var orderById = th.getAttribute("data-vtgorderbyid");
				if (orderById && orderById !== "")
					th.setAttribute("data-vtgorderbyfunction", val);

				break;
			case 1:
				// group by function
				th.setAttribute("data-vtggroupbyfunction", val);

				if (groupByIdSelectEl.selectedIndex > 0){
					// group by level is not empty so set group by function like field function
					fieldFunctionSelectEl.selectedIndex = selectedIndex;
					th.setAttribute("data-vtgselectfunction", fieldFunctionSelectEl.options[fieldFunctionSelectEl.selectedIndex].value);

					// if this is field is sorted then add order by function
					var orderById = th.getAttribute("data-vtgorderbyid");
					if (orderById && orderById !== "")
						th.setAttribute("data-vtgorderbyfunction", val);
				}

				break;
			case 2:
				// group by level
				th.setAttribute("data-vtggroupbyid", val);

				if (val !== ""){

					groupByFunctionSelectEl.removeAttribute("disabled");
					groupByFunctionSelectEl.selectedIndex = fieldFunctionSelectEl.selectedIndex;

					th.setAttribute("data-vtggroupbyfunction", groupByFunctionSelectEl.options[groupByFunctionSelectEl.selectedIndex].value);

				} else {

					groupByFunctionSelectEl.setAttribute("disabled", "disabled");
					groupByFunctionSelectEl.selectedIndex = 0;

					th.setAttribute("data-vtggroupbyfunction", "");

				}


				break;
		}

		return true;
	}

	function _getFilterHTML(thFields, taskId, graphId, fieldName, op, val, useDictionaryId){

		if (typeof fieldName === "undefined")
			fieldName = "";

		if (typeof op === "undefined")
			op = "";

		if (typeof val === "undefined")
			val = "";

		var selectFieldHTML = ["<select class='vtg-filter-criterion-field'>"];

		var fieldType = "";

		for (var i=0; i<thFields.length; i++){

			var thFieldName = thFields[i].fieldName;
			var thFieldType = thFields[i].fieldType;
			var thHeader = thFields[i].header;

			if (thFieldName !== fieldName){
				selectFieldHTML.push("<option value='"+thFieldName+"' data-vtgfieldtype='"+thFieldType+"'>"+thHeader+"</option>");
			} else {
				selectFieldHTML.push("<option value='"+thFieldName+"' data-vtgfieldtype='"+thFieldType+"' selected>"+thHeader+"</option>");
				fieldType = thFieldType;
			}
		}

		selectFieldHTML.push("</select>");

		var selectOpHTML = ["<select class='vtg-filter-criterion-op'>"];

		var ops = ["=", ">", ">=", "<", "<=", "!=", "in", "startswith", "endswith", "includes"];

		for (var i=0; i<ops.length; i++){

			if (ops[i] !== op)
				selectOpHTML.push("<option value='"+ops[i]+"'>"+ops[i]+"</option>");
			else
				selectOpHTML.push("<option value='"+ops[i]+"' selected>"+ops[i]+"</option>");
		}

		selectOpHTML.push("</select>");

		var html = "<div class='vtg-filter-criterion'>" + selectFieldHTML.join("") + selectOpHTML.join("") + "<input class='vtg-filter-criterion-value' type='text' value='"+val+"' />" + (typeof useDictionaryId === "undefined" ? "" : "") + "<input type='button' value='-' onclick='this.parentElement.parentElement.removeChild(this.parentElement)' /></div>";

		return html;
	}





	return {
		render: _render,
		query: _query,
		queryOnKeypPressEnter: _queryOnKeypPressEnter,
		change: _change,
		csv: _csv,
		ui: _ui
	};


})();