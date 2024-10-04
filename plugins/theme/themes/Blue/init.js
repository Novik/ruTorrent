plugin.oldAllDone = plugin.allDone;
plugin.allDone = function() {
	plugin.oldAllDone.call(this);

	$('input[type="text"],input[type="password"],input[type="file"],select,textarea').each( function()
	{
		$(this).on('focus', function()
		{
			$(this).css( { "border-color": "#7eadd9" } );
		});
		$(this).on('blur', function()
		{
			$(this).css( { "border-color": "#b5b8c8" } );
		});
	});
}
