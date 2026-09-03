	fixStyles();

	var onResizeHome = function()
	{
		// Document scrolling is handled by the modern responsive shell.
		
		if (browser == 'ie6-')
		{
			var container = document.getElementById('container');
			
			if (document.documentElement && document.documentElement.clientWidth) // IE6+
			{
				var windowWidth = document.documentElement.clientWidth;
			}
			else if (document.body && document.body.clientWidth) // IE5-
			{
				var windowWidth = document.body.clientWidth;
			}
			
			if (windowWidth > 1030)
			{
				container.style.width = '1030px';
			}
			else
			{
				container.style.width = 'auto';
			}
		}
	}
	
	addResizeEvent(onResizeHome);
	
	addLoadEvent(onResizeHome);
	
	onResizeHome();

	var loadingBlack = new Image;
	loadingBlack.src = 'html/static/images/loading_black.gif';
	
	var loadingWhite = new Image;
	loadingWhite.src = 'html/static/images/loading_white.gif';
	
	var overshadow = new Image;
	overshadow.src = 'html/static/images/overshadow.png';
	
	var whiteout = new Image;
	whiteout.src = 'html/static/images/whiteout.png';

	var grayout = new Image;
	grayout.src = 'html/static/images/grayout.png';
