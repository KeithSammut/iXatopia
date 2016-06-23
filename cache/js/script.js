function storeBuy(element)
{
	var xats = $(element).parent().parent().parent().siblings('.xats').val();
	var name = $(element).attr('name').substr(1);
	
	if(parseInt(getCookie('xats')) < parseInt(xats))
	{
		alert('You don\'t have enough xats to buy ' + name);
		return false;
	}
	
	if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
	
	$.ajax({
		type: "POST",
		url: "/powers?ajax",
		data: "storebuy=" + name,
		success: function(json)
		{
			json = jQuery.parseJSON(json);
			if(json.status != 'ok')
			{
				alert(json.status);
			}
			else
			{
				alert('You have purchased ' + name + '.\nRefresh your chat for it to show.');
				$('html').append(json.relogin);
			}
			if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
		}
	});
}

$('.myprofile').mouseover(
	function()
	{
		$('.hoverxats').html(getCookie('xats'));
		$('.hoverdays').html(getCookie('days'));
		$('.hoverprofile').fadeIn(200);
	}
);

$('.myprofile').mouseout(
	function()
	{
		$('.hoverprofile').fadeOut(200);
	}
);


$('.msearchinput').keypress( function(e) { if(e.which == 13) { $('.msearchsubmit').click(); } } );
$('.msearchsubmit').click(
	function()
	{
		$('.showmessages').empty();
		
		if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
		
		$.ajax({
			type: "POST",
			url: "/search?ajax",
			data: 'search=' + encodeURIComponent($('.msearchinput').val()),
			success: function(json)
			{
				json = jQuery.parseJSON(json);
				if(json.messages.length == 0)
				{
					$('.showmessages').prepend('<div class="block c1 tc"> No messages were found </div>');
				}
				for(var i = 0; i < json.messages.length; i++)
				{
					var html = '<div class="block c1">';
					html += '<div class="heading">';
					html += json.messages[i]['name'];
					if(getCookie('rank') == ranks['admin'])
					{
						html += '<a class="fr boringlink deletemessage" href="del=' + json.messages[i]['mid'] + '">delete</a>';
					}
					html += '</div>';
					html += json.messages[i]['message'];
					html += '</div>';
					$('.showmessages').prepend(html);
					if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
				}
			}
		});
	}
);

$('html').on('click', '.deletemessage',
	function(message)
	{
		var that = this;
		if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
		
		$.ajax({
			type: "POST",
			url: "/search?ajax",
			data: $(this).attr('href'),
			success: function(json)
			{
				json = jQuery.parseJSON(json);
				if(json.status == 'SUCCESS')
				{
					$(that).parent().parent().fadeOut(400);
				}
				if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
			}
		});
		return false;
	}
);

$('.relogin').click(
	function()
	{
		$.ajax({
			type: "POST",
			url: "/relogin?ajax",
			success: function(data)
			{
				$('body').append(data);
			}
		});
	}
);

$('.claimCredit').click(
	function()
	{
		$.ajax({
			type: "POST",
			url: "/claimcredit?ajax",
			success: function(data)
			{
				$('body').append(data);
			}
		});
	}
);

$('.egrouppass').keypress( function(e) { if(e.which == 13) { $('.egroupsub').click(); } } );
$('.egroupname').keypress( function(e) { if(e.which == 13) { $('.egroupsub').click(); } } );

$('.egroupsub').click(
	function()
	{
		if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
		var edit_gn = encodeURIComponent($('.egroupname').val());
		var edit_pw = encodeURIComponent($('.egrouppass').val());
		
		$.ajax({
			type: "POST",
			url: "/edit?ajax",
			data: 'gn=' + edit_gn + '&gp=' + edit_pw,
			success: function(data)
			{
				json = jQuery.parseJSON(data);
				switch(json.response)
				{
					case 'success':
						$('.edit_group_box').html(json.html);
						$('.edit_group_login').fadeOut(300,
							function()
							{
								var egf = '.edit_group_conf';
								$(egf + ' .eg_innr').val(json.chatInnr);
								$(egf + ' .eg_scrl').val(json.chatScrl);
								$(egf + ' .eg_rdio').val(json.chatRdio);
								$(egf + ' .eg_bttn').val(json.chatBttn);
								$(egf + ' .eg_atch').val(json.chatAtch);
								$('.edit_group_conf').fadeIn(300);
							}
						);
						break;
					default:
						$('.edit_group_box').html('');
						$('.edit_group_conf').hide();
						$('.edig_group_login').show();
						alert(json.response);
						break;
				}
				if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
			}
		});
	}
);

$('.eg_innr').keypress( function(e) { if(e.which == 13) { $('.eg_submit').click(); } } );
$('.eg_scrl').keypress( function(e) { if(e.which == 13) { $('.eg_submit').click(); } } );
$('.eg_rdio').keypress( function(e) { if(e.which == 13) { $('.eg_submit').click(); } } );
$('.eg_bttn').keypress( function(e) { if(e.which == 13) { $('.eg_submit').click(); } } );
$('.eg_atch').keypress( function(e) { if(e.which == 13) { $('.eg_submit').click(); } } );

$('.eg_submit').click(
	function()
	{
		if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
		data  = 'gn=' + encodeURIComponent($('.egroupname').val()) + '&';
		data += 'gp=' + encodeURIComponent($('.egrouppass').val()) + '&';
		data += 'innr=' + encodeURIComponent($('.edit_group_conf .eg_innr').val()) + '&';
		data += 'scrl=' + encodeURIComponent($('.edit_group_conf .eg_scrl').val()) + '&';
		data += 'rdio=' + encodeURIComponent($('.edit_group_conf .eg_rdio').val()) + '&';
		data += 'bttn=' + encodeURIComponent($('.edit_group_conf .eg_bttn').val()) + '&';
		data += 'atch=' + encodeURIComponent($('.edit_group_conf .eg_atch').val());
		
		$.ajax({
			type: "POST",
			url: "/edit?ajax",
			data: data,
			success: function(data)
			{
				json = jQuery.parseJSON(data);
				switch(json.response)
				{
					case 'success':
						$('.edit_group_box').html($('.edit_group_box').html());
						alert(json.message);
						break;
					default:
						alert("NOPE" + json.message);
						break;
				}
				
				if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
			}
		});
	}
);

$('.psearchname').keyup(
	function()
	{
		do_search();
	}
);

$('.psearchprice').keyup(
	function()
	{
		if(/\D/g.test(this.value))
		{
			this.value = this.value.replace(/\D/g, '');
		}
		do_search();
	}
);

$('.psearchprice0').change(
	function()
	{
		do_search();
	}
);



function do_search()
{
	var search_name = $('.psearchname').val().toLowerCase();
	var search_option = $('.psearchprice0 option:selected').text();
	var search_price = parseInt($('.psearchprice').val());
	usepowers = [];
	
	for(var i in powers)
	{
		power = powers[i];
		
		if(power['name'].indexOf(search_name) != -1)
		{
			usepowers.push(power);
		}
	}
	
	setPages();
	loadPage(1);
}




function getCookie(name)
{
	cookies = document.cookie.split('; ');
	for(i = 0; i < cookies.length; i++)
	{
		cookie = cookies[i].split('=');
		if(cookie[0] == name)
		{
			return unescape(cookie[1]);
		}
	}
	return false;
}

