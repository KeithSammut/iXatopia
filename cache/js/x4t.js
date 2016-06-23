/*!
	jQuery Cookie Plugin
	https://github.com/carhartl/jquery-cookie
	
	Copyright 2011, Klaus Hartl
	Dual licensed under the MIT or GPL Version 2 licenses.
	http://www.opensource.org/licenses/mit-license.php
	http://www.opensource.org/licenses/GPL-2.0
*/
(function(a){a.cookie=function(b,c,d){if(arguments.length>1&&(!/Object/.test(Object.prototype.toString.call(c))||c===null||c===undefined)){d=a.extend({},d);if(c===null||c===undefined){d.expires=-1}if(typeof d.expires==="number"){var e=d.expires,f=d.expires=new Date;f.setDate(f.getDate()+e)}c=String(c);return document.cookie=[encodeURIComponent(b),"=",d.raw?c:encodeURIComponent(c),d.expires?"; expires="+d.expires.toUTCString():"",d.path?"; path="+d.path:"",d.domain?"; domain="+d.domain:"",d.secure?"; secure":""].join("")}d=c||{};var g=d.raw?function(a){return a}:decodeURIComponent;var h=document.cookie.split("; ");for(var i=0,j;j=h[i]&&h[i].split("=");i++){if(g(j[0])===b)return g(j[1]||"")}return null}})(jQuery)

// Configuration
var contentSliderSpeed = 5000;
var animationSpeed = 200;

// Custom parse function for cross browser compatibility (IE)
function parseDate(dateString)
{
	var v = dateString.split(' ');
	return new Date(Date.parse(v[1] + ' ' + v[2] + ', ' + v[5] + ' ' + v[3] + ' UTC'));
}

// Relative date function for tweets
var relativeDate = function(dateString)
{
	// Create date object and parse
	var date = new Date();
    date.setTime(parseDate(dateString));
        
    // Calculate distances    
	var distanceSeconds = ((new Date() - date) / 1000);
	var distanceMinutes = Math.floor(distanceSeconds / 60);
	
	// Return according to seconds
	if (distanceMinutes == 0) return 'less than a minute ago';
	if (distanceMinutes == 1) return 'a minute ago';
	if (distanceMinutes < 45) return distanceMinutes + ' minutes ago';
	if (distanceMinutes < 90) return 'about 1 hour ago';
	if (distanceMinutes < 1440) return 'about ' + Math.round(distanceMinutes / 60) + ' hours ago';
	if (distanceMinutes < 2880) return '1 day ago';
	if (distanceMinutes < 43200) return Math.floor(distanceMinutes / 1440) + ' days ago';
	if (distanceMinutes < 86400) return 'about 1 month ago';
	if (distanceMinutes < 525960) return Math.floor(distanceMinutes / 43200) + ' months ago';
	if (distanceMinutes < 1051199) return 'about 1 year ago';
	return 'over ' + Math.floor(distanceMinutes / 525960) + ' years ago';
};

// DOM is ready
$(function()
{
	// Animate navigation
	var siteHeaderNavigationActive = false;
	$('div.siteHeader ul.navigation li').hover(function()
	{
		$(this).find('ul.dropdown').stop(true, true).hide().animate({ height: 'show' }, animationSpeed);
	}, function()
	{
		$(this).find('ul.dropdown').stop(true, true).show().animate({ opacity: 'hide' }, animationSpeed);
	});

	// Render content slider
	var contentSlider = $('div.contentSlider');
	var contentSlide = contentSlider.find('div.slide');
	if (contentSlide.length > 1)
	{
		// Calculate slide switch element width
		var contentSliderCount = contentSlide.length;
		var slideSwitchWidth = Math.round((480 - ((contentSlide.length - 1) * 9)) / contentSliderCount) - 2;
				
		// Prepare output		
		var printSwitch = '<div class="slideSwitch">';
		
		// Add a switch for every slide
		$.each(contentSlide, function(key)
		{
			// Hide slide if it is not the first
			if (key != 0)
			{
				$(this).hide();
			}
			
			// Get label for switch
			var label = $(this).find('.slideSwitchLabel').html();
			
			// Use a number if no label was given			
			if (label === null)
			{
				label = key + 1;
			}
					
			// Add id to switch with slide number
			$(this).attr('id', 'slide-' + key);
			printSwitch+= '<div id="switchSlide-' + key + '" style="width: ' + slideSwitchWidth + 'px;">' + label + '</div>';
		});
		
		// Output slide switches and adapt design
		contentSlider.css({ height: '380px' });
		contentSlide.css({ height: '380px', overflow: 'visible' });
		contentSlider.find('div.slide img').css({ marginTop: 0 });
		contentSlider.find('div.center').prepend(printSwitch + '</div>');
		contentSlider.find('div.slideSwitch div:first').addClass('active');
		contentSlider.find('div.slideSwitch div:last').css({ marginRight: 0 });
		
		// Create auto slider
		var autoSlideTimer;
		var autoSlide = function()
		{
			// Get active id
			var activeId = contentSlider.find('div.slideSwitch div.active').attr('id').split('-');
			activeId = activeId[1];
			
			// Get next id and store it in active id
			if (activeId == (contentSliderCount - 1))
			{
				activeId = 0;
			}
			else
			{
				activeId++;
			}
			
			// Call next slide
			$('#switchSlide-' + activeId).click();
			
			// Create timing event for next slide
			autoSlideTimer = window.setTimeout(autoSlide, contentSliderSpeed);
		};
		
		// Create timing event for next slide
		autoSlideTimer = window.setTimeout(autoSlide, contentSliderSpeed);
		
		// Add event listener for click
		var contentSliderActive = false;
		contentSlider.delegate('div.slideSwitch div', 'click', function(event)
		{
			// Deny click on active element
			if ($(this).is('.active'))
			{
				return false;
			}
			
			// Stop current animation if still active
			if (contentSliderActive)
			{
				$('div.contentSlider div.slide, div.contentSlider div.slide div.information, div.contentSlider div.slide img').stop(true, true);
			}
			
			// Set content slider active
			contentSliderActive = true;
			
			// Kill auto slide if user clicked		
			if (typeof event.which !== 'undefined')
			{
				window.clearTimeout(autoSlideTimer);
			}
		
			// Get slide id
			var id = $(this).attr('id').split('-');
			id = id[1];
			
			// Change slide and adapt design
			$('div.contentSlider div.slideSwitch div').removeClass('active');
			$(this).addClass('active');
			
			// Active slide
			var activeSlide = $('div.contentSlider .slide:visible');
			
			// Animate slide change
			activeSlide.find('div.information').animate({ marginLeft: '-420px', opacity: 0 }, 400, function()
			{
				activeSlide.hide();
				$(this).css({ marginLeft: 0, opacity: 1 });
			});
			
			activeSlide.find('img').animate({ marginRight: '-480px', opacity: 0 }, 400, function()
			{
				$(this).css({ marginRight: 0, opacity: 1 });
			});
			
			$('#slide-' + id).css({ position: 'absolute', top: '-340px' }).show().animate({ top: '0' }, 400, function()
			{
				$(this).css({ position: 'relative' });
				
				// Set content slider inactive
				contentSliderActive = false;
			});
		});
	}
	
	// Handle image zoom function
	$('a.imageZoom').click(function()
	{	
		// Get link to image
		var imageLink = $(this).attr('href');
				
		// Put image in DOM
		$('div.siteWrapper').prepend('<div class="siteOverlay"></div><div class="siteLoading"><div></div></div><img class="imageZoomBox" src="' + imageLink + '" alt="Zoom" /><div class="imageZoomClose"></div>');		
		
		// Animate image box
		$('div.siteOverlay').css({ opacity: 0 }).show().animate({ opacity: 0.9 }, animationSpeed);
		$('div.siteLoading').animate({ opacity: 'show' }, animationSpeed);
		
		// Load the image
		var image = $('img.imageZoomBox');
		$(image).load(function()
		{
			// Process image dimensions
			var imageRatio = 0;
			var imageWidth = $(this).width();
			var imageHeight = $(this).height();
			var windowWidth = $(window).width();
			var windowHeight = $(window).height();
			var maxWidth = windowWidth - 80;
			var maxHeight = windowHeight - 80;
			
			// Check image width
			if (imageWidth > maxWidth)
			{
				imageRatio = maxWidth / imageWidth;
				imageWidth = imageWidth * imageRatio;
				imageHeight = imageHeight * imageRatio;
			}
			
			// Check image height
			if (imageHeight > maxHeight)
			{
				imageRatio = maxHeight / imageHeight;
				imageWidth = imageWidth * imageRatio;
				imageHeight = imageHeight * imageRatio;
			}
			
			// Hide loading indicator
			$('div.siteLoading').hide();
			
			// Show and animate image
			$(this).css({ width: 0, height: 0 }).animate({ opacity: 'show', width: imageWidth + 'px', height: imageHeight + 'px', marginTop: '-' + ((imageHeight / 2) + 20) + 'px', marginLeft: '-' + ((imageWidth / 2) + 20) + 'px' }, animationSpeed, function()
			{
				$('div.imageZoomClose').css({ marginLeft: ((imageWidth / 2) - 20) + 'px', marginTop: '-' + ((imageHeight / 2) + 20) + 'px' }).animate({ opacity: 'show' }, animationSpeed);
			})
		});
		
		return false;
	});
	
	// Handle close event on overlay or imagebox
	$('div.siteWrapper').delegate('div.siteOverlay, div.imageZoomClose, div.siteLoading', 'click', function()
	{
		$('div.siteOverlay, img.imageZoomBox, div.imageZoomClose, div.siteLoading').animate({ opacity: 'hide' }, animationSpeed, function()
		{
			$(this).remove();
		});
	});
	
	// Generate tab navigation
	if ($('div.tabWrapper').length != 0)
	{	
		$('div.tabWrapper').each(function()
		{
			// Prepare tab output
			var printTabs = '<ul class="tabs">';
			var tabContent =  $(this).find('.tabContent');
			var tabCount = tabContent.length;
			
			$(tabContent).each(function(key)
			{
				// Hide tab if it is not the first
				if (key != 0)
				{
					$(this).hide();
				}
				
				// Get label for tab
				var label = $(this).find('.label').text();
								
				// Use a number if no label was given			
				if (!label)
				{
					label = 'Tab ' + (key + 1);
				}
			
				// Add id to tab content
				$(this).addClass('tab-' + key);
				printTabs+= '<li class="tabTrigger-' + key + '">' + label + '</li>';
			});
			
			// Add tabs
			$(this).prepend(printTabs + '</ul>');
			$(this).find('li:first').addClass('active');
		});
	}
	
	// Handle click on tabs
	$('.tabWrapper').delegate('ul.tabs li', 'click', function()
	{
		// Deny click on active element
		if ($(this).is('.active'))
		{
			return false;
		}
	
		// Get tab id
		var id = $(this).attr('class').split('-');
		id = id[1];
		
		// Display and animate new tab content
		var parent = $(this).parent().parent();
		parent.find('ul.tabs li').removeClass('active');
		$(this).addClass('active');
		parent.find('.tabContent').hide()
		parent.find('.tab-' + id).animate({ opacity: 'show' }, animationSpeed);
	});
	
	// Set label names
	var nameLabel = $('label[for="name"]').text();
	var emailLabel = $('label[for="email"]').text();
	var messageLabel = $('label[for="message"]').text();
	
	// Catch contact form submission
	$('#contactForm').submit(function()
	{
		// Validate full name
		var name = $('#name').val();		
		if (name.length == 0)
		{
			$('label[for="name"]').addClass('error').text(nameLabel + ' is required');
		}
		else
		{
			$('label[for="name"]').removeClass('error').text(nameLabel);
		}
		
		// Validate email address
		var email = $('#email').val();
		var emailPattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);		
		if (email.length == 0)
		{
			$('label[for="email"]').addClass('error').text(emailLabel + ' is required');
		}
		else if (!emailPattern.test(email))
		{
			$('label[for="email"]').addClass('error').text(emailLabel + ' is not valid');
		}
		else
		{
			$('label[for="email"]').removeClass('error').text(emailLabel);
		}
		
		// Validate message
		var message = $('#message').val();		
		if (message.length == 0)
		{
			$('label[for="message"]').addClass('error').text(messageLabel + ' is required');
		}
		else
		{
			$('label[for="message"]').removeClass('error').text(messageLabel);
		}
	
		// Deny submission if error was found
		if ($(this).find('label.error').length != 0)
		{
			return false;
		}
		else
		{
			$(this).find('button').text('Please wait...');
		}
	});
	
	// Set blog search value & event
	var blogSearch = $('form.blogSearch');
	if (blogSearch.length != 0)
	{
		var label = blogSearch.find('label').text();
		var input = blogSearch.find('input[type="text"]');
		input.val(label);
		
		input.focus(function()
		{		
			if ($(this).val() == label)
			{
				$(this).val('');
				$(this).focus();
			}
		});
		
		input.blur(function()
		{
			if ($(this).val() == '')
			{
				$(this).val(label);
				$(this).blur();
			}
		});
	}
	
	// Get latest tweet
	var twitterWidget = $('div.twitterWidget');
	if (twitterWidget.length != 0)
	{
		// Get profile name from twitter link
		var profileName = twitterWidget.find('a.profileLink').attr('href').replace('http://twitter.com/', '');
		
		// Print function			
		var printTweet = function(lastTweet, time)
		{
			// Get tweet and replace urls with html link
			lastTweet.replace(/(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig, function(url)
			{
				return '<a href="' + url + '">' + url + '</a>';
			});
			
			// Detect usernames and wrap with link
			lastTweet = lastTweet.replace(/\B@([\w-]+)/gm, function(username)
			{
				return '<a href="http://twitter.com/' + $.trim(username).replace('@', '') + '">' + username + '</a>';
			});
			
			// Detect hashes and wrap with link
			lastTweet = lastTweet.replace(/(^|\s+)#(\w+)/gi, function(hash)
			{
				return '<a href="http://twitter.com/search?q=%23' + $.trim(hash).replace('#', '') + '">' + hash + '</a>';
			});
							
			// Print tweet and relative date
			twitterWidget.find('p').html(lastTweet + '<span>' + relativeDate(time) + '</span>');
		};
		
		// Check if tweet is in cookie
		if ($.cookie('lastTweet') !== null && $.cookie('lastTweet').search('(:split:)') !== -1)
		{
			// Retrieve tweet from cookie
			var lastTweet = $.cookie('lastTweet').split('(:split:)');
			
			// Call print function
			printTweet(lastTweet[0], lastTweet[1]);
		}
		else
		{
			// Get latest tweet
			$.getJSON('http://twitter.com/statuses/user_timeline.json?screen_name=' + profileName + '&include_rts=true&callback=?', function(response)
			{
				// Check if everything needed is in place
				if (response && response[0] && response[0].text && response[0].created_at)
				{
					// Call print function
					printTweet(response[0].text, response[0].created_at);
												
					// Set cookie cache
					var expire = new Date();
					expire.setTime(expire.getTime() + (60 * 20 * 1000));
					$.cookie('lastTweet', response[0].text + '(:split:)' + response[0].created_at, { expires: expire, path: '/' });
				}
				else
				{
					// Print error if request failed
					twitterWidget.find('p').html('Error<span>Latest tweet could not be retrieved...</span>');
				}
			});
		}
	}
			
	// Animate "Back to Top"
	$('div.siteFooterBar a.backToTop').click(function()
	{
		$('html, body').animate({ scrollTop: 0 }, animationSpeed);
		return false;
	});
});