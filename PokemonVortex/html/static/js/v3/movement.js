// ---------------------------------------------- Keyboard movement -------------------------------------------------------//

(function(w){
    
    var d = w.document;
    var $A = function(a){var b=[];for(var i=0;i<a.length;i++){b.push(a[i]);}return b;};
    var $$ = function(s,r){return $A((r || d).querySelectorAll(s));};
    var $1 = function(s,r){return (r || d).querySelector(s);};
    
    if (/\/map(?:\.php)?$/.test(w.location.pathname)) {
        [
            {"s":"#arrows img[src$='/arrowleft.gif']","k":[37,100,65]},
            {"s":"#arrows img[src$='/arrowleftup.gif']","k":[103]},
            {"s":"#arrows img[src$='/arrowup.gif']","k":[38,104,87]},
            {"s":"#arrows img[src$='/arrowrightup.gif']","k":[105]},
            {"s":"#arrows img[src$='/arrowright.gif']","k":[39,102,68]},
            {"s":"#arrows img[src$='/arrowrightdown.gif']","k":[99]},
            {"s":"#arrows img[src$='/arrowdown.gif']","k":[98,83,40]},
            {"s":"#arrows img[src$='/arrowleftdown.gif']","k":[97]},
            {"s":"form[action='wildbattle.php'] input[type='submit']","k":[101,13,32]}
        ].forEach(function(map){
            d.addEventListener('keydown', function(e){
                map.k.forEach(function(k){
                    if (e.which !== k)
                        return;
                    
                    var bt = $1(map.s);
                    if (!bt)
                        return;
                    
                    bt.click();
                    e.preventDefault();
                    return;
                });
            }, false);
        });
    }
    
})(typeof unsafeWindow !== 'undefined' ? unsafeWindow : window);