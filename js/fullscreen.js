
function fullscreen(){
    if ( window.location.search.indexOf('fullscreen=1') !== -1 ){
	var a = document.getElementsByClassName("header-title");
	a[0].style.display = 'none';
	a = document.getElementsByClassName("object-group");
	a[0].style.display = 'none';
	a = document.getElementsByClassName("filter-btn-container");
	a[0].style.display = 'none';
	a = document.getElementsByClassName("article");
	a[0].style.padding = '0 0 0 0';
	a = document.getElementsByClassName("table-forms-container");
	a[0].style.padding = '0 0 0 0';
	a = document.body.style['margin-bottom'] = '0';
	
	a = document.getElementsByClassName("screenitem");
	a[3].style.width='640px';
    }
}
