// Pokémon Vortex local-install compatibility helpers.
var PV_LEGACY_BASE = (function () {
    if (typeof window.PV_BASE === 'string') return window.PV_BASE.replace(/\/$/, '');
    var marker = '/html/static/';
    var scripts = document.getElementsByTagName('script');
    for (var i = scripts.length - 1; i >= 0; i--) {
        var src = scripts[i].src || '';
        var pos = src.indexOf(marker);
        if (pos >= 0) {
            try { return new URL(src, window.location.href).pathname.substring(0, pos).replace(/\/$/, ''); } catch (e) {}
        }
    }
    var path = window.location.pathname || '/';
    var slash = path.lastIndexOf('/');
    return slash > 0 ? path.substring(0, slash) : '';
})();
window.PV_BASE = window.PV_BASE || PV_LEGACY_BASE;
window.PV_STATIC_BASE = window.PV_STATIC_BASE || (PV_LEGACY_BASE + '/html/static');
var pvLegacyUrl = function (url) {
    url = String(url || '');
    if (!url || /^(?:[a-z]+:|#|data:|blob:)/i.test(url)) return url;
    if (url.indexOf('//') === 0) return url;
    if (url.charAt(0) === '/') return PV_LEGACY_BASE + url;
    return PV_LEGACY_BASE + '/' + url.replace(/^\.\//, '');
};

//------------------------------------- Misc Functions -------------------------------------//

	var targetParent = function(atag)
	{
		if (!(window.focus && window.opener))
		{
			return true;
		}
		
		window.opener.focus();
		
		window.opener.location.href = atag.href;
		
		window.close();
		
		return false;
	}
	
	var addLoadEvent = function(func)
	{
		var oldOnload = window.onload;
		if (typeof window.onload != 'function')
		{
			window.onload = func;
		}
		else
		{
			window.onload = function()
			{
				if (oldOnload)
				{
					oldOnload();
				}
				func();
			}
		}
	}

	var addResizeEvent = function(func)
	{
		var oldOnresize = window.onresize;
		if (typeof window.onresize != 'function')
		{
			window.onresize = func;
		}
		else
		{
			window.onresize = function()
			{
				if (oldOnresize)
				{
					oldOnresize();
				}
				func();
			}
		}
	}

	var disableSubmitButton = function(form)
	{
		for (i = 0; i < form.elements.length; i++)
		{
			if (form.elements[i].type == 'submit')
			{
				form.elements[i].disabled = true;
				form.elements[i].style.color = '#666666';
				form.elements[i].value = 'Please Wait...';
			}
		}
		
		return true;
	}	
	
	var fixStyles = function()
	{
		var inputElements = document.getElementsByTagName('input');
		
		for (i = 0; i < inputElements.length; i++)
		{
			if (inputElements[i].type == 'radio' || inputElements[i].type == 'checkbox')
			{
				inputElements[i].style.background = 'none';
				inputElements[i].style.border = 'none';
			}
		}
	}
	

	
	var popup = function(url)
	{
		var PopupWindow = this.open(url, "PopupWin", "toolbar=no,location=no,scrollbars=yes,status=yes,resize=yes,width=725,height=500");
	}
	
	//------------------------------------- Notification functions -------------------------------------//
	

	
	
	var getPosition = function(subject)
	{
		var container = document.getElementById('container');
		var content = document.getElementById('content');
		
		var selectedPosX = 0;
		var selectedPosY = 0;
		
		var theElement = subject;
		var theElementHeight = theElement.offsetHeight;
		var theElementWidth = theElement.offsetWidth;
		
		while (theElement != null)
		{
			selectedPosX += theElement.offsetLeft;
			selectedPosY += theElement.offsetTop;
			theElement = theElement.offsetParent;
		}
		
		selectedPosX = selectedPosX - container.offsetLeft - content.offsetLeft;
		selectedPosY = selectedPosY - 152;
	
		return { x: selectedPosX, y: selectedPosY, height: theElementHeight, width: theElementWidth };
	}
	
	var showDetailsTimeout;
	var detailsBoxTop = new Image;
	detailsBoxTop.src = 'html/static/images/whiteout.gif';
	var detailsBoxBottom = new Image;
	detailsBoxBottom.src = 'html/static/images/whiteout.gif';
	var detailsBoxClose = new Image;
	detailsBoxClose.src = 'html/static/images/whiteout.gif';

	function showDetails(domElementID, subject, flag)
	{
		if (flag)
		{
			detailsBox = document.getElementById('showDetails');
			
			var position = getPosition(document.getElementById(subject));
						
			detailsBox.innerHTML = '<div id="showDetailsTop"></div><div id="showDetailsContent">' + document.getElementById(domElementID).innerHTML + '</div><div id="showDetailsClose"  onclick="hideDetails();"></div>';
			
			detailsBox.style.display = 'block';
		
			detailsBoxContent = document.getElementById('showDetailsContent');
		
			if (window.ActiveXObject)
			{
				detailsBoxContent.style.height = 'auto';
				
				if (detailsBoxContent.offsetHeight > 200)
				{
					detailsBoxContent.style.height = '200px';
				}
			}
		
			var detailsBoxHeight = detailsBox.offsetHeight;
			var detailsBoxWidth = detailsBox.offsetWidth;
								
			detailsBox.style.left = position.x + 'px';
					
			detailsBox.style.top = position.y + position.height - 5 + 'px';
			
			if (typeof(window.pageYOffset) == 'number')
			{
				var scrollHeight = window.pageYOffset;
			}
			else if (document.documentElement && document.documentElement.scrollTop)
			{
				var scrollHeight = document.documentElement.scrollTop;
			}
			else
			{
				var scrollHeight = document.body.scrollTop;
			}
			
			
			if (typeof(window.innerWidth) == 'number')
			{
				var clientHeight = window.innerHeight;
				var clientWidth = window.innerWidth;
			}
			else if (document.documentElement && document.documentElement.clientHeight)
			{
				var clientHeight = document.documentElement.clientHeight;
				var clientWidth = document.documentElement.clientWidth;
			}
			else
			{
				var clientHeight = document.body.clientHeight;
				var clientWidth = document.body.clientWidth;
			}
					
			if ((scrollHeight + clientHeight) < (position.y + position.height + 5 + detailsBoxHeight))
			{
				detailsBox.innerHTML = '<div id="showDetailsClose" onclick="hideDetails();"></div><div id="showDetailsContent">' + document.getElementById(domElementID).innerHTML + '</div><div id="showDetailsBottom"></div>';
				
				detailsBoxContent = document.getElementById('showDetails_content');
				
				if (window.ActiveXObject)
				{
					detailsBoxContent.style.height = 'auto';
					
					if (detailsBoxContent.offsetHeight > 200)
					{
						detailsBoxContent.style.height = '200px';
					}
				}
				
				detailsBox.style.top = position.y - detailsBoxHeight + 5 + 'px';
			}
						
			/*
			if (clientWidth < (position.x + theElementWidth + detailsBoxWidth))
			{
				detailsBox.style.left = position.x - detailsBoxWidth + theElementWidth + "px";
			}
			*/
			
			detailsBox.style.visibility = 'visible';
		}
		else
		{
			showDetailsTimeout = setTimeout("showDetails('" + domElementID + "', '" + subject + "', 1)", 500);
		}
	}

	function hideDetails()
	{
		var detailsBox = document.getElementById('showDetails');
		
		detailsBox.style.visibility = 'hidden';
		detailsBox.style.display = 'none';
		
		detailsBox.innerHTML = '';
		
		detailsBox.style.left = 0;
		detailsBox.style.top = 0;
		
		clearTimeout(showDetailsTimeout);
		showDetailsTimeout = false;
	}
	
	//------------------------------------- Content resize function -------------------------------------//

	var resizeObject = function(objectName, offset)
	{
		var contentScroll = document.getElementById(objectName);
		
		if (typeof(window.innerHeight) == 'number') // Firefox, Safari, etc.
		{
			var windowHeight = window.innerHeight;
		}
		else if (document.documentElement && document.documentElement.clientHeight) // IE6+
		{
			var windowHeight = document.documentElement.clientHeight;
		}
		else if (document.body && document.body.clientHeight) // IE5-
		{
			var windowHeight = document.body.clientHeight;
		}
		
		var selectedPosX = 0;
		var selectedPosY = 0;
		
		var theElement = contentScroll;
		
		while (theElement != null)
		{
			selectedPosX += theElement.offsetLeft;
			selectedPosY += theElement.offsetTop;
			theElement = theElement.offsetParent;
		}
						
		if (!offset)
		{
			offset = 0;
		}
		
		contentScroll.style.height = windowHeight - selectedPosY - offset + 'px';
	}

	//------------------------------------- Alert Functions -------------------------------------//

	var showAlert = function(alertString)
	{
		if (alertString)
		{
			var selectElements = document.getElementsByTagName('select');
			
			if (window.ActiveXObject)
			{
				for (i = 0; i < selectElements.length; i++)
				{
					selectElements[i].style.visibility = "hidden";
				}
				
				document.getElementById('alert').style.top = document.documentElement.scrollTop + 100 + 'px';
			}
			
			document.getElementById('alert').style.display = "block";
			document.getElementById('alert').innerHTML = alertString;
			
			if (document.getElementById('alertFocus'))
			{
				document.getElementById('alertFocus').focus();
			}
		}
	}
	
	var removeAlert = function()
	{
		if (window.ActiveXObject)
		{
			for (i = 0; i < selectElements.length; i++)
			{
				selectElements[i].style.visibility = "visible";
			}
		}
		
		document.getElementById('alert').style.display = "none";
		document.getElementById('alert').innerHTML = "";
	}

	var showErrorTimeout;
	var errorBoxBottom = new Image;
	errorBoxBottom.src = 'html/static/images/whiteout.gif';
	
	var showError = function(errorStr, domElementID, subject, flag)
	{
		if (flag)
		{
			var errorBox = document.getElementById(domElementID);
			var subjectBox = document.getElementById(subject);
			
			var container = document.getElementById('container');
			
			var position = getPosition(subjectBox);
			
			errorBox.innerHTML = '<div class="errorBoxContainer"><div class="errorBoxContents"><strong>Error:</strong> ' + errorStr + '</div><div class="errorBoxBottom"></div></div>';
			
			errorBox.style.width = '250px';
			
			errorBox.style.visibility = 'hidden';
			errorBox.style.display = 'block';

			var errorBoxHeight = errorBox.offsetHeight;
			var errorBoxWidth = errorBox.offsetWidth;
			
			errorBox.style.left = position.x + 'px';
					
			errorBox.style.top = position.y - errorBoxHeight + 6 + 'px';
						
			errorBox.style.visibility = 'visible';
			
			subjectBox.style.border = '1px solid #E6524F';			
		}
		else
		{
			showErrorTimeout = setTimeout("showError('" + errorStr + "', '" + domElementID + "', '" + subject + "', 1)", 500);
		}
	}
	
	var hideError = function(domElementID, subject)
	{
		var errorBox = document.getElementById(domElementID);
		var subjectBox = document.getElementById(subject);
		
		errorBox.style.left = 0;
		errorBox.style.top = 0;
		errorBox.style.visibility = 'hidden';
		errorBox.style.display = 'none';
		
		subjectBox.style.border = '1px solid #666666';
		
		clearTimeout(showErrorTimeout);
		showErrorTimeout = false;
	}

	//------------------------------------- Ajax Request Class -------------------------------------//
	
	var AjaxRequest = function()
	{
		 if (!this.xmlhttp)
		 {
			try
			{
				// Try to create object for Firefox, Safari, IE7, etc.
				this.xmlhttp = new XMLHttpRequest();
			}
			catch (e)
			{
				try
				{
					// Try to create object for later versions of IE.
					this.xmlhttp = new ActiveXObject('MSXML2.XMLHTTP');
				}
				catch (e)
				{
					try
					{
						// Try to create object for early versions of IE.
						this.xmlhttp = new ActiveXObject('Microsoft.XMLHTTP');
					}
					catch (e)
					{
						// Could not create an XMLHttpRequest object.
						return false;
					}
				}
			}
		}		
		
		this.method = 'post';
		this.async = true;
		this.url;
		this.query = '';
		this.data = '';
		this.reponseText;
		this.reponseXML;
		
		this.responseHandler;
		this.abortHandler;
		
		this.showLoading = false;
		
		this.send = function()
		{
			if (this.method && this.url)
			{				
				var self = this;
				
				this.xmlhttp.onreadystatechange = function()
				{
					if (self.xmlhttp.readyState == 4)
					{
						if (self.xmlhttp.status && (self.xmlhttp.status == 200 || self.xmlhttp.status == 304))
						{
							self.responseText = self.xmlhttp.responseText;
							
							if (self.xmlhttp.responseXML)
							{
								self.responseXML = self.xmlhttp.responseXML;
							}
							else
							{
								self.responseXML = null;
							}
							
							if (self.responseHandler)
							{
								self.responseHandler();
							}
						}
						else
						{
							showAlert('<p>An error occured while requesting the data.</p><p>Status Msg: '+self.xmlhttp.statusText+'</p><p><input type="button" name="ok" value="OK" onclick="removeAlert();" id="alertFocus"></p>');
						}
						
						if (self.showLoading && self.loading)
						{
							self.loading.style.visibility = 'hidden';
						}
					}
				}
				
				if (this.showLoading)
				{
					this.displayLoading();
				}		
				
				this.xmlhttp.open(this.method, pvLegacyUrl(this.url) + '?' + encodeURI(this.query), this.async);
				
				if (this.method == 'post')
				{
					this.xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				}
				
				this.xmlhttp.send(encodeURI(this.data));
			}
			else
			{
				showAlert("<p>An error occured while requesting the data.</p><p>No method, URL, and/or query string provided.</p><p><input type=\"button\" name=\"ok\" value=\"OK\" onclick=\"removeAlert();\" id=\"alertFocus\"></p>");
			}
		}
		
		this.abort = function()
		{
			this.xmlhttp.onreadystatechange = function() {};
			
			this.xmlhttp.abort();
			
			if (this.abortHandler)
			{
				this.abortHandler();
			}
		}
		
		this.getFormValues = function(form)
		{
			for (i = 0; i < form.elements.length; i++)
			{
				switch (form.elements[i].type)
				{
					case 'text': 
					case 'hidden': 
					case 'password': 
					case 'textarea': 
						this.data += form.elements[i].name + "=" + form.elements[i].value + "&";
						break;
		
					case 'checkbox':  
					case 'radio':  
						if (form.elements[i].checked)
						this.data += form.elements[i].name + "=" + form.elements[i].value + "&";
						break;
		
					case 'select-one':
						this.data += form.elements[i].name + "=" + form.elements[i].options[form.elements[i].selectedIndex].value + "&";
						break;
				}
				
			}
			
			this.data = this.data.substr(0, (this.data.length - 1));
		}
		
		this.appendHTML = function(object, flag)
		{
			if (this.xmlhttp.responseText)
			{
				if (flag)
				{
					object.innerHTML = this.responseText;
				}
				else
				{
					object.innerHTML += this.responseText;
				}
			}
			else
			{
			
			}
		}
	
		this.displayLoading = function()
		{
			if (this.showLoading == 'sidebar')
			{
				this.loading = document.getElementById('sidebarLoading');
				
				this.loading.style.height = document.getElementById('sidebar').offsetHeight - 2 + 'px';
				
				this.loading.style.width = document.getElementById('sidebarContent').offsetWidth + 'px';
				
				this.loading.innerHTML = '<p style="text-align: center; margin-top: 150px;"><img src="html/static/images/loading.gif" width="100" height="100" alt="Loading..." /></p>';
			}
                        else if (this.showLoading == 'message') // message
			{
				this.loading = document.getElementById('messageContent');
				
				this.loading.style.height = document.getElementById('message').offsetHeight + 'px';
				
				this.loading.style.width = document.getElementById('message').offsetWidth + 'px';
				
				this.loading.innerHTML = '<p style="text-align: center; margin-top: 75px;"><img src="html/static/images/loading.gif" width="100" height="100" alt="Loading..." /></p>';
			}
			else if (this.showLoading == 'messageList') // message list
			{
				this.loading = document.getElementById('messageList');
				
				this.loading.style.height = document.getElementById('messageList').offsetHeight + 'px';
				
				this.loading.style.width = document.getElementById('messageList').offsetWidth + 'px';
				
				this.loading.innerHTML = '<p style="text-align: center; margin-top: 50px;"><img src="html/static/images/loading.gif" width="100" height="100" alt="Loading..." /></p>';
			}

			else if (this.showLoading == 'map') // map
			{
				this.loading = document.getElementById('mapLoading')
				
				this.loading.innerHTML = '<p style="text-align: center; margin-top: 150px;"><img src="html/static/images/loading_white.gif" width="100" height="100" alt="Loading..." /></p>';
			}

                        else if (this.showLoading == 'live')
			{
				this.loading = document.getElementById('loading');
	
				this.loading.style.height = document.getElementById('scroll').offsetHeight + 'px';
				
				if (document.getElementById('scrollContent'))
				{
					this.loading.style.width = document.getElementById('scrollContent').offsetWidth + 'px';
				}
				else
				{
					this.loading.style.width = document.getElementById('scroll').offsetWidth + 'px';
				}
				
				this.loading.innerHTML = '<p class="large" style="margin-top: 75px; text-align: center;"><strong>Waiting for the other user to respond...</strong></p><p style="text-align: center;">You have been waiting <span id="waitTime">0 seconds</span>.</p>';
				
				waitTime(0);
			}
			else // main
			{
				this.loading = document.getElementById('loading');
	
				this.loading.style.height = document.getElementById('scroll').offsetHeight + 'px';
								
				if (document.getElementById('scrollContent'))
				{
					this.loading.style.width = document.getElementById('scrollContent').offsetWidth + 'px';
				}
				else
				{
					this.loading.style.width = document.getElementById('scroll').offsetWidth + 'px';
				}
				
				this.loading.innerHTML = '<p style="text-align: center; margin-top: 150px;"><img src="html/static/images/loading.gif" width="100" height="100" alt="Loading..." /></p>';
			}
								
			this.loading.style.visibility = 'visible';
		}
	}

	//------------------------------------- Element Movement Fuctions -------------------------------------//

	var slideTimeout = new Array();

	var objectSlide = function(objectID, x, y, increment)
	{
		var object = document.getElementById(objectID);
		
		if (object.offsetTop != y)
		{
			if (object.offsetTop > y)
			{
				if (object.offsetTop - increment < y)
				{
					object.style.top = y + "px";
				}
				else
				{
					object.style.top = object.offsetTop - increment + "px";
				}
			}
			else
			{
				if (object.offsetTop + increment > y)
				{
					object.style.top = y + "px";
				}
				else
				{
					object.style.top = object.offsetTop + increment + "px";
				}
			}
		}
		
		if (object.offsetLeft != x)
		{
			if (object.offsetLeft > x)
			{
				if (object.offsetLeft - increment < x)
				{
					object.style.left = x + "px";
				}
				else
				{
					object.style.left = object.offsetLeft - increment + "px";
				}
			}
			else
			{
				if (object.offsetLeft + increment > x)
				{
					object.style.left = x + "px";
				}
				else
				{
					object.style.left = object.offsetLeft + increment + "px";
				}
			}
		}
		
		if (object.offsetTop != y || object.offsetLeft != x)
		{
			slideTimeout[objectID] = setTimeout("objectSlide('"+objectID+"', "+x+", "+y+", "+increment+")", 30);
		}
		else
		{
			clearTimeout(slideTimeout[objectID]);
			
			slideTimeout[objectID] = 0;
		}
	}

	//------------------------------------- Ajax content request functions -------------------------------------//

	
	var regget = [];
	var get = function(url, query, form)
	{

		var scrollingContainer = document.getElementById('scroll');
		var content = document.getElementById('ajax');
		var request = new AjaxRequest();
		request.url = url;
		request.query = query + '&ajax=1';
		request.showLoading = 'main';
		
		if (form) {
			request.getFormValues(form);
			disableSubmitButton(form);
		}
		
		request.responseHandler = function(){

			if (this.responseText){				
				content.innerHTML = '';
				content.innerHTML = this.responseText;
				for (var i=0;i<regget.length;i++) {try{regget[i](url,query,form,this.responseText);}catch(x){}}
			}

			scrollingContainer.scrollTop = 0;
			fixStyles();
		}

		request.send();
	}

	

	var removeSidebarContent = true;
	var sidebarSlideDistance = 472;
	var browser = '';

	var showSidebar = function(focusTab, flag)
	{
		var sidebar = document.getElementById('sidebar');
		
		var sidebarContent = document.getElementById('sidebarContent');
		
		var sidebarTabs = document.getElementById('sidebarTabs').getElementsByTagName('a');
		
		if (removeSidebarContent)
		{
			sidebarContent.innerHTML = '<div id="optionsTabContent" class="tabContent"></div><div id="pokedexTabContent" class="tabContent"></div><div id="membersTabContent" class="tabContent"></div>';
			
			removeSidebarContent = false;
		}
		
		if (browser == 'ie7+')
		{
			objectSlide('sidebar', 0, -2, 50);
		}
		else if (browser == 'ie6-')
		{
			sidebar.style.left = '-40px';
		}
		else
		{
			sidebar.style.left = '0px';
		}
		
		//objectSlide('sidebar', 0, -2, 50);
		
		for (i = 0; i < sidebarTabs.length; i++)
		{
			if (sidebarTabs[i].id == focusTab.id)
			{
				sidebarTabs[i].className = 'selected';
				
				document.getElementById(sidebarTabs[i].id + 'Content').style.display = 'block';
				
				sidebarTabs[i].onclick = function()
				{
					hideSidebar();
					
					return false;
				}
			}
			else
			{
				sidebarTabs[i].className = 'deselected';
				
				document.getElementById(sidebarTabs[i].id + 'Content').style.display = 'none';
				
				sidebarTabs[i].onclick = function()
				{
					showSidebar(this, 1);
					
					return false;
				}
			}
		}
		
		if (document.getElementById(focusTab.id + 'Content').innerHTML == '' && flag)
		{	
			if (focusTab.id == 'optionsTab')
			{
				var fileName = 'options.php';
			}
			else if (focusTab.id == 'pokedexTab')
			{
				var fileName = 'pokedex.php';
			}
			else
			{
				var fileName = 'members.php';
			}
			
			getSidebar(fileName, '', focusTab.id, 0);
		}
	}
	
	var hideSidebar = function()
	{
		var sidebar = document.getElementById('sidebar');
		
		var sidebarContent = document.getElementById('sidebarContent');
		
		var sidebarTabs = document.getElementById('sidebarTabs').getElementsByTagName('a');
		
		for (i = 0; i < sidebarTabs.length; i++)
		{
			sidebarTabs[i].className = 'deselected';
			
			sidebarTabs[i].onclick = function()
			{
				showSidebar(this, 1);
				
				return false;
			}
		}
		
		sidebarContent.innerHTML = '';
		
		//objectSlide('sidebar', -472, -2, 50);
		
		if (browser == 'ie7+')
		{
			objectSlide('sidebar', -472, -2, 50);
		}
		else if (browser == 'ie6-')
		{
			sidebar.style.left = '-512px';
		}
		else
		{
			sidebar.style.left = '-472px';
		}
				
		removeSidebarContent = true;
	}
	
	var getSidebar = function(url, query, focusTabID, flag, form)
	{
		var sidebar = document.getElementById('sidebar');
		
		var sidebarContent = document.getElementById('sidebarContent');
		
		var focusTab = document.getElementById(focusTabID);
		
		if (flag)
		{
			showSidebar(focusTab, 0);
		}
		
		var tabContent = document.getElementById(focusTab.id + 'Content');
		

		var request = new AjaxRequest();
		
		request.url = 'tabs/' + String(url || '').replace(/^\/+/, '');
		request.query = query + '&ajax';
		request.showLoading = 'sidebar';
		
		if (form)
		{
			request.getFormValues(form);
			
			disableSubmitButton(form);
		}
		
		request.responseHandler = function()
		{

	
			if (this.responseText)
			{				
				document.getElementById(focusTabID + 'Content').innerHTML = '';
					document.getElementById(focusTabID + 'Content').innerHTML = this.responseText;
                                        
                                        
			}
	

				
				
			
			
			
			sidebarContent.scrollTop = 0;
			
			fixStyles();	
		}
		
		request.send();
		
	}
	
	var pokedexTab = function(query, flag)
	{
		getSidebar('pokedex.php', query, 'pokedexTab', flag);
	}
	
	var membersTab = function(query, flag)
	{
		getSidebar('members.php', query, 'membersTab', flag);
	}
	
	var optionsTab = function(query, flag)
	{
		getSidebar('options.php', query, 'optionsTab', flag);
	}
	var getBadges = function(userid)
	{
		var request = new AjaxRequest();
		request.url = 'tabs/badges.php';
		
		request.query = 'myid=' + userid;
		
		
			request.responseHandler = function()
		{

	
			if (this.responseText)
			{				
					var badgeEle = document.getElementById('badges')
					if (badgeEle) badgeEle.innerHTML = this.responseText;
                                        
                                        
			}
		}
 	request.send();
	}

//--------------------------------- Countdown Function if needed --------------------------------------//

var count=25200;

var counter=setInterval(timer, 1000); //1000 will  run it every 1 second

function timer()
{
  count=count-1;
  if (count <= 0)
  {
     clearInterval(counter);
     //counter ended, do something here
     return;
  }

  document.getElementById("timer").innerHTML=count + " seconds";
}
