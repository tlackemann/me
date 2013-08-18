'use strict';

;(function() {
	var TOM = function() {

		var self = this;
		this.api = 'api/',
		this.methods = {
			'twitter'		: true, 	// Twitter API v.1.1 (OAuth)
			'instagram' 	: true, 	// Instagram API (OAuth)
			'rss'			: true, 	// RSS Feeds
			'events'		: true, 	// Events Feeds
			'bandcamp'		: false,	// Bandcamp API
			'soundcloud'	: false,	// Soundcloud API
			'facebook'		: false,	// Facebook API (OAuth)
		},

		this.priority = {
			'twitter'		: 0,
			'instagram'		: 0,
			'rss'			: 1,
			'events'		: 1
		},

		this.fullScreen = true,	
		this.columns = 5,
		// this.columns = {
		// 	'>920' : 5,
		// 	'<920' : 3,
		// 	'<500' : 1,
		// },
		this.pagePadding = 30, 
		this.ajaxLoader = '.tom-ajax',
		this.defaultColumnWidth = 340,
		this.defaultPageWidth = 1020,
		this.html = [],
		this.container = '#main',
		this.limit = 15,
		this.loadMore = false,
		this._offset = 0,
		this._currentColumns = 0,
		this._skipPriorities = 0,
		this.currentColumnWidth = 0,


		this.callApi = function() {
			// clear any data if any exists
			$(this.container).html('');
			this.html = [];

			// determine which methods to run
			var _params = '?';
			for(var method in this.methods)
			{
				_params += (this.methods[method] == true) ? method + '=1&' : method + '=0&';
			}

			this.api += _params;

			this.startAjaxLoader();

			$.ajax({
				type: 'GET',
				url: this.api,
				dataType: 'json',
				success: function(data)
				{
					self.renderTimeline(data);
					//self.initiateMasonry();
				}
			});
		},

		this.startAjaxLoader = function()
		{
			$(this.ajaxLoader).show();
		},
		this.killAjaxLoader = function()
		{
			$(self.ajaxLoader).hide();
		},

		this.renderTimeline = function(data) {
			var i = 0;
			var t = 0;

			for (var entry in data)
			{
				var e = document.createElement('div');
				if (data[entry].rss !== undefined && this.methods.rss == true)
				{
					
					var rss = data[entry].rss;
					var postDate = new Date(rss.pubDate);
					var amPm = (postDate.getHours() < 12) ? 'am' : 'pm';
					var hours = (postDate.getHours() >= 12) ? ('0' + (postDate.getHours()-11)).slice(-2) : ('0' + (postDate.getHours()+1)).slice(-2) ;

					var html = '';
					//this.html[i] =
					var classes = "tom-tile";
					classes += " tom-rss";
					classes += (this.priority.rss) ? " tom-priority-" + this.priority.rss : '';
					classes += (i == 0) ? " first" : '';

					html += '<a href="' + rss.link + '" target="_blank" class="' + classes + '"><span class="tom-rss">';
					html += '<span class="tom-pixafy">' + rss.title + '</span><span class="tom-divider"></span>' + 
								htmlDecode(rss.description + '') +
							'<span class="tom-divider"></span>';

					if (rss.guid.indexOf('pixafy',0) > 0)
					{
						html += '<span class="tom-date img-file">Pixafy - ' + postDate.toDateString() + ' at ' + hours + ':' + ('0' + (postDate.getMinutes()+1)).slice(-2) + ' ' + amPm.toUpperCase() + '</span>';
					}
					else
					{
						
						html += '<span class="tom-date img-file">Personal Blog - ' + postDate.toDateString() + ' at ' + hours + ':' + ('0' + (postDate.getMinutes()+1)).slice(-2) + ' ' + amPm.toUpperCase() + '</span>';
					}
					
					html += '</span></a>';

					e.innerHTML = html;
					this.html[i] = e;
					i++;
				}
				if (data[entry].instagram !== undefined && this.methods.instagram == true)
				{
					var instagram = data[entry].instagram;
					var postDate = new Date(parseInt(instagram.caption.created_time) * 1000);
					var amPm = (postDate.getHours() < 12) ? 'am' : 'pm';
					var hours = (postDate.getHours() >= 12) ? ('0' + (postDate.getHours()-11)).slice(-2) : ('0' + (postDate.getHours()+1)).slice(-2) ;
					
					var classes = "tom-tile";
					classes += " tom-instagram";
					classes += (this.priority.instagram) ? " tom-priority-" + this.priority.instagram : '';
					classes += (i == 0) ? " first" : '';
					e.innerHTML =
						'<div class="' + classes + '"><div class="tom-instagram">' +
							'<img src="' + instagram.images.low_resolution.url + '"/>' +
							'<span class="tom-title">' + instagram.caption.text + '</span>' +
							'<div class="tom-divider"></div>' + 
							'<span class="tom-date img-instagram">' + postDate.toDateString() + ' at ' + hours + ':' + ('0' + (postDate.getMinutes()+1)).slice(-2) + ' ' + amPm.toUpperCase() + '</span>' +
						'</div></div>';
					this.html[i] = e;
					i++;
				}
				if (data[entry].twitter !== undefined && this.methods.twitter == true)
				{

					var twitter = data[entry].twitter;
					var postDate = new Date(twitter.created_at);
					var amPm = (postDate.getHours() < 12) ? 'am' : 'pm';
					var hours = (postDate.getHours() >= 12) ? ('0' + (postDate.getHours()-11)).slice(-2) : ('0' + (postDate.getHours()+1)).slice(-2) ;
					var tweet = twitter.tweet.text;

					// Fill in some of the missing twitter information (links, mentions, *sigh* hashtags)
					
					// urls
					for(var url in twitter.tweet.entities.urls) {
						var defaultUrl = twitter.tweet.entities.urls[url].url;
						var expandedUrl = twitter.tweet.entities.urls[url].expanded_url;
						// add link attributes to expandedUrl
						expandedUrl = '<a href="' + defaultUrl + '" target="_blank">' + expandedUrl + '</a>';
						tweet = tweet.replace(defaultUrl, expandedUrl);
					}
					// user mentions
					for(var user in twitter.tweet.entities.user_mentions) {
						var defaultUser = twitter.tweet.entities.user_mentions[user].screen_name;
						// add link attributes to user
						var expandedUser = '<a href="https://twitter.com/' + defaultUser + '" target="_blank">' + defaultUser + '</a>';
						tweet = tweet.replace('@' + defaultUser, '@' + expandedUser);
					}

					var classes = "tom-tile";
					classes += " tom-tweet";
					classes += (this.priority.twitter) ? " tom-priority-" + this.priority.twitter : '';
					classes += (i == 0) ? " first" : '';
					e.innerHTML = '<div class="' + classes + '"><div class="tom-tweet">' + 
						'<span class="img-twitter"></span><div class="tom-divider"></div>' +
						tweet + 
						'<div class="tom-divider"></div>' +
						'<span class="tom-date">' + postDate.toDateString() + ' at ' + hours + ':' + ('0' + (postDate.getMinutes()+1)).slice(-2) + ' ' + amPm.toUpperCase() + '</span>'
						'</div></div>';
					this.html[i] = e;
					i++;
				}
				if (data[entry].events !== undefined && this.methods.events == true)
				{
					var events = data[entry].events;
					var postDate = new Date(events.date);
					
					var classes = "tom-tile";
					classes += " tom-event";
					classes += (this.priority.events) ? " tom-priority-" + this.priority.events : '';
					classes += (i == 0) ? " first" : '';

					var html = '<div class="' + classes + '"><div class="tom-event">';
					if (events.image !== undefined && events.image != null)
					{
						html += '<img src="' + events.image + '"/>';
					}
					html += '<span class="tom-title">' + events.name + '</span>';
					html += '<div class="tom-divider"></div>';
					html +=	'<span class="tom-date img-star">' + postDate.toDateString() + '</span>';
					html += '</div></div>';
					e.innerHTML = html;
					html = '';
					this.html[i] = e;
					i++;
				}
				
			}
			this.initiateMasonry();
			//this.applyMasonry(255, true);
		},

		// Full size, set max width, figure out how many fit in window width
		// return how many columns
		this.calculateColumns = function(windowWidth)
		{
			/**
			 * @todo Make this a lot more customizable
			 */
			if (windowWidth < 520)
			{
				this._currentColumns = 1;
				this._skipPriorities = true;
			}
			else if (windowWidth >= 520 && windowWidth < 720)
			{
				this._currentColumns = 2;
				this._skipPriorities = true;
			}
			else if (windowWidth >= 720 && windowWidth < 1120)
			{
				this._currentColumns = 3;
				this._skipPriorities = false;
			}
			else if (windowWidth >= 1120)
			{
				this._currentColumns = this.columns;
				this._skipPriorities = false;
			}
		}

		this.resizeColumn = function()
		{
			var $columnWidth = 0;
			var $windowWidth = window.innerWidth;

			this.calculateColumns($windowWidth);

			if (this.fullScreen == true)
			{
				$columnWidth = (($windowWidth / this._currentColumns));
				this.currentColumnWidth = $columnWidth;
				return $columnWidth;
			}
			else
			{
				if ((($windowWidth / this._currentColumns) > this.defaultColumnWidth) && ($windowWidth > this.defaultPageWidth))
				{
					$columnWidth = this.defaultColumnWidth;
				}
				else
				{
					//console.log(window.innerWidth / 3);
					$columnWidth = $windowWidth / this._currentColumns;
				}
				this.currentColumnWidth = $columnWidth;
				return $columnWidth;
			}
			

		},

		this.applyResize = function(s)
		{
			var container = document.querySelector(this.container);
			var msnry = Masonry.data(container);
			var $size = parseInt(s);
			msnry.option({columnWidth: $size });
			$('.tom-tile').css({ maxWidth: ( $size - this.pagePadding ) }); 
			if (!this._skipPriorities)
			{
				$('.tom-priority-1').css({ maxWidth: ( $size * 2 ) - this.pagePadding }); 
			}
		},

		this.initiateMasonry = function()
		{
			var $columnWidth = self.resizeColumn();
			var container = document.querySelector(this.container);

			container.style.visibility = 'hidden';
			var msnry = new Masonry( container, {
				// options
				columnWidth: $columnWidth ,
				itemSelector: '.tom-tile'
			});
			var elems = [];
			var fragment = document.createDocumentFragment();
			for (var e in this.html)
			{
				var elem = this.html[e];
				if (e >= this.limit)
				{
					this.setLoadMore(true);
				}
				else
				{

					fragment.appendChild( elem );
					elems.push( elem );
					this._offset++;
				}
			}
			container.appendChild( fragment );

			setTimeout(function() {
				// Apply a max-width to the blocks
				// jQuery because I'm a hack
				$('.tom-tile').css({ maxWidth: ( ($columnWidth) - self.pagePadding ) }); 
				if (!self._skipPriorities)
				{
					$('.tom-priority-1').css({ maxWidth: ( $columnWidth * 2 ) - self.pagePadding }); 
				}

				container.style.visibility = 'visible';
				msnry.appended( elems )
				self.killAjaxLoader();

				if (self.canLoadMore() == true)
				{
					var loadMore = document.querySelector('.tom-load-more');
					loadMore.style.display = 'block';
				}
			}, 1000);
			
		},

		this.loadMoreElements = function ()
		{
			var container = document.querySelector(this.container);
			var msnry = Masonry.data(container);
			var i = 0;
			var elems = [];
			var fragment = document.createDocumentFragment();

			for (var e in this.html)
			{
				
				if (e > this._offset && i < this.limit)
				{
					var elem = this.html[e];
					fragment.appendChild( elem );
					elems.push( elem );
					this._offset++;
					i++;
					//console.log(this.html.length);
					var count = parseInt(e);
					if (count + 1 >= this.html.length)
					{
						var loadMore = document.querySelector('.tom-load-more');
						loadMore.style.display = 'none';
					}
				}

				
			}
			container.appendChild( fragment );
			// Set the max-width
			$('.tom-tile').not('.tom-priority-1').css({ maxWidth: ( self.currentColumnWidth - self.pagePadding ) }); 
			$('.tom-priority-1').css({ maxWidth: ( self.currentColumnWidth * 2 ) - self.pagePadding }); 

			return elems;
		},

		this.addType = function(type)
		{
			switch (type) {
				case 'rss' :
					this.methods.rss = true;
					break;
				case 'twitter' :
					this.methods.twitter = true;
					break;
				case 'events' :
					this.methods.events = true;
					break;
				case 'instagram' :
					this.methods.instagram = true;
					break;
			}
			this.callApi();
		},

		this.removeType = function(type)
		{
			switch (type) {
				case 'rss' :
					this.methods.rss = false;
					break;
				case 'twitter' :
					this.methods.twitter = false;
					break;
				case 'events' :
					this.methods.events = false;
					break;
				case 'instagram' :
					this.methods.instagram = false;
					break;
			}
			this.callApi();
		}

		this.setLoadMore = function(loadMore)
		{
			this.loadMore = loadMore;
		},

		this.canLoadMore = function()
		{
			return this.loadMore;
		}

	}

	

	if (!window.TOM)
	{
		window.TOM = new TOM();
	}
	else
	{
		window._TOM = new TOM();
	}
})();

docReady(function() {
	// Run the main function
	window.TOM.callApi();

	$('#main-filters li a').on('click', function(e) {
		e.preventDefault();
		if ($(this).hasClass('tom-filter-active')) {
			$(this).addClass('tom-filter-inactive');
			$(this).removeClass('tom-filter-active');
			window.TOM.removeType($(this).attr('data-tom-type'));
		}
		else
		{
			$(this).removeClass('tom-filter-inactive');
			$(this).addClass('tom-filter-active');
			window.TOM.addType($(this).attr('data-tom-type'));
		}
	})

	// Load more link
	$('.tom-load-more').on('click', function(e) {
		e.preventDefault();
		var elems = window.TOM.loadMoreElements();
		var container = document.querySelector(window.TOM.container);
		var msnry = Masonry.data(container);
		msnry.appended( elems );
		
		//setTimeout(function() { msnry.layout() }, 500);
	});

	// Some responsive js
	$(window).on('resize', function() {
		if (window.TOM.fullScreen == true)
		{
			var size = window.TOM.resizeColumn();
			window.TOM.applyResize(size);
		}
		else
		{
			if (window.innerWidth < window.TOM.defaultPageWidth)
			{
				var size = window.TOM.resizeColumn();
				window.TOM.applyResize(size);
			}
			else
			{
				if (window.TOM.currentColumnWidth < window.TOM.defaultColumnWidth)
				{
					window.TOM.applyResize(window.TOM.defaultColumnWidth);
				}
			}
		}
	});

});
function htmlEncode(value){
  //create a in-memory div, set it's inner text(which jQuery automatically encodes)
  //then grab the encoded contents back out.  The div never exists on the page.
  return $('<div/>').text(value).html();
}

function htmlDecode(value){
  return $('<div/>').html(value).text();
}
//document.write('<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>');