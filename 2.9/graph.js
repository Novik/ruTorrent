function clearElement(target)
{
	while(target.hasChildNodes())
	{
		clearElement(target.firstChild);
		target.firstChild.removeNode();
	}
}

function clearCanvas( target )
{
	var cnv = target.getElementsByTagName('canvas');
	if(cnv)
	{
		for(var i=0; i<cnv.length; i++)
		{
			cnv[i].onmousemove = null;
			cnv[i].onpropertychange = null;
			cnv[i].onresize = null;
			if(browser.isIE)
			{
				cnv[i].getContext = null;
				cnv[i].context_.canvas = null;
				cnv[i].context_ = null;
			}
		}
	}
	if(browser.isIE)
		clearElement(target);
}

function rSpeedGraph()
{
}

rSpeedGraph.prototype.create = function( aOwner )
{
	this.owner = aOwner;
	this.maxSeconds = 600;
	this.seconds = -1;
	this.down = { label: WUILang.DL, data: [], color: "#1C8DFF" };
	this.up = { label: WUILang.UL, data: [], color: "#009900" };
	this.startSeconds = new Date().getTime()/1000;
}

var previousSpeedPoint = null;
rSpeedGraph.prototype.draw = function()
{
	var self = this;
	$(function() 
	{
		if((utWebUI.activeView=='Speed') &&
			$("#"+self.owner.id).height() && $("#"+self.owner.id).width())
		{
			clearCanvas( self.owner );
			function xTick(n) 
			{
				var dt = new Date(n*1000-utWebUI.deltaTime);
				var h = dt.getHours();
				var m = dt.getMinutes();
				var s = dt.getSeconds();
				h = (h < 10) ? ("0" + h) : h;
				m = (m < 10) ? ("0" + m) : m;
				s = (s < 10) ? ("0" + s) : s;
				return( h+":"+m+":"+s );
			}

			$.plot($("#"+self.owner.id), [ self.up, self.down ],
			{ 
				colors:
				[
				 	self.up.color, self.down.color
				],
				lines:
				{
					show: true
				},
				grid:
				{
					hoverable: true
				},
				xaxis: 
				{ 
					max: (self.seconds-self.startSeconds>=self.maxSeconds) ? null : self.maxSeconds+self.startSeconds,
					tickSize: 60,
					tickFormatter: xTick
			 	},
			  	yaxis: 
			  	{ 
			  		min: 0,
				  	minTickSize: 5*1024,
	  				tickFormatter: function(n) { return(ffs(n) + "/" + WUILang.s) } 
		  		}
			});

			function showTooltip(x, y, contents)
			{
        			$('<div id="tooltip">' + contents + '</div>').css( {
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #fdd',
					padding: '2px',
					'background-color': '#fee',
					'color': 'black',
					'font-size': '11px',
					'font-weight': 'bold',
					'font-family': 'Tahoma, Verdana, Arial, Helvetica, sans-serif',
					opacity: 0.80
				}).appendTo("body").fadeIn(200);
			}

			$("#"+self.owner.id).bind("plothover", 
				function (event, pos, item) 
				{ 
					if(item)
					{
						if(previousSpeedPoint != item.datapoint)
						{
							previousSpeedPoint = item.datapoint;
							$("#tooltip").remove();
							showTooltip(item.pageX, item.pageY,
								item.series.label + " " + xTick(item.datapoint[0]) + " = " + ffs(item.datapoint[1]) + "/" + WUILang.s);
						}
					}
					else
					{
						$("#tooltip").remove();
						previousSpeedPoint = null;
					}
				}
			);

		}
	}
	);
}

rSpeedGraph.prototype.resize = function( newWidth, newHeight )
{
	if(this.owner)
	{
		if(newWidth)
			this.owner.style.width = newWidth+"px";
		if(newHeight)
			this.owner.style.height = newHeight+"px";
		this.draw();
	}
}

rSpeedGraph.prototype.addData = function( upSpeed, downSpeed )
{
	this.seconds = new Date().getTime()/1000;
	this.up.data.push([this.seconds,upSpeed]);
	this.down.data.push([this.seconds,downSpeed]);
	while((this.down.data[this.down.data.length-1][0]-this.down.data[0][0])>this.maxSeconds)
	{
		this.down.data.shift(); 
		this.up.data.shift();
	}		
	this.draw();
}
