var currentSuggestionHighlight = 0;
	var lastQueriedString = '';
	var suggestTimeout;
	var hideSuggestTimeout;
	var textFieldName;
	var textField;
	var lastTextField;
	var suggestion;
	var skipCheck;
	
	var suggest = function(textFieldName, table, flag)
	{
		var suggestResults = document.getElementById('suggestResults');
		
		textField = document.getElementById(textFieldName);
		
		if (lastTextField != textField)
		{
			clearTimeout(hideSuggestTimeout);
			hideSuggestTimeout = false;
			
			clearTimeout(suggestTimeout);
			suggestTimeout = false;
			
			lastQueriedString = '';
			
			lastTextField = textField;
		}
				
		if ((textField.value.length > 0) && (textField.value != lastQueriedString))
		{
			var request = new AjaxRequest();
			
			request.url = 'xml/suggest.php';
			
			request.query = 't=' + table + '&q=' + textField.value;
			
			request.responseHandler = function()
			{
				suggestResults.innerHTML = request.responseText;				
				
				if (textField.value.length > 0)
				{
					var selectedPosX = -225;
					var selectedPosY = -154;
					
					var theElement = textField;
					var theElementHeight = theElement.offsetHeight;
					var theElementWidth = theElement.offsetWidth;
					
					while (theElement != null)
					{
						selectedPosX += theElement.offsetLeft;
						selectedPosY += theElement.offsetTop;
						theElement = theElement.offsetParent;
					}
				
					suggestResults.style.left = selectedPosX + "px";
							
					suggestResults.style.top = selectedPosY + theElementHeight + 2 + "px";
					
					if (window.ActiveXObject)
					{
						var selectElements = document.getElementsByTagName('select');
						
						for (i = 0; i<selectElements.length; i++)
						{
							selectElements[i].style.visibility = "hidden";
						}
					}
					
					suggestResults.style.display = "block";
					
					suggestResults.style.width = textField.offsetWidth + "px";
					
					suggestResults.style.visibility = "visible";
				}
				
				currentSuggestionHighlight = 0;
			}
			
			request.send();
						
			lastQueriedString = textField.value;
		}
		
		if (textField.value.length < 1)
		{
			suggestResults.style.display = "none";
			suggestResults.style.visibility = "hidden";
			suggestResults.style.left = 0;
			suggestResults.style.top = 0;
		}
					
		suggestTimeout = setTimeout("suggest('"+textFieldName+"', '"+table+"', 1)", 1000);
	}
	
	var hideSuggest = function(flag)
	{
		var suggestResults = document.getElementById('suggestResults');
		
		if (flag)
		{
			suggestResults.style.display = "none";
			suggestResults.style.visibility = "hidden";
			suggestResults.style.left = 0;
			suggestResults.style.top = 0;
		}
		else
		{
			hideSuggestTimeout = setTimeout("hideSuggest(1)", 500);
		}
		
		if (window.ActiveXObject)
		{
			var selectElements = document.getElementsByTagName('select');
			
			for (i = 0; i<selectElements.length; i++)
			{
				selectElements[i].style.visibility = "visible";
			}
		}
		
		clearTimeout(suggestTimeout);
		suggestTimeout = false;
		
		lastQueriedString = '';
	}
		
	var suggestionJump = function(e, formName)
	{	
		var suggestResults = document.getElementById('suggestResults');
		
		var nextSuggestion;
		
		var formElement = document.getElementById(formName);
		
		var suggestionArray = new Array();
		
		var suggestionArray = suggestResults.getElementsByTagName('a');
				
		var suggestionLinks = new Array();
				
		for (i = 0; i<suggestionArray.length; i++)
		{
			suggestionLinks[i] = i;
		}
				
		if (window.event) // IE
		{
			keynum = e.keyCode;
		}
		else if (e.which)
		{
			keynum = e.which;
		}
		
		
		if (keynum == 38)
		{
			nextSuggestion = currentSuggestionHighlight - 1;
			
			if(document.getElementById('suggestion_'+nextSuggestion))
			{
				if (document.getElementById('suggestion_'+currentSuggestionHighlight))
				{
					document.getElementById('suggestion_'+currentSuggestionHighlight).className = "suggestion";
				}
				
				currentSuggestionHighlight--;
				
				document.getElementById('suggestion_'+currentSuggestionHighlight).className = "suggestionHighlighted";
			}
		}
		else if (keynum == 40)
		{
			nextSuggestion = currentSuggestionHighlight + 1;
			
			if (document.getElementById('suggestion_'+nextSuggestion))
			{
				if (document.getElementById('suggestion_'+currentSuggestionHighlight))
				{
					document.getElementById('suggestion_'+currentSuggestionHighlight).className = "suggestion";
				}
				
				currentSuggestionHighlight++;
				
				document.getElementById('suggestion_'+currentSuggestionHighlight).className = "suggestionHighlighted";
			}
		}
		else if (keynum == 13) // || keynum == 77)
		{
			if (currentSuggestionHighlight && textField.value != '')
			{
				suggestion = document.getElementById('suggestion_'+currentSuggestionHighlight).title;
				
				selectSuggestion(suggestion);
				
				skipCheck = true;
			}
			else
			{
				//formElement.submit();
			}
		}
		else if (keynum == 27)
		{
			hideSuggest(1);
		}
	}
	
	var selectSuggestion = function(suggestion)
	{
		textField.value = suggestion;
		
		lastQueriedString = suggestion;
		
		currentSuggestionHighlight = 0;
		
		hideSuggest(1);
	}
	
	var submitCheck = function(formName)
	{
		var formElement = document.getElementById(formName);

		if (!currentSuggestionHighlight && !skipCheck)
		{
			formElement.submit();
		}
		else
		{
			skipCheck = false;
		}
	}
