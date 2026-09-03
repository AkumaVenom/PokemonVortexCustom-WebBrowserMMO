loadArrows = function(hor,ver,canty,cantx,trainer,map){
var xplus = false;var xminus = false;var yplus = false;var yminus = false;var yminusminus = false;var yminusplus = false;var yplusminus = false;var yplusplus = false;
for(i=0; i<=canty.length; i++) {
if(cantx[i] == (hor + 1) && canty[i] == ver) {var xplus = true;}
if(cantx[i] == (hor - 1) && canty[i] == ver) {var xminus = true;}
if(cantx[i] == hor && canty[i] == (ver + 1)) {var yplus = true;}
if(cantx[i] == hor && canty[i] == (ver - 1)) {var yminus = true;}
if(cantx[i] == (hor - 1) && canty[i] == (ver - 1)) {var yminusminus = true;}
if(cantx[i] == (hor + 1) && canty[i] == (ver - 1)) {var yminusplus = true;}
if(cantx[i] == (hor - 1) && canty[i] == (ver + 1)) {var yplusminus = true;}
if(cantx[i] == (hor + 1) && canty[i] == (ver + 1)) {var yplusplus = true;}
}
if(map == 10 || map == 11 || map == 12 || map == 15 || map == 18 || map == 19 || map == 20 || map == 21){

if(map == 10){if(hor > 10 && hor < 21 && ver == 7 || hor > 20 && hor < 25 && ver == 8){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
if(hor == 21 && ver == 8){
var yminusminus=false;
}
if(hor > 10 && hor < 22 && ver == 6){
var yplusminus = true;
}
if(hor > 10 && hor < 21 && ver == 6){
var yplus = true;
}
if(hor > 9 && hor < 21 && ver == 6){
var yplusplus = true;
}

if(hor > 20 && hor < 25 && ver == 7){
var yplusminus = true; 
}
if(hor > 20 && hor < 25 && ver == 7){
 var yplus = true; 
}
if(hor > 20 && hor < 24 && ver == 7){
var yplusplus = true;
}
if( hor > 8 && hor < 10 && ver == 8){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
if(hor == 10 && ver == 8){
yminus = true;
yminusminus = true;
}

if(hor == 9 && ver == 7 || hor == 8 && ver == 7){
yplus = true;
yplusplus = true;
}


}

if(map == 11){
if(hor > 12 && hor < 16 && ver == 10){
hor == 8 && ver == 7
}
if(hor == 16 && ver == 10 || hor == 10 && ver == 12){
yminusminus = true;
}
if(hor > 12 && hor < 16 && ver == 9)
{
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor == 12 && ver == 9 || hor == 9 && ver == 11){
yplusplus = true;
}
if(hor > 9 && hor < 12 && ver == 10){
yplus = true;
yplusplus = true;
}
if(hor > 10 && hor < 13 && ver == 11){
yminusminus = true;
yminus = true;
}

}
if(map == 12){
if(hor == 25 && ver == 11 || hor == 23 && ver == 9 || hor == 21 && ver == 11){
yplusminus = true;
}
if(hor == 12 && ver == 11 || hor == 8 && ver == 8){
yplusplus = true;
}
if(hor == 8 && ver == 10 || hor == 9 && ver == 9){
yminusminus = true;
}
if(hor == 16 && ver == 10){
yminusplus = true;
}
if(hor > 16 && hor < 23 && ver == 9 || hor > 12 && hor < 21 && ver == 11 || hor > 2 && hor < 8 && ver == 9){
yplusminus = true;
yplus = true;
yplusplus = true;
}

if(hor > 2 && hor < 8 && ver == 10 || hor > 12 && hor < 21 && ver == 12 || hor > 16 && hor < 23 && ver == 10){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
}

if(map == 15){
if(hor == 24 && ver == 11 || hor == 23 && ver == 12 || hor == 25 && ver == 12 || hor == 26 && ver == 13){
yplusminus = true;
}
if(hor == 13 && ver == 11 || hor == 14 && ver == 12 || hor == 12 && ver == 12 || hor == 11 && ver == 13){
yplusplus = true;
}
if(hor == 24 && ver == 13 || hor == 25 && ver == 14){
yminusplus = true;
}
if(hor == 13 && ver == 13 || hor == 12 && ver == 14){
yminusminus = true;
}
if(hor > 13 && hor < 24 && ver == 11 || hor > 14 && hor < 23 && ver == 12){
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor > 13 && hor < 24 && ver == 12 || hor > 14 && hor < 23 && ver == 13){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}

}

if(map == 18){
if(hor == 9 && ver == 10 || hor == 10 && ver == 9){
yminusminus = true;
}
if(hor == 10 && ver == 7 || hor == 9 && ver == 8 || hor == 8 && ver == 9){
yplusplus = true;
}
if(hor > 10 && hor < 31 && ver == 7 ){
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor > 10 && hor < 31 && ver == 8){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
}

if(map == 19){
if(hor == 17 && ver == 14 || hor == 16 && ver == 13 || hor == 15 && ver == 12){
yplusminus = true;
}
if(hor == 14 && ver == 13 || hor == 15 && ver == 14 || hor == 16 && ver == 15 || hor == 17 && ver == 16){
yminusplus = true;
}

if(hor > 17 && hor < 31 && ver == 15 ){
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor > 17 && hor < 31 && ver == 16){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
}
if(map == 20){
if(hor == 13 && ver == 9){
yminusplus = true;
}
if(hor == 14 && ver == 8 || hor == 13 && ver == 7){
yplusminus = true;
}
if(hor == 24 && ver == 12 || hor == 23 && ver == 13 || hor == 22 && ver == 14){
yplusplus = true;
}
if(hor == 24 && ver == 14 || hor == 23 && ver == 15){
yminusminus = true;
}
if(hor > 0 && hor < 13 && ver == 7 || hor > 24 && hor < 29 && ver == 12){
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor > 0 && hor < 13 && ver == 8 || hor > 24 && hor < 29 && ver == 11){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}
}

if(map == 21){
if(hor == 12 && ver == 15 || hor == 13 && ver == 16){
yplusminus = true;
}
if(hor == 12 && ver == 17){
yminusplus = true;
}
if(hor > 0 && hor < 12 && ver == 15){
yplusminus = true;
yplus = true;
yplusplus = true;
}
if(hor > 0 && hor < 12 && ver == 16){
var yminusminus = true;
var yminus = true;
var yminusplus = true;
}

}






}


var moving = "<table border=\"0\"><tr>";
		if(yminusminus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="24" height="25" onClick="AjaxMove(' + (hor - 1) + ',' + (ver - 1) + ', 5);" src="html/static/images/maps/arrows/arrowleftup.gif"></td>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="24" height="25" src="html/static/images/maps/arrows/arrowleftupno.gif"></td>';
	}
	if(yminus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="24" height="25" onClick="AjaxMove(' + hor + ',' + (ver - 1) + ', 1);" src="html/static/images/maps/arrows/arrowup.gif"></td>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="24" height="25" src="html/static/images/maps/arrows/arrowupno.gif"></td>';
	}
	if(yminusplus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="25" height="24" onClick="AjaxMove(' + (hor + 1) + ',' + (ver - 1) + ', 7);" src="html/static/images/maps/arrows/arrowrightup.gif"></td></tr>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="25" height="24" src="html/static/images/maps/arrows/arrowrightupno.gif"></td></tr>';
	}
	if(xminus == false) {
		moving = moving + '<tr><td><img style="cursor:pointer;" width="25" height="24" onClick="AjaxMove(' + (hor - 1) + ',' + ver + ', 3);" src="html/static/images/maps/arrows/arrowleft.gif"></td><td><img src="html/static/images/sprites/' + trainer + 'whole.gif" border="0" /></td>';
	} else {
		moving = moving + '<tr><td><img  style="cursor:default" width="25" height="24" src="html/static/images/maps/arrows/arrowleftno.gif"></td><td><img src="html/static/images/sprites/' + trainer + 'whole.gif" border="0" /></td>';
	}
	if(xplus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="25" height="24" onClick="AjaxMove(' + (hor + 1) + ',' + ver + ', 4);" src="html/static/images/maps/arrows/arrowright.gif"></td></tr>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="25" height="24" src="html/static/images/maps/arrows/arrowrightno.gif"></td></tr>';
	}
	if(yplusminus == false) {
		moving = moving + '<tr><td><img style="cursor:pointer;" width="25" height="24" onClick="AjaxMove(' + (hor - 1) + ',' + (ver + 1) + ', 6);" src="html/static/images/maps/arrows/arrowleftdown.gif"></td>';
	} else {
		moving = moving + '<tr><td><img style="cursor:default" width="25" height="24" src="html/static/images/maps/arrows/arrowleftdownno.gif"></td>';
	}
	if(yplus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="24" height="25" onClick="AjaxMove(' + hor + ',' + (ver + 1) + ', 2);" src="html/static/images/maps/arrows/arrowdown.gif"></td>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="24" height="25" src="html/static/images/maps/arrows/arrowdownno.gif"></td>';
}
	if(yplusplus == false) {
		moving = moving + '<td><img style="cursor:pointer;" width="24" height="25" onClick="AjaxMove(' + (hor + 1) + ',' + (ver + 1) + ', 8);" src="html/static/images/maps/arrows/arrowrightdown.gif"></td></tr>';
	} else {
		moving = moving + '<td><img style="cursor:default" width="24" height="25" src="html/static/images/maps/arrows/arrowrightdownno.gif"></td></tr>';
	}
	cursor = moving + "</table>";
	
	document.getElementById("arrows").innerHTML = cursor;
}

