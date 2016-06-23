var trades = [];
var alltrades = [];
var powers = null;
var pages = null;
var type = 'list';
var currentPage = 1;

function writeTrade(trade, tType)
{
	powers = document.getElementById('powers');
	pages = document.getElementById('pages');
	alltrades = trade;
	trades = trade;
	type = tType;
	
	setPages();
	loadPage(1);
}

function setPages()
{
	page_max = trades.length;
	page_num = page_max / 20 + (page_max % 20 == 0 ? 0 : 1);
	pages.innerHTML = 'Pages: ';
	
	for(var i = 1; i <= page_num; i++)
	{
		pages.innerHTML += '<span class="pnt hus" onclick="loadPage(' + i + ');">' + i + '</span>&nbsp;&nbsp;';
	}
}

function loadPage(pn)
{
	currentPage = pn;
	var index = (pn - 1) * 20;
	var show = trades.slice(index, index + 20);
	var power = null;
	var html = null;
	
	if(show.length > 0)
	{
		powers.innerHTML = '';
		switch(type)
		{
			case 'trades':
				for(var pwrIndex in show)
				{
					power = show[pwrIndex];
					html  = '<div class="block c5">';
					html += '<div class="heading">' + power['name'] + '</div>';
					html += '<table style="width:99%">';
					html += '<tr class="price"> <td> Price </td> <td class="tr price"> ' + power["price"] + ' xats </td> </tr>';
					html += '<tr class="price"> <td> Store Price </td> <td class="tr price"> ' + power["cost"] + ' xats </td> </tr>';
					html += '<tr> <td> Seller ID </td> <td class="tr"> <a target="_blank" href="/profile?u=' + power["userid"] + '">' + power["userid"] + '</a> </td> </tr>';
					html += '<tr> <td> Amount Available </td> <td class="tr"> ' + power["count"] + ' </td> </tr>';
					html += '<tr> <td colspan="2" class="tc"> <button class="tradeButton" onclick="buyTrade(' + Math.floor(parseInt(index) + parseInt(pwrIndex)) + ');">Buy Power</button> </td> </tr>';
					html += '</table>';
					html += '</div>';
					
					powers.innerHTML += html;
				}
				break;
			
			case 'list':
				for(var pwrIndex in show)
				{
					power = show[pwrIndex];
					html  = '<div class="block c5">';
					html += '<div class="heading">' + power['name'] + '</div>';
					html += '<table style="width:99%">';
					html += '<tr> <td> Count </td> <td class="tr"> ' + power["count"] + ' </td> </tr>';
					html += '<tr> <td> Min Price </td> <td class="tr"> ' + Math.floor(0.8 * parseInt(power["cost"])) + ' </td> </tr>';
					html += '<tr> <td> Max Price </td> <td class="tr"> ' + Math.floor(1.2 * parseInt(power["cost"])) + ' </td> </tr>';
					html += '<tr> <td> Price(<i>xats</i>) </td> <td class="tr"> <input type="text" style="width:100%" id="price_' + Math.floor(parseInt(index) + parseInt(pwrIndex)) + '" value="' + power["cost"] + '" /> </td> </tr>';
					html += '<tr> <td colspan="2" class="tc"> <button class="tradeButton" onclick="listTrade(' + Math.floor(parseInt(index) + parseInt(pwrIndex)) + ');">List Power</button> </td> </tr>';
					html += '</table>';
					html += '</div>';
					
					powers.innerHTML += html;
				}
				break;
		}
	}
	else
	{
		powers.innerHTML = '<div class="block c1 tc"><i> No powers were found... </i></div>';
	}
}

function buyTrade(i)
{
	if(parseInt(getCookie('xats')) < parseInt(trades[i]['price']))
	{
		alert("You don't have enough xats to buy that power :c");
	}
	else
	{
		if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
		$.ajax({
			type: "POST",
			url: "/trade?ajax&sub=buyPower",
			data: "power=" + trades[i]['id'],
			success: function(json)
			{
				json = JSON.parse(json);
				if(json.status == 'ok')
				{
					alert("You have purchased " + trades[i]['name'] + ".\nRefresh your chat for it to show.");
					$('html').append(json.relogin);
					setTimeout(location.href = location.href, 1500);
				}
				else
				{
					alert(json.status);
				}
				
				if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
			}
		});
	}
}

function listTrade(i)
{
	if($('#loading').css('display') == 'none') $('#loading').fadeIn(200);
	$.ajax({
		type: "POST",
		url: "/trade?ajax&sub=listPower",
		data: "power=" + trades[i]['id'] + '&price=' + document.getElementById('price_' + i).value,
		success: function(json)
		{
			json = JSON.parse(json);
			if(json.status == 'ok')
			{
				alert("You have listed " + trades[i]['name'] + " for trade!");
				
				$('html').append(json.relogin);
				setTimeout(location.href = location.href, 1500);
			}
			else
			{
				alert(json.status);
			}
			
			if($('#loading').css('display') != 'none') $('#loading').fadeOut(200);
		}
	});
}



function do_tradeSearch()
{
	var search_name = $('.psearchname').val().toLowerCase();
	var search_option = $('.psearchprice0 option:selected').text();
	var search_price = parseInt($('.psearchprice').val());
	trades = [];
	
	for(var i in alltrades)
	{
		power = alltrades[i];
		
		if(power['name'] != undefined && power['name'].indexOf(search_name) != -1)
		{
			trades.push(power);
		}
	}
	
	setPages();
	loadPage(1);
}