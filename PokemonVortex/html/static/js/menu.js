var showMenuTimeout;
	var hideMenuTimeout;
	var previousTabClass = '';

	var menuHTML = new Array();
	var menuWidth = new Array();
	
	menuHTML['maps'] = '<p>Select a map region to explore it:</p><table cellspacing="7" cellpadding="0"><tr><td rowspan="3"><table class="mapSelect" cellspacing="1" cellpadding="0" style="width: 152px;"><tr><td><a href="map.php?map=1"><img src="html/static/images/minimap1.png" width="50" height="42" /></a></td><td><a href="map.php?map=4"><img src="html/static/images/minimap4.png" width="50" height="42" /></a></td><td><a href="map.php?map=7"><img src="html/static/images/minimap7.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=2"><img src="html/static/images/minimap2.png" width="50" height="42" /></a></td><td><a href="map.php?map=5"><img src="html/static/images/minimap5.png" width="50" height="42" /></a></td><td><a href="map.php?map=8"><img src="html/static/images/minimap8.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=3"><img src="html/static/images/minimap3.png" width="50" height="42" /></a></td><td><a href="map.php?map=6"><img src="html/static/images/minimap6.png" width="50" height="42" /></a></td><td><a href="map.php?map=9"><img src="html/static/images/minimap9.png" width="50" height="42" /></a></td></tr></table></td></tr><tr><td><table cellspacing="1" cellpadding="0" class="mapSelect" style="width: 103px;"><tr><td><a href="map.php?map=10"><img src="html/static/images/minimap10.png" width="50" height="42" /></a></td><td><a href="map.php?map=13"><img src="html/static/images/minimap13.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=11"><img src="html/static/images/minimap11.png" width="50" height="42" /></a></td><td><a href="map.php?map=14"><img src="html/static/images/minimap14.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=12"><img src="html/static/images/minimap12.png" width="50" height="42" /></a></td><td><a href="map.php?map=15"><img src="html/static/images/minimap15.png" width="50" height="42" /></a></td></tr></table></td><td><table cellspacing="1" cellpadding="0" class="mapSelect" style="width: 52px;"><tr><td><a href="map.php?map=23"><img src="html/static/images/minimap23.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=22"><img src="html/static/images/minimap22.png" width="50" height="42" /></a></td></tr></table></td></tr></td></table><table cellspacing="7" cellpadding="0"><tr><td rowspan="3"><td><table cellspacing="1" cellpadding="0" class="mapSelect" style="width: 102px;"><tr><td><a href="map.php?map=18"><img src="html/static/images/minimap18.png" width="50" height="42" /></a></td><td><a href="map.php?map=20"><img src="html/static/images/minimap20.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=19"><img src="html/static/images/minimap19.png" width="50" height="42" /></a></td><td><a href="map.php?map=21"><img src="html/static/images/minimap21.png" width="50" height="42" /></a></td></tr></table></td><td><table cellspacing="1" cellpadding="0" class="mapSelect" style="width: 50px;"><tr><td><a href="map.php?map=24"><img src="html/static/images/minimap25.png" width="50" height="42" /></a></td></tr><tr><td><a href="map.php?map=25"><img src="html/static/images/minimap24.png" width="50" height="42" /></a></td></tr></table></td><td><table cellspacing="1" cellpadding="0" class="mapSelect" style="width: 102px;"><tr><td><a href="map.php?map=16"><img src="html/static/images/minimap16.png" width="50" height="42" /></a></td><td><a href="map.php?map=17"><img src="html/static/images/minimap17.png" width="50" height="42" /></a></td></tr></table></table></td></tr></td></table><ul><li><a href="map_select.php">View Larger Map Select</a></li><li><a href="community.php">World Map Guide</a></li><li><a href="map.php">Return to Map</a></li></ul>';
	
menuWidth['maps'] = 400;

	menuHTML['community'] = '<ul><li><a href="community.php">Trainer Community</a></li><li><a href="community.php">Trainer Community</a></li><li><a href="community.php">Trainer Community</a></li><li><a href="community.php">Trainer Community</a></li><li><a href="community.php">Trainer Community</a></li></ul>';
	menuWidth['community'] = 200;

	menuHTML['battle'] = '<p><img src="html/static/images/misc/gym.gif" width="112" height="90" alt="Gym" /></p><ul><li><a href="battle_select.php?type=gym">Battle in a Gym</a></li><li><a href="sidequest.php">Sidequests</a><ul><li><a href="battle_select.php?type=frontier">Battle Frontier</a></li><ul><li><a href="battle_select.php?type=event">Events</a></li></li><li><a href="battle_select.php?type=member">Battle Any Member<br />(Computer Controlled)</a></li></ul>';
	menuWidth['battle'] = 200;
	
	menuHTML['yourAccount'] = '<ul><li><a href="your_team.php">Your Pok&eacute;mon Team</a></li><li><a href="your_pokemon.php">View All Your Pok&eacute;mon</a></li><li><a href="trade.php">Trade Pok&eacute;mon</a></li><li><a href="items.php">Pok&eacute;mart</a></li><li><a href="your_profile.php">View/Edit Your Profile</a></li><li><a href="messages.php">Messages</a></li><li><a href="dashboard.php">Your Dashboard</a></li></ul>';
	menuWidth['yourAccount'] = 200;

	var showMenu = function(menu, flag)
	{
		if (flag)
		{
			hideMenu(1);
			
			var menuBox = document.getElementById('menuBox');
			var menuTab = document.getElementById(menu + 'Tab');
			
			var selectedPosX = 0;
			var selectedPosY = 0;
			
			var theElement = menuTab;
			
			while (theElement != null)
			{
				selectedPosX += theElement.offsetLeft;
				selectedPosY += theElement.offsetTop;
				theElement = theElement.offsetParent;
			}
			
			//menuBox.innerHTML = '<div id="menuContents">' + menuHTML[menu] + '</div>';
			
			menuBox.innerHTML = menuHTML[menu];
			
			menuBox.style.width = menuWidth[menu] + 'px';
			
			var menuContents = document.getElementById('menuContents');
			
			menuBox.onmouseover = cancelHideMenu;
			
			menuBox.onmouseout = function()
			{
				hideMenu(0);
			}
			
			menuTab.className += ' active';
			
			menuBox.style.left = selectedPosX - Math.round(menuBox.offsetWidth / 2 - menuTab.offsetWidth / 2) + 'px';
			
			menuBox.style.top = selectedPosY + menuTab.offsetHeight - 2 + 'px';
			
			menuBox.style.visibility = 'visible';
		}
		else
		{
			showMenuTimeout = setTimeout("showMenu('" + menu + "', 1)", 250);
		}
		
		cancelHideMenu();
	}

	var hideMenu = function(flag)
	{
		var menuBox = document.getElementById('menuBox');
		
		var navTabs = document.getElementById('nav').getElementsByTagName('a');
				
		if (flag)
		{
			for (i = 0; i < navTabs.length; i++)
			{
				var navTabClass = navTabs[i].className.split(' ');
				
				navTabs[i].className = navTabClass[0];
			
			}
			
			menuBox.style.visibility = 'hidden';
			menuBox.style.left = 0;
			menuBox.style.top = 0;
		}
		else
		{
			clearTimeout(showMenuTimeout);
			showMenuTimeout = false;
			
			hideMenuTimeout = setTimeout("hideMenu(1)", 500);
		}
	}

	var cancelHideMenu = function()
	{
		clearTimeout(hideMenuTimeout);
		hideMenuTimeout = false;
	}

