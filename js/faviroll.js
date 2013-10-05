/**
 * faviroll.js - JavaScript for Faviroll backend
 * Author: UnderWordPressure
 * Version: latest
 * ----------------------------------------------------------------------------------------
 * Copyright 2009-2011 andurban.de  (email: http://www.andurban.de/kontakt)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Constructor
 */
Faviroll = function(){};


Faviroll.prototype = {
	
	/**
	 * Get faviroll envorionment informations from DOM and store it in a Collection
	 * @param _env - the environment Hash
	 */
	initenv : function($, _env) {

		// get necessary environment from DOM
		_env.wpmu_prefix = 	$('#wpmu_prefix').val();

		// Initialize member: plugin_url from self script url
		_env.plugin_url =	$('script[src*="/faviroll/js/faviroll.js"]').attr('src');
		if (_env.plugin_url) {
			var tmp = _env.plugin_url.split('/');
			if (tmp.length > 3) {
				tmp.pop();
				tmp.pop();
			};
			_env.plugin_url = tmp.join('/') + '/';
		};

		favi.env.load_icon_url = favi.env.plugin_url + 'img/wpspin_light.gif';
		
		return true;
	},


	/**
	 * onclick trigger
	 * Remove cache and settings
	 */
	removeSettingsAndCache : function() {
		var doIt = confirm('Click OK to remove all settings and empty favicon cache');
		if (!doIt)
			return false;

		var faviform = document.getElementById('faviform');
		if (!faviform) {
			alert("ERROR: Can't find faviform");
			return false;
		};

		var _remove = document.getElementById('_remove');
		if (_remove) {
			_remove.value = true;
		};

		faviform.submit();
		return true;
	},


	/**
	 * onclick trigger
	 * Reload cache from all website icons
	 * @param _this the fired object
	 */
	reloadIcons : function(_this) {
		
		var allBookmarks = this.getAllBookmarkInfos(_this);

		if (!allBookmarks)
			return false;
  
    var kju = new Array();

		// Walk through all bookmark infos, but skip the default icon (index 0)
		var row;
		for (var i = 0;i < allBookmarks.length; i++) {
			row = allBookmarks[i];

			kju.push({
				action:    'reload',
				siteid:    row.siteTD.id,
				url:       row.site_url
			});

			row.siteTD.img.src = favi.env.load_icon_url;
		};
		
		// Ins Environment übergeben, wegen setTimeout
		favi.env.ajaxq = kju;


		var qlen = kju.length -1;
		window.setTimeout('favi.roll.initCountdown(' + qlen +')', 20);

		// kleinen Timeout setzen, da ansonsten die GUI nicht aktualisiert wird.
		window.setTimeout('favi.roll.runAjaxQ()', 222);

		return true;
	},


	/**
	 * onclick trigger
	 * Reload cache from all website icons
	 * @param _this the fired object
	 */
	restoreOriginalIcons : function(_this) {

		var doIt = confirm('Click OK to restore all original bookmark favicons');
		if (!doIt)
			return false;

		
		var allBookmarks = this.getAllBookmarkInfos(_this);

		if (!allBookmarks)
			return false;


		// Walk through all bookmark infos
		var row;
		for (var i = 1;i < allBookmarks.length; i++) {
			row = allBookmarks[i];
			row.icon_style.backgroundImage = 'url(' + favi.env.load_icon_url + ')';

		};

		// Ins Environment übergeben, wegen setTimeout
		favi.env.ajaxq = new Array({
			action:    'restore'
		});

		// kleinen Timeout setzen, da ansonsten die GUI nicht aktualisiert wird.
		window.setTimeout('favi.roll.runAjaxQ()', 222);

		return true;		
	},
	

	/**
	 * Starts for every item in the static Array "favi.env.ajaxq" a separate Ajax-Query 
	 */
	runAjaxQ : function() {
		var ajax_url = favi.env.plugin_url + 'faviroll-ajax.php';

		var data;
		jQuery.each(favi.env.ajaxq, function() {

			data = this;
			data.wpmu_prefix = favi.env.wpmu_prefix;

			// AJAX Query posten
			var jqXHR = jQuery.ajax({
				 url: ajax_url,
				 type: 'POST',
				 data: data
			});
			
			jqXHR.done(function(_str) {
				favi.roll.jqXHR_callback(_str);
			});

			jqXHR.fail(function(jqXHR, textStatus) {	
					favi.roll.jqXHR_callback(jqXHR.responseText);
			});

		});
		
		return true;
	},


	/**
	 * JQuery Ajax Callback Dispatcher
	 * @param _str the response from the Ajax-Query
	 */
	jqXHR_callback : function(_str) {
		
		if (!_str)
			return false;

		var param = {};
		var attrs = _str.split('|');
		for (var i=0;i<attrs.length;i++) {
			var attr = attrs[i];
			var tmp = attr.split(':');

			var cmdstr = 'param.' + tmp.shift() + '="' + tmp.shift() + '";';
			eval(cmdstr); 
		};

		if (!param.action)
			return false;

		switch(param.action) {
			case 'restore':
				this.cb_restore_done(param);
				break;
			case 'reload':
				this.cb_reload_done(param);
				break;
			case 'useicon':
				this.cb_useicon_done(param);
				break;
			case 'customicon':
				this.cb_customicon_done(param);
				break;
			default:
				break;
		};
		
		return true;
	},


	/**
	 * Callback Function of ajax query for action:restore 
	 */
	cb_restore_done : function (_param) {
		if (!(_param))
			return false;

		var _this = document.getElementById('_restore');
		if (!_this)
			return false;
		
		var allBookmarks = this.getAllBookmarkInfos(_this);
		if (!allBookmarks)
			return false;
  
		// Walk through all bookmark infos, but skip the default icon (index 0)
		var row;
		for (var i = 1;i < allBookmarks.length; i++) {
			row = allBookmarks[i];
			row.icon_style.backgroundImage = 'url(' + row.siteTD.img.src + ')';
		};
		
		return true;
	},

	
	/**
	 * Callback Function of ajax query for action:reload 
	 */
	cb_reload_done : function (_param) {
		if (!(_param && _param.siteid && _param.basename))
			return false;

		var siteTD = document.getElementById(_param.siteid);

		if (!(siteTD && siteTD.tagName.toLowerCase() == 'td'))
			return false;

		var faviconURL = favi.env.plugin_url + 'cache/' + _param.basename;

		var img = siteTD.getElementsByTagName('img');
		if (img.length) {
			img = img[0];
			img.src = faviconURL;
		};

		// Falls das Bookmark-Icon auch das Splash-Icon ist, dieses mit updaten.
		var link = this.getLinkInfo(siteTD);
		if (!(link && link.style && link.icon_url))
			return false;

		if (link.icon_url.indexOf('/img/wpspin_light.gif') > -1)
			link.style.backgroundImage = 'url(' + faviconURL + ')';

		this.setCountdown(-1);

		return true;
	},


	/**
	 * Callback Function of ajax query for action:useicon
	 */
	cb_useicon_done : function (_param) {

		if (!(_param && _param.siteid && _param.basename))
			return false;
		var siteTD = document.getElementById(_param.siteid);
		
		if (!(siteTD && siteTD.tagName.toLowerCase() == 'td'))
			return false;

		var link = this.getLinkInfo(siteTD);
		if (!(link && link.style))
			return false;

		var iconUrl = favi.env.plugin_url + 'cache/' + _param.basename;
		
		link.style.backgroundImage = 'url(' + iconUrl + ')';

		return true;
	},


	/**
	 * Callback Function of ajax query for action:customicon
	 */
	cb_customicon_done : function (_param) {
		if (!(_param && _param.custid && _param.basename))
			return false;
		
		var custTD = document.getElementById(_param.custid);

		if (!(custTD && custTD.tagName.toLowerCase() == 'td'))
			return false;

		var faviconURL;
		if (_param.basename == 'invalid') {
			faviconURL = favi.env.plugin_url + 'img/empty.png';
		} else {
			faviconURL = favi.env.plugin_url + 'cache/' + _param.basename + '?' + Math.round((Math.random() * 1000000000));
		}

		var img = custTD.getElementsByTagName('img');
		if (img.length) {
			img = img[0];
			img.src = faviconURL;
		};

		return true;
	},


	/**
	 * Initialize the Countdown Tooltip
	 */
	initCountdown : function(_counter) {

		var div = document.getElementById('_faviroll_countdown');
		if (!div)
			return false;

		div.className = div.className.replace(/hidden/,'visible');
		div.innerHTML = _counter + ' icons left';
	},


	/**
	 * 
	 */
	setCountdown : function (_number) {
		
		var div = document.getElementById('_faviroll_countdown');
		if (!div)
			return false;

		var counter;
		var elems = div.innerHTML.split(' ');
		if (elems && elems.length > 2) {
			counter = elems[0] = parseInt(elems[0]) + _number;

			div.innerHTML = elems.join(' ');
		};
		
		// Ausblenden
		if (!isNaN(counter) && counter < 1)
			div.className = div.className.replace(/visible/,'hidden');

		return false;
	},
	
	
	/**
	 * Initalize the cache.
	 * Initialization goes in two stages
	 * Stage 1: onsubmit trigger
	 * Stage 2: automatic load favicon cache
	 */
	initCache : function(_doit) {
		
		if (_doit) {
			var elem = document.getElementById('_reload');
			if (elem)
			 return this.reloadIcons(elem);
			
		} else {

			var hidden = document.getElementById('_initialize');
			if (!hidden)
				return false;

			hidden.value = true;
		}
		
		return true;
	},


	/**
	 * get a parent DOM node selected by Tag-Type relative from a onclick fired object
	 * @param _this the fired object
	 * @param _tagName the tag is seaching for
	 * @param _withChildNodes the result node must have childNodes
	 * @return the result node OR false
	 */
	getParentNodeByTagName : function(_this,_tagName,_withChildNodes) {
		var node = _this;
		if (!node)
			return false;

		while(node.tagName.toLowerCase() != _tagName) {
			if (node && node.parentNode)
				node = node.parentNode;
		}

		if (node) {
			if (_withChildNodes && !node.hasChildNodes())
					return false;

			return node;
		}

		return false;
	},


	/**
	 * Prepare useful things from the bookmark table as an object
	 * @param _this the source element
	 * bookmarkTD, siteTD, custTD,...
	 */
	getAllBookmarkInfos : function(_this) {

		// walk up to the table body
		var tbody = this.getParentNodeByTagName(_this,'tbody',true);
		if (!tbody)
			return false;

		// Get all TR-Tags
		var trs = tbody.getElementsByTagName('tr');
		if (!trs)
			return false;
	    
	  var result = new Array();

		// Walk through all rows
		var isOk,tr,tds;
		for (var i = 0;i < trs.length; i++) {
			tr = trs[i];
				
			if (!(tr.tagName && tr.tagName.toLowerCase() == 'tr'))
				continue;

			tds = tr.getElementsByTagName('td');

			// Minimum test: TDs must be at least 3 times
			if (!(tds && tds.length > 3))
				continue;

			var row = {
				bookmarkTD:tds[0]	    // erste Spalte = Link-Infos 
			};

			var tmp = this.getLinkInfo(row.bookmarkTD);
			isOk = (tmp && tmp.icon_url);

			if (!isOk)
				continue;

			row.icon_style = tmp.style;
			row.icon_url = tmp.icon_url;
			row.site_url = tmp.site_url;

			// dritte Spalte = Site-Icon
			row.siteTD = tds[2];		

			tmp = row.siteTD.getElementsByTagName('img');
			if (tmp.length > 1)
				continue;

			row.siteTD.img = tmp[0];
			
			// vierte Spalte = Custom-Icon
			row.custTD = tds[3];
			tmp = row.custTD.getElementsByTagName('img');
			if (tmp.length > 1)
				continue;

			row.custTD.img = tmp[0];
			
			result.push(row);
		};

		return result;
	},
	

	/**
	 * Get the CSS object of the Link information (left side in table)
	 * @param _this the fired source object
	 * @return Object with keys {style, url}
	 */
	getLinkInfo : function(_this) {
		var result = {
				style: null,
				icon_url: null,
				site_url: null
		};
		
		var tr = this.getParentNodeByTagName(_this,'tr',true);
		if (!tr)
			return false;

		var tds = tr.getElementsByTagName('td');
		if (!tds)
			return false;
		
		// first TD contains link informations
		var td = tds[0];

		// get the link url which is into the SMALL Tag
		var strong = td.getElementsByTagName('strong');
		if (strong && strong.length > 0)
			strong = strong[0];
		
		if (strong && strong.style && strong.style.backgroundImage) {
			result.style = strong.style; 
			result.icon_url = strong.style.backgroundImage.replace(/^url|[\"\)\(]/g, '');
		};

		// get the link url which is into the SMALL Tag
		var small = td.getElementsByTagName('small');
		if (small && small.length > 0)
			small = small[0];

		if (small && small.innerHTML)
			result.site_url = small.innerHTML;

		// get the input hidden tag
		var inputs = td.getElementsByTagName('input');
		result.link_input = false;
		if (inputs) {
			for (var i = 0;i < inputs.length; i++) {
				input = inputs[i];
				if (input && input.type && input.type == 'hidden') {
					result.link_input = input;
					break;
				};
			};
		};
		
		return result;
	},

	
	/**
	 * use this icon
	 * @param _this - the object which fired the onlick() event
	 */
	use : function(_this) {
		if (!_this)
			return false;

		var span = _this.getElementsByTagName('span'); // Im <span> sind die relevanten Elemente enthalten und damit wird die Sichtbarkeit geschaltet
		if (!(span && span.length > 0))
			return false;

		span = span[0];

		if (span.className != 'visible')
			return false;


		// get the src from the selected icon
		var currentImg = null;
		var img = span.getElementsByTagName('img');
		if (img && img.length > 0 && img[0].src) {
			var currentImg = img[0].src;
		} else {
			return false;
		};

		var tr = this.getParentNodeByTagName(_this,'tr',true);
		if (!tr)
			return false;

		var tds = tr.getElementsByTagName('td');
		if (!(tds && tds.length > 2))
			return false;

		var siteid = tds[2].id;		// dritte Spalte = Site-Icons
	
		// first TD contains link informations
		td = tds[0];

		var linkTD = this.getLinkInfo(td);
		if (linkTD && linkTD.style) {

			linkTD.style.backgroundImage = 'url(' + favi.env.load_icon_url + ')';

			span.className = 'hidden';	// set hidden after usage
		
			// put basename in Ajax Queue fpr action:useicon
			var basename = currentImg.split('/');
			basename = basename.pop();

			// Ins Environment übergeben, wegen setTimeout
			favi.env.ajaxq = [{
					 action:    'useicon',
					 siteid:    siteid,
					 basename:  basename
			}];

			// kleinen Timeout setzen, da ansonsten die GUI nicht aktualisiert wird.
			window.setTimeout('favi.roll.runAjaxQ()', 222);
		};
		
		return true;
	},
	
	
	/**
	 * Setzt das aktuell angecheckte Icon in die Spalte für die Selektion
	 * Bei den Links die diese Icon schon haben, wird die Zelle ausgeblendet
	 * @param _this the fired object
	 */
	setupSelectionIcons : function (_this) {

		// -- Source Image {
		// minimal test for the radio button
		if (!(_this && _this.tagName && _this.tagName.toLowerCase() == 'input') && _this.nextSibling)
			return false;

		// get the src from the site-icon
		var img = _this.nextSibling;
		if (!(img && img.src && img.tagName && img.tagName.toLowerCase() == 'img'))
			return false;

		if (img.src.indexOf('/img/empty.png') > -1)
			return false;
		
		var currentImg = img.src;

		if (currentImg.indexOf('/img/wpspin_light.gif') > -1)
			return false;

		// -- Source Image }

		// walk up to the table body
		var tbody = this.getParentNodeByTagName(_this,'tbody',true);
		if (!tbody)
			return false;

		// Get all TR-Tags
		var trs = tbody.getElementsByTagName('tr');
		if (!trs)
			return false;
		
		// Walk through all rows
		var tr,tds,img;
		for (var i = 0;i < trs.length; i++) {
			tr = trs[i];
			
			if (!(tr.tagName && tr.tagName.toLowerCase() == 'tr'))
				continue;

			tds = tr.getElementsByTagName('td');

			// Minimum test: TDs must be at least 3 times
			if (tds && tds.length > 2) {

				var link = {
				 td: tds[0]	    // erste Spalte = Link-Infos
				};
				
				var tmp = this.getLinkInfo(link.td);
				var isOk = (tmp && tmp.icon_url);

				if (isOk) {
					
					link.icon_url = tmp.icon_url;
					
					var span = tds[1].getElementsByTagName('span'); // zweite Spalte = Selektions-Icon ist in <span> eingebunden wegen Sichtbarkeit
					if (!(span && span.length > 0))
						return false;

					span = span[0];

					img = span.getElementsByTagName('img');
					if (img.length) {
						img = img[0];

						if (img.src && (currentImg != link.icon_url)) {
							img.src = currentImg;
							span.className = 'visible';
						} else {
							span.className = 'hidden';
						};
					};
				};
			};
		};

		return true;
	},


	/**
	 * Grab a image for the custom icon
	 * @param _this the onklick fired object 
	 */
	setCustomIcon : function(_this) {

		// minimal test for the radio button
		if (!(_this && _this.tagName && _this.tagName.toLowerCase() == 'input') && _this.previousSibling)
			return false;

		var iconURL = prompt('Input URL to a Website or an Image file','');
		if(!iconURL)
			return false;

		var img = _this.previousSibling;
		if (!(img && img.tagName.toLowerCase() == 'img'))
			return false;
		
		img.src = favi.env.load_icon_url;

		var tr = this.getParentNodeByTagName(_this,'tr',true);
		if (!tr)
			return false;

		var tds = tr.getElementsByTagName('td');
		if (!(tds && tds.length > 2))
			return false;

		var siteid = tds[2].id;		// dritte Spalte = Site-Icons
		var custid = tds[3].id;		// vierte Spalte = Custom-Icon
		// Ins Environment übergeben, wegen setTimeout
		favi.env.ajaxq = [{
				 action: 'customicon',
				 custid: custid,
				 url:    iconURL
		}];

		// kleinen Timeout setzen, da ansonsten die GUI nicht aktualisiert wird.
		window.setTimeout('favi.roll.runAjaxQ()', 222);
		
		return true;
	}
	
};
//
// ----- [end of class] -----
//

// Create favi namespace to avoid collisions
var favi = {
		env: {},
		roll: null
};

jQuery(document).ready(function ($) {
	favi.roll = new Faviroll();
	favi.roll.initenv($, favi.env);

	if (typeof(FAVIROLL_INITCACHE) == 'boolean')
		favi.roll.initCache(true);

});
