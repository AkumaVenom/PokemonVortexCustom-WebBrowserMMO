function messagefunctions(id,type) {

	if(type == 'read' || type == 'delete' || type == 'forward' || type == 'reply'){
		
		var request = new AjaxRequest();
		request.url = 'tabs/messages.php';
		request.query = type + '=' + id;
		request.showLoading = 'messageContent';
		request.responseHandler = function(){

  			if (this.responseText){

                document.getElementById('messageContent').innerHTML = this.responseText;
                document.getElementById("messageFunctions").innerHTML = '<li id="deletebutton"><a href="messages.php?delete=' + id + '" id="delete" onclick="messagefunctions(\'' + id + '\',\'delete\'); return false;"><em><img src="html/static/images/message/delete.png" title="Delete" alt="Delete"></em></a></li><li id="deletebutton"><a href="messages.php?forward=' + id + '" id="forward" onclick="messagefunctions(\'' + id + '\',\'forwardsent\'); return false;"><em><img src="html/static/images/message/forward.png" title="Forward" alt="Forward"></em></a></li><li id="deletebutton"><a href="messages.php?reply=' + id + '" id="reply" onclick="messagefunctions(\'' + id + '\',\'reply\'); return false;"><em><img src="html/static/images/message/reply.png" title="Reply" alt="Reply"></em></a></li>';
                document.getElementById("open" + id).className = "dark";
				
				if(type == 'delete'){
					
					messageoption('inbox', 'deleted');
					document.getElementById('messageContent').innerHTML = '<div class="actionMsg">Message Deleted</div>';
					document.getElementById("messageFunctions").innerHTML = '<li id="deletebutton"><a href="messages.php" id="delete" onclick="return false;"><em><img src="html/static/images/message/delete.png" title="Delete" alt="Delete"></em></a></li><li id="deletebutton"><a href="messages.php" id="forward" onclick="return false;"><em><img src="html/static/images/message/forward.png" title="Forward" alt="Forward"></em></a></li>';
				}
		 	}
		}
		request.send();
	}
	if(type == 'readsent' || type == 'deletesent' || type == 'forwardsent'){
		
		var request = new AjaxRequest();
		request.url = 'tabs/messages.php';
		request.query = type + '= '+ id;
		request.showLoading = 'messageContent';
		
		request.responseHandler = function(){

  			if (this.responseText){


				document.getElementById('messageContent').innerHTML = request.responseText;
				document.getElementById("messageFunctions").innerHTML = '<li id="deletebutton"><a href="messages.php?delete=' + id + '" id="delete" onclick="messagefunctions(\'' + id + '\',\'deletesent\'); return false;"><em><img src="html/static/images/message/delete.png" title="Delete" alt="Delete"></em></a></li><li id="deletebutton"><a href="messages.php?forward=' + id + '" id="forward" onclick="messagefunctions(\'' + id + '\',\'forwardsent\'); return false;"><em><img src="html/static/images/message/forward.png" title="Forward" alt="Forward"></em></a></li>';
				document.getElementById("open" + id).className = "dark";
				
				if(type == 'deletesent'){
					
					messageoption('sent', 'deleted');
					document.getElementById('messageContent').innerHTML = '<div class="actionMsg">Message Deleted</div>';
					document.getElementById("messageFunctions").innerHTML = '<li id="deletebutton"><a href="messages.php" id="delete" onclick="return false;"><em><img src="html/static/images/message/delete.png" title="Delete" alt="Delete"></em></a></li><li id="deletebutton"><a href="messages.php" id="forward" onclick="return false;"><em><img src="html/static/images/message/forward.png" title="Forward" alt="Forward"></em></a></li>';
				}
			}
		}
		request.send();
	}
}

function messageoption(option, deleted){
	
	document.getElementById("inboxTab").className = "deselected";
	document.getElementById("sentTab").className = "deselected";
	document.getElementById("newMessageTab").className = "deselected";
	document.getElementById(option + "Tab").className = "selected";
	
	if(option == 'newMessage'){
			
		document.getElementById("messageContent").innerHTML = '<div id="newMessage" style="height:300px;"><form method="post" action="messages.php" onsubmit="return disableSubmitButton(this);return false;"><table><tr><td>Username:</td><td><input type="text" id="messageusername" name="messageusername" size="30"></td></tr><tr><td>Title:</td><td><input type="text" id="messagetitle" maxlength="20" name="messagetitle" size="20"></td></tr><tr><td valign="top">Message:</td><td><textarea name="messagetext" id="messagetext" rows="10" cols="60"></textarea></td></tr><tr><td>Save to Sent Items?<input type="checkbox" name="keep" id="keep" value="1"></td><td><input type="submit" name="send" value="Send"></td></tr></table></form></div>';
	}
		
	if(option != 'newMessage'){
			
		function tabs(){
				
			var request = new AjaxRequest();
			request.url = 'tabs/messages.php';
			request.query = 'option='+ option;
			request.showLoading = 'messageList';

			request.responseHandler = function(){

  				if (this.responseText){
		
					var words = '<div id="messageList" style="height:300px;"><ul>' + this.responseText + '</ul></div><div id="message" style="height:300px;overflow-y:scroll;"><div id="messageContent">';
						
					if(deleted == 'deleted'){
							
						words = words + '<div class="actionMsg">Message Deleted</div></div></div>';
					}
					else{
						words = words + '<h2>No Message Selected.</h2>Click on a message subject to the left to view.</div></div>';
					}
					document.getElementById('messageContainer').innerHTML = words;
				} 
			}
			request.send();
		}
		tabs();
	}
}