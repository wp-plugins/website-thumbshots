/*
 * Thumbshot Preview script for Thumbshots.RU
 * Powered by jQuery (http://www.jquery.com)
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 * Date: 02-Feb-2012
 *
 * Скрипт добавляет всплывающую картинку к тамбшотам
 * Адрес картинки берется из атрибута alt
 *
 */
function ThumbshotPreview(tclass, target) {
	if (!target) {
		target = jQuery("img.thumbshots_plugin[alt^=\'http:\']");
	}
	if (target.length < 1) return;

	jQuery('<style type="text/css"> .' + tclass + ' {position:absolute; left:-20000px; display:none; z-index:10; border:1px solid #ccc; background:#333; padding:2px; color:#fff; line-height: normal} .' + tclass + ' img {margin:0;padding:0;border:none} </style>').appendTo('head');

	jQuery(target).each(function (i) {
		jQuery(this).hover(function () {
			jQuery("body").append("<div class='" + tclass + "' id='" + tclass + i + "'><img src='" + jQuery(this).attr('alt') + "' alt='Loading preview' /><br />" + jQuery(this).attr('title') + "</div>");
			jQuery(this).attr('title','');
			jQuery("#" + tclass + i).css({
				opacity: 1,
				display: "none"
			}).fadeIn(50)
		}, function () {
			jQuery("#" + tclass + i).fadeOut(50)
		}).mousemove(function (kmouse) {
			jQuery("#" + tclass + i).css({
				left: kmouse.pageX + 25,
				top: kmouse.pageY - 55
			})
		})
	})
}


function ThumbshotExt( tclass, target ) {
	if( ! target ) {
		target = jQuery("article a[href^=\'http:\']").not("[class~=\'thumbshot-no-popup\']").not("[href*=\'" + window.location.host + "\']").get();
	}
	if (target.length < 1) return;

	var img_height = 90;
	var host = "http://get.thumbshots.ru/";
	var params = new Array();
	params["size"] = "M"; // XS, S, M, L
	params["lang"] = "en"; // ru
	//params["w"] = 400;
	//params["h"] = 300;
	//params["key"] = "";

	jQuery('<style type="text/css"> .' + tclass + ' {position:absolute; left:-20000px; display:none; z-index:10; border:1px solid #ccc; background:#333; padding:2px; color:#fff; line-height: 0} .' + tclass + ' img {margin:0;padding:0;border:none} </style>').appendTo('head');

	var query = [];
	for (var v in params) {
		if (typeof params[v] != "function") {
			query.push(encodeURIComponent(v) + "=" + encodeURIComponent(params[v]))
		}
	}

	jQuery(target).each(function (i) {
		jQuery(this).hover(function () {
			if( jQuery(this).find('img:first').attr("class") ) return;
			var src = host + "?" + query.join("&") + "&url=" + jQuery(this).attr("href");
			jQuery("body").append("<div class='" + tclass + "' id='" + tclass + i + "'><img src='" + src + "' alt='Loading preview' /></div>");
			jQuery("#" + tclass + i).css({
				opacity: 1,
				display: "none"
			}).fadeIn(50)
		}, function () {
			jQuery("#" + tclass + i).fadeOut(50)
		}).mousemove(function (kmouse) {
			x = kmouse.pageX;
			y = kmouse.pageY - 50 - img_height;
			jQuery("#" + tclass + i).css({ left: x, top: y });
		})
	});
}