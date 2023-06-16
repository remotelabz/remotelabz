	// Open context menu block when you click on Captur link
document.addEventListener( "click", function(e) {
	var obj = $(e.target);
	if (obj.hasClass("menu-collapse"))
	{
	    e.preventDefault();  // Prevent default behaviour
	    e.stopPropagation();  // Prevent default behaviour
	    var item_class = obj.attr('data-path');
	    
	    if (obj.hasClass("active"))
	    {
		    $('.' + item_class).addClass('hidden');
		    obj.removeClass("active")
	    }
	    else
	    {
		    $('.context-collapsible:not(.'+ item_class+')').addClass('hidden');
		    $('.' + item_class).removeClass('hidden');
		    obj.addClass("active")
	    }
    	return false;
	}
}, true);

document.addEventListener( "Wich", function(e) {
	var obj = $(e.target);
	if (obj.hasClass("menu-collapse"))
	{
	    e.preventDefault();  // Prevent default behaviour
	    e.stopPropagation();  // Prevent default behaviour
	    var item_class = obj.attr('data-path');
	    
	    if (obj.hasClass("active"))
	    {
		    $('.' + item_class).addClass('hidden');
		    obj.removeClass("active")
	    }
	    else
	    {
		    $('.context-collapsible:not(.'+ item_class+')').addClass('hidden');
		    $('.' + item_class).removeClass('hidden');
		    obj.addClass("active")
	    }
    	return false;
	}
}, true);

/*
Open form startup-config
*/
