/*
*	Visutaltask client library
*	OfferToro, Gilad Babin
* 	Packed: http://dean.edwards.name/packer/ and then https://www.javascriptobfuscator.com/Javascript-Obfuscator.aspx
*/

var VisualTask = (function(){

	"use strict";


	/*
		query - send a visual task to server and render all graphs
	*/


	var _dicts = {};					// dictId => object (k => v)
	var _dictNames = {};				// dictId => dict name
	var _endpoint;




	function _query(options, cbSuccess){

		// send options with only the queries object

		if (typeof options === "undefined" || typeof options.queries === "undefined")
			return false;

		if (typeof options.taskId === "undefined")
			options.taskId = "t"+Math.random().toString().substr(2);

		if (typeof VisualTaskConfig.errorHTMLElement !== "undefined"){
			var el = document.getElementById(VisualTaskConfig.errorHTMLElement);
			if (el){
				el.style.display = "none";
				el.innerHTML = "";
			}
		}

		if (typeof cbSuccess !== "function")
			cbSuccess = function(){};

		var xhr = new XMLHttpRequest();
		
		xhr.open('post', window.VisualTaskConfig.endpoint);

		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send("options="+JSON.stringify({ queries: options.queries }));

		xhr.onreadystatechange = function () {

			var DONE = 4; // readyState 4 means the request is done.
			var OK = 200; // status 200 is a successful return.

			if (xhr.readyState === DONE){

				if (xhr.status === OK){

					var obj;

					try {
						obj = JSON.parse(xhr.responseText);
					} catch (ex) {

						console.log("There was a problem with the VisualTask query");
						console.log("The query:");
						console.log(options.queries);
						console.log("Exception msg:");
						console.log(ex.message);

						if (typeof VisualTaskConfig.errorHTMLElement !== "undefined"){
							var el = document.getElementById(VisualTaskConfig.errorHTMLElement);
							if (el){
								el.innerHTML = "There was a problem with the VisualTask query, please verify you made a valid query.";
								el.style.display = "";
							}
						}
						
					}

					if (typeof obj === "undefined" || typeof obj.queries === "undefined" || typeof obj.results === "undefined")
						return false;

					options.queries = obj.queries;
					options.results = obj.results;

					var val = _render(options);
					cbSuccess();
					return val;

				} else {

					console.log('Error: ' + xhr.status); // An error occurred during the request.
					return false;

				}

			}
		};
		

	}

	function _render(options){

		if (typeof window.VisualTasks !== "object")
			window.VisualTasks = {};
	
		window.VisualTasks[options.taskId] = options;	

		if (typeof options.results === "undefined")
			options.results = {};

		// first create dictionaries
		if (typeof options.dictionaries !== "undefined"){
			for (var dictId in options.dictionaries)
				if (options.dictionaries.hasOwnProperty(dictId)){

					var dict = options.dictionaries[dictId];

					_dicts[dictId] = {};

					if (typeof dict.name !== "undefined")
						_dictNames[dictId] = dict.name;
					else
						_dictNames[dictId] = "";

					// check if queryId exists and has results
					if (typeof options.queries[dict.queryId] === "undefined" || typeof options.results[dict.queryId] !== "object" || typeof options.results[dict.queryId][0] === "undefined")
						continue;

					if (typeof dict.isLazy !== "boolean")
						dict.isLazy = false;

					options.dictionaries[dictId].isLazy = dict.isLazy;


					// find the fromId => toId in the query results

					var fromId, toId;
					var isFromId = false, isToId = false;

					for (var i=0; i<options.queries[dict.queryId].select.length && (!isFromId || !isToId); i++){
						var select = options.queries[dict.queryId].select[i];
						if (typeof select.alias !== "undefined"){
							// check with alias
							if (!isFromId && select.alias == dict.from){
								fromId = i;
								isFromId = true;
							} else if (!isToId && select.alias == dict.to){
								toId = i;
								isToId = true;
							}
						} else {
							// there's no alias so use fieldName
							if (!isFromId && select.fieldName == dict.from){
								fromId = i;
								isFromId = true;
							} else if (!isToId && select.fieldName == dict.to){
								toId = i;
								isToId = true;
							}
						}
					}

					if (!isFromId || !isToId)
						continue;
					
					if (!dict.isLazy){
						// not lazy so load all object properties now
						var results = options.results[dict.queryId];
						for (var i=0; i<results.length; i++)
							_dicts[dictId][results[i][fromId]] = results[i][toId];
					}
				}
		}

		// plot graphs
		if (typeof options.graphs !== "undefined")
			for (var graphId in options.graphs)
				if (options.graphs.hasOwnProperty(graphId)){

					switch (options.graphs[graphId].lib){
						case "plotly":
							_renderPlotlyGraph(options, graphId);
							break;
						case "visualtaskgrid":
							_renderVisualTaskGrid(options, graphId);
							break;
					}

				}

		return true;
	}

	function _renderPlotlyGraph(options, graphId){

		var graph = options.graphs[graphId];
		var rows = [];

		if (typeof Plotly === "undefined")
			return false;

		if (typeof options.results[graph.queryId] !== "object" || typeof options.results[graph.queryId][0] === "undefined")
			return false;

		// can't mix table types with other types in the same graph!
		// if type is not table then use graph.config.data[i].xFieldId and graph.config.data[i].yFieldId
		// if type is table then only one cell is permitted in config.data!

		// populate rows with query results according to plotly requirements

		var isAllFieldsFound = true;
		for (var i=0; i<graph.fields.length && isAllFieldsFound; i++){
			rows[i] = [];

			var graphField = graph.fields[i];

			var fieldId;
			var isFieldId = false;

			for (var j=0; j<options.queries[graph.queryId].select.length && !isFieldId; j++){
				var select = options.queries[graph.queryId].select[j];
				if (typeof select.alias !== "undefined"){
					// check with alias
					if (!isFieldId && select.alias == graphField.fieldName){
						fieldId = j;
						isFieldId = true;
					}
				} else {
					// there's no alias so use fieldName
					if (!isFieldId && select.fieldName == graphField.fieldName){
						fieldId = j;
						isFieldId = true;
					}
				}
			}

			if (!isFieldId){
				isAllFieldsFound = false;
				break;;
			}

			// insert the row into rows
			var results = options.results[graph.queryId];

			if (typeof graphField.useDictionaryId !== "undefined"){
				// use dictionary

				if (typeof _dicts[graphField.useDictionaryId] === "undefined"){
					isAllFieldsFound = false;
					break;;
				}

				var dict = _dicts[graphField.useDictionaryId];

				for (var j=0; j<results.length; j++)
					if (typeof dict[results[j][fieldId]] !== "undefined")
						rows[i].push(dict[results[j][fieldId]]);
					else
						rows[i].push(results[j][fieldId]);

			} else {
				// don't use dictionary

				for (var j=0; j<results.length; j++)
					rows[i].push(results[j][fieldId]);
			}

		}

		if (!isAllFieldsFound)
			return false;

		// plot rows according to type
		if (graph.config.data[0].type === "table"){

			// plot a table

			if (typeof graph.config.data[0].cells === "undefined")
				graph.config.data[0].cells = {};

			console.log(rows);

			graph.config.data[0].cells.values = rows;

			console.log(graph.config.data[0]);
			
			Plotly.newPlot(graph.HTMLElementId, [graph.config.data[0]], graph.config.layout || {});
			
			// clean options from the additional properties
			delete graph.config.data[0].cells.values;

		} else {

			// add x according to xFieldId and y according to yFieldId to config.data objects

			for (var i=0; i<graph.config.data.length; i++){
				if (typeof graph.config.data[i].xFieldId === "undefined" || typeof graph.config.data[i].yFieldId === "undefined")
					continue;

				var xFieldId = graph.config.data[i].xFieldId;
				var yFieldId = graph.config.data[i].yFieldId;

				if (typeof rows[xFieldId] === "undefined" || typeof rows[yFieldId] === "undefined")
					continue;

				graph.config.data[i].x = rows[xFieldId];
				graph.config.data[i].y = rows[yFieldId];
			}

			Plotly.newPlot(graph.HTMLElementId, graph.config.data, graph.config.layout || {});

			// clean options from the additional properties
			for (var i=0; i<graph.config.data.length; i++){
				delete graph.config.data[i].x;
				delete graph.config.data[i].y;
			}

		}

		return true;
	}

	function _renderVisualTaskGrid(options, graphId){
		
		if (typeof VisualTaskGrid === "undefined")
			return false;

		VisualTaskGrid.render(options.taskId, graphId);
	}



	return {
		dicts: _dicts,
		query: _query
	};

})();