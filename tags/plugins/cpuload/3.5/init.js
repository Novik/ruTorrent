plugin.loadMainCSS()

function rLoadGraph()
{
}

rLoadGraph.prototype.create = function( aOwner )
{
	this.owner = aOwner;
	this.maxSeconds = 180;
	this.seconds = -1;
	this.load = { label: null, data: [] };
	this.startSeconds = new Date().getTime()/1000;
}


rLoadGraph.prototype.draw = function( percent )
{
	var self = this;
	$(function() 
	{
		if(self.owner.height() && self.owner.width())
		{
			clearCanvas( self.owner.get(0) );
			self.owner.empty();

			$.plot(self.owner, [ self.load.data ],
			{ 
				legend: 
				{
					show: false
				},
				colors:
				[
					(new RGBackground()).setGradient(plugin.prgStartColor,plugin.prgEndColor,percent).getColor()
				],
				lines:
				{
					show: true,
					lineWidth: 1,
					fill: true
				},
				points: { lineWidth: 0, radius: 0 },
				grid:
				{
					borderWidth: 0,
					labelMargin: 0
				},
				xaxis: 
				{ 
					max: (self.seconds-self.startSeconds>=self.maxSeconds) ? null : self.maxSeconds+self.startSeconds,
					labelHeight: 0,
					labelWidth: 0,
					autoscaleMargin: 0,
					noTicks: true
			 	},
				shadowSize: 0,
			  	yaxis: 
			  	{ 
			  		min: 0,
//			  		max: 100,
					labelHeight: 0,
					labelWidth: 0,
					autoscaleMargin: 0,
					noTicks: true
		  		}
			});
			self.owner.append( $("<div>").attr("id","meter-cpu-text").css({top: 0}).text(percent+'%') ).attr("title", percent+'%');
		}
	}
	);
}

rLoadGraph.prototype.addData = function( percent )
{
	this.seconds = new Date().getTime()/1000;
	this.load.data.push([this.seconds,percent]);
	while((this.load.data[this.load.data.length-1][0]-this.load.data[0][0])>this.maxSeconds)
		this.load.data.shift(); 
	this.draw(percent);
}

plugin.init = function()
{
	if(getCSSRule("#meter-cpu-holder"))
	{
		plugin.prgStartColor = new RGBackground("#99D699");
		plugin.prgEndColor = new RGBackground("#E69999");
		plugin.addPaneToStatusbar("meter-cpu-td",$("<div>").attr("id","meter-cpu-holder").get(0));
		plugin.graph = new rLoadGraph();
		plugin.graph.create( $("#meter-cpu-holder") );
		plugin.check = function()
		{
			var AjaxReq = jQuery.ajax(
			{
				type: "GET",
				timeout: theWebUI.settings["webui.reqtimeout"],
			        async : true,
			        cache: false,
				url : "plugins/cpuload/action.php",
				dataType : "json",
				cache: false,
				success : function(data)
				{
					plugin.graph.addData( data.load );
				}
			});
		};
		plugin.check();
		plugin.reqId = theRequestManager.addRequest( "ttl", null, plugin.check );
		plugin.markLoaded();
	}
	else
		window.setTimeout(arguments.callee,500);
};

plugin.onRemove = function()
{
	plugin.removePaneFromStatusbar("meter-cpu-td");
	theRequestManager.removeRequest( "ttl", plugin.reqId );
}

plugin.init();